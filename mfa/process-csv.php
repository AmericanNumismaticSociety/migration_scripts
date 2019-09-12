<?php 

/************************
 AUTHOR: Ethan Gruber
 MODIFIED: August, 2019
 DESCRIPTION: Transform CSV linked to OCRE/CRRO URIs to RDF for ingest into Nomisma
 ************************/

//set user agent
ini_set('user_agent', 'Nomisma.org/PHP Harvesting');

$data = generate_json("mfa.csv");


//generate an array of records for outputting
$records = array();

//process each line in a spreadsheet
$recordCount = 1;
foreach ($data as $row){
    
    if (strlen($row['URI']) > 0 && strlen($row['ID']) > 0){
        echo "{$recordCount}\n";
        $record = array();
        
        $uri = 'https://collections.mfa.org/objects/' . $row['ID'];
        
        $record['uri'] = $uri;
        $record['cointype'] = $row['URI'];
        
        if (strlen($row['Object No.']) > 0){
            $record['objectnumber'] = $row['Object No.'];
        } else {
            echo "No ID for {$row['ID']}\n";
        }
        
        if (strlen($row['Title']) > 0){
            $record['title'] = $row['Title'];
        } else {
            echo "No Title for {$row['ID']}\n";
        }
        
        //initiate DOMDocument call to harvest image IDs and measurements from MFA HTML page
        $html = file_get_contents($uri);
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXpath($dom);
        
        $images = $xpath->query("//img[contains(@src, 'postagestamp')]");
        
        $count = 0;
        
        //parse images
        foreach ($images as $image){
            $pieces = explode('/', $image->getAttribute('src'));
            
            if ($count == 0){
                $record['obv_image'] = $pieces[4];
            } elseif ($count == 1){
                $record['rev_image'] = $pieces[4];
            }            
            $count++;
        }
        
        //extract measurements
        $measurements = $xpath->query("//div[contains(@class, 'dimensionsField')]/span[@class='detailFieldValue']");
        
        if ($measurements->length == 1){
            $statement = $measurements->item(0)->nodeValue;
            
            if (preg_match('/Diameter:\s+(\d+\.?\d?)\smm\./', $statement, $matches)) {
                if (is_numeric($matches[1])){
                    $record['diameter'] = $matches[1];
                }
            }
            
            if (preg_match('/Weight:\s+(\d+\.\d+?)\sgm\./', $statement, $matches)) {
                if (is_numeric($matches[1])){
                    $record['weight'] = $matches[1];
                }
            }
            
            if (preg_match('/Die\saxis:\s+(\d+)/', $statement, $matches)) {
                if (is_numeric($matches[1])){
                    $record['axis'] = $matches[1];
                }
            }
        }        
        
        //var_dump($record);        
        
        $recordCount++;
        $records[] = $record;
    }
}

generate_rdf($records);


function generate_rdf($records){
    //start RDF/XML file
    //use XML writer to generate RDF
    $writer = new XMLWriter();
    $writer->openURI("mfa.rdf");
    //$writer->openURI('php://output');
    $writer->startDocument('1.0','UTF-8');
    $writer->setIndent(true);
    //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
    $writer->setIndentString("    ");
    
    $writer->startElement('rdf:RDF');
    $writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema#');
    $writer->writeAttribute('xmlns:nm', "http://nomisma.org/id/");
    $writer->writeAttribute('xmlns:nmo', "http://nomisma.org/ontology#");
    $writer->writeAttribute('xmlns:dcterms', "http://purl.org/dc/terms/");
    $writer->writeAttribute('xmlns:foaf', "http://xmlns.com/foaf/0.1/");
    $writer->writeAttribute('xmlns:geo', "http://www.w3.org/2003/01/geo/wgs84_pos#");
    $writer->writeAttribute('xmlns:rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
    $writer->writeAttribute('xmlns:void', "http://rdfs.org/ns/void#");
    
    foreach ($records as $record){
        if (isset($record['cointype'])){
            $writer->startElement('nmo:NumismaticObject');
                $writer->writeAttribute('rdf:about', $record['uri']);
                $writer->startElement('dcterms:title');
                    $writer->writeAttribute('xml:lang', 'en');
                    $writer->text($record['title']);
                $writer->endElement();
                
                $writer->writeElement('dcterms:identifier', $record['objectnumber']);
                
                $writer->startElement('nmo:hasCollection');
                    $writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/mfa_boston');
                $writer->endElement();
                
                $writer->startElement('nmo:hasTypeSeriesItem');
                    $writer->writeAttribute('rdf:resource', $record['cointype']);
                $writer->endElement();
            
                if (isset($record['hoard'])){
                    $writer->startElement('dcterms:isPartOf');
                    $writer->writeAttribute('rdf:resource', $record['hoard']);
                    $writer->endElement();
                }
                
                //conditional measurement data
                if (isset($record['weight'])){
                    $writer->startElement('nmo:hasWeight');
                        $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
                        $writer->text($record['weight']);
                    $writer->endElement();
                }
                if (isset($record['diameter'])){
                    $writer->startElement('nmo:hasDiameter');
                        $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
                        $writer->text($record['diameter']);
                    $writer->endElement();
                }
                if (isset($record['axis'])){
                    $writer->startElement('nmo:hasAxis');
                        $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
                        $writer->text($record['axis']);
                    $writer->endElement();
                }
                
                //conditional images
                if (isset($record['obv_image'])){
                    $writer->startElement('nmo:hasObverse');
                        $writer->startElement('rdf:Description');
                            $writer->startElement('foaf:thumbnail');
                                $writer->writeAttribute('rdf:resource', "https://collections.mfa.org/internal/media/dispatcher/{$record['obv_image']}/resize%3Aformat%3Dthumbnail");
                            $writer->endElement();
                            $writer->startElement('foaf:depiction');
                                $writer->writeAttribute('rdf:resource', "https://collections.mfa.org/internal/media/dispatcher/{$record['obv_image']}/preview");
                            $writer->endElement();
                        $writer->endElement();
                    $writer->endElement();
                }
                if (isset($record['rev_image'])){
                    $writer->startElement('nmo:hasReverse');
                        $writer->startElement('rdf:Description');
                            $writer->startElement('foaf:thumbnail');
                                $writer->writeAttribute('rdf:resource', "https://collections.mfa.org/internal/media/dispatcher/{$record['rev_image']}/resize%3Aformat%3Dthumbnail");
                            $writer->endElement();
                            $writer->startElement('foaf:depiction');
                                $writer->writeAttribute('rdf:resource', "https://collections.mfa.org/internal/media/dispatcher/{$record['rev_image']}/preview");
                            $writer->endElement();
                        $writer->endElement();
                    $writer->endElement();
                }
                
                //void:inDataset
                $writer->startElement('void:inDataset');
                    $writer->writeAttribute('rdf:resource', 'https://www.mfa.org/');
                $writer->endElement();
                
                //end nmo:NumismaticObject
                $writer->endElement();
        }
    }
    
    //end RDF file
    $writer->endElement();
    $writer->flush();
}

//generate csv
function generate_csv($records, $project){
    $csv = '"objectnumber","title","uri","reference","type"' . "\n";
    
    foreach ($records as $record){
        $csv .= '"' . $record['objectnumber'] . '","' . $record['title'] . '","' . $record['uri'] . '","' . (isset($record['reference']) ? $record['reference'] : '') . '","' . (isset($record['cointype']) ? $record['cointype'] : '') . '"' . "\n";
    }
    
    file_put_contents("concordances-{$project}.csv", $csv);
}

function generate_json($doc){
    $keys = array();
    $geoData = array();
    
    $data = csvToArray($doc, ',');
    
    // Set number of elements (minus 1 because we shift off the first row)
    $count = count($data) - 1;
    
    //Use first row for names
    $labels = array_shift($data);
    
    foreach ($labels as $label) {
        $keys[] = $label;
    }
    
    // Bring it all together
    for ($j = 0; $j < $count; $j++) {
        $d = array_combine($keys, $data[$j]);
        $geoData[$j] = $d;
    }
    return $geoData;
}

// Function to convert CSV into associative array
function csvToArray($file, $delimiter) {
    if (($handle = fopen($file, 'r')) !== FALSE) {
        $i = 0;
        while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== FALSE) {
            for ($j = 0; $j < count($lineArray); $j++) {
                $arr[$i][$j] = $lineArray[$j];
            }
            $i++;
        }
        fclose($handle);
    }
    return $arr;
}
?>