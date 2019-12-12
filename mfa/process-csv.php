<?php 

/************************
 AUTHOR: Ethan Gruber
 MODIFIED: December, 2019
 DESCRIPTION: Transform CSV linked to OCRE/CRRO URIs to RDF for ingest into Nomisma
 ************************/

//set user agent
ini_set('user_agent', 'Nomisma.org/PHP Harvesting');

CONST FINDSPOTS = array('http://www.wikidata.org/entity/Q137721'=>
    array('label'=>'Naucratis', 'lat'=>'30.9', 'long'=>'30.616667', 'uri'=>
        array('http://sws.geonames.org/351274/', 'https://pleiades.stoa.org/places/727169', 'http://nomisma.org/id/naucratis'))
);

$data = generate_json("mfa.csv");

//generate an array of records for outputting
$records = array();

//process each line in a spreadsheet
$recordCount = 1;
foreach ($data as $row){
    if (strlen($row['URI']) > 0 && strlen($row['ID']) > 0){
        
        $record = array();
        
        $uri = 'https://collections.mfa.org/objects/' . $row['ID'];
        echo "{$recordCount}: {$uri}\n";
        
        $record['uri'] = $uri;
        $record['cointype'] = $row['URI'];     
        
        if (strlen($row['Title']) > 0){
            $record['title'] = $row['Title'];
        } else {
            echo "No Title for {$row['ID']}\n";
        }
        
        if (array_key_exists('Findspot', $row)){
            if (preg_match('/^https?:\/\//', $row['Findspot'])){
                $record['findspot'] = $row['Findspot'];
            }
        }
        
        //initiate DOMDocument call to harvest image IDs and measurements from MFA HTML page
        $html = file_get_contents($uri);
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXpath($dom);
        
        //get accession number from page since trailing 0s have been dropped from the CSV
        
        $accnum = $xpath->query("//div[@class='detailField invnolineField']/span[@class='detailFieldValue']")->item(0)->nodeValue;
        
        $record['objectnumber'] = $accnum;
        
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
    //GLOBAL $findspots;
    
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
    $writer->writeAttribute('xmlns:crm', "http://www.cidoc-crm.org/cidoc-crm/");
    $writer->writeAttribute('xmlns:crmgeo', "http://www.ics.forth.gr/isl/CRMgeo/");
    $writer->writeAttribute('xmlns:skos', "http://www.w3.org/2004/02/skos/core#");
    
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
                
                //create the new Nomisma/ARIADNE compliant findspot data model
                if (isset($record['findspot'])){
                    $writer->startElement('nmo:hasFindspot');
                        $writer->startElement('nmo:Find');
                            $writer->startElement('crm:P7_took_place_at');
                                $writer->startElement('crm:E53_Place');
                                    $writer->startElement('crm:P89_falls_within');
                                        $writer->writeAttribute('rdf:resource', resolve_entity($record['findspot']));
                                    $writer->endElement();
                                $writer->endElement();
                            $writer->endElement();
                        $writer->endElement();
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
    
    //insert findspots
    
    foreach (FINDSPOTS as $uri=>$array){
        $writer->startElement('crm:E53_Place');
            $writer->writeAttribute('rdf:about', $uri);
            
            $writer->startElement('skos:prefLabel');
                $writer->writeAttribute('xml:lang', 'en');
                $writer->text($array['label']);
            $writer->endElement();
            
            foreach ($array['uri'] as $match){
                $writer->startElement('skos:exactMatch');
                    $writer->writeAttribute('rdf:resource', $match);
                $writer->endElement();
            }
            
            //use WGS84 and CRMGeo properties
            $writer->startElement('geo:location');
                $writer->writeAttribute('rdf:resource', $uri . '#this');
            $writer->endElement();
            $writer->startElement('crm:P168_place_is_defined_by');
                $writer->writeAttribute('rdf:resource', $uri . '#this');
            $writer->endElement();
            
           
            $writer->startElement('skos:inScheme');
                $writer->writeAttribute('rdf:resource', 'http://www.wikidata.org/entity/');
            $writer->endElement();
            $writer->startElement('rdf:type');
                $writer->writeAttribute('rdf:resource', 'http://www.w3.org/2004/02/skos/core#Concept');
            $writer->endElement();
        $writer->endElement();
        
        //SpatialThing
        $writer->startElement('crmgeo:SP5_Geometric_Place_Expression');
            $writer->writeAttribute('rdf:about', $uri . '#this');
            $writer->startElement('rdf:type');
                $writer->writeAttribute('rdf:resource', 'http://www.w3.org/2003/01/geo/wgs84_pos#SpatialThing');
            $writer->endElement();
            
            $writer->startElement('geo:lat');
               $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
               $writer->text($array['lat']);
            $writer->endElement();
            $writer->startElement('geo:long');
                $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
                $writer->text($array['long']);
            $writer->endElement();
            
            $writer->startElement('crmgeo:asWKT');
                $writer->writeAttribute('rdf:datatype', 'http://www.opengis.net/ont/geosparql#wktLiteral');
                $writer->text('Point(' . $array['long'] . ' ' . $array['lat'] . ')');
            $writer->endElement();
        $writer->endElement();
    }
    
    //end RDF file
    $writer->endElement();
    $writer->flush();
}

//perform lookup to resolve URI to Wikidata entity 
function resolve_entity($uri){    
    
    foreach (FINDSPOTS as $wikidata=>$array){
        foreach ($array['uri'] as $match){
            if ($match == $uri){
                return $wikidata;
            }
        }
    }
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