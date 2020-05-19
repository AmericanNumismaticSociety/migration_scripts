<?php 

error_reporting(0);

$data = generate_json('bm-coins-4.csv');
$filename = 'bm.rdf';

//use XML writer to generate RDF
$writer = new XMLWriter();
$writer->openURI($filename);
//$writer->openURI('php://output');
$writer->startDocument('1.0','UTF-8');
$writer->setIndent(true);
//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
$writer->setIndentString("    ");

$writer->startElement('rdf:RDF');
$writer->writeAttribute('xmlns:rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
$writer->writeAttribute('xmlns:dcterms', "http://purl.org/dc/terms/");
$writer->writeAttribute('xmlns:nm', "http://nomisma.org/id/");
$writer->writeAttribute('xmlns:nmo', "http://nomisma.org/ontology#");
$writer->writeAttribute('xmlns:foaf', "http://xmlns.com/foaf/0.1/");
$writer->writeAttribute('xmlns:rdfs', "http://www.w3.org/2000/01/rdf-schema#");
$writer->writeAttribute('xmlns:void', "http://rdfs.org/ns/void#");
$writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema#');

//iterate through each row in the spreadsheet
$count = 1;
foreach ($data as $row){
        
    if (strlen($row['coinType']) > 0 || strlen($row['hoard']) > 0){        
        echo "Processing {$count}: {$row['URI']}.\n";
        $writer->startElement('nmo:NumismaticObject');
            $writer->writeAttribute('rdf:about', $row['URI']);
            $writer->startElement('dcterms:title');
                $writer->writeAttribute('xml:lang', 'en');
                $writer->text($row['title']);
            $writer->endElement();
            $writer->writeElement('dcterms:identifier', $row['id']);
            $writer->startElement('nmo:hasCollection');
                $writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/bm');
            $writer->endElement();
            
            if (strlen($row['coinType']) > 0){
                $writer->startElement('nmo:hasTypeSeriesItem');
                    $writer->writeAttribute('rdf:resource', $row['coinType']);
                $writer->endElement();
            }            
        
            //measurements
            if (is_numeric($row['axis'])){
                $writer->startElement('nmo:hasAxis');
                    $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
                    $writer->text($row['axis']);
                $writer->endElement();
            }
            if (is_numeric($row['diameter'])){
                $writer->startElement('nmo:hasDiameter');
                    $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
                    $writer->text($row['diameter']);
                $writer->endElement();
            }
            if (is_numeric($row['weight'])){
                $writer->startElement('nmo:hasWeight');
                    $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
                    $writer->text($row['weight']);
                $writer->endElement();
            }
    
            //images: try obverse and reverse first
            if (strlen($row['obv_thumb']) > 0 || strlen($row['rev_thumb']) > 0){
                if (strlen($row['obv_thumb']) > 0 || strlen($row['obv_depiction']) > 0){
                    $writer->startElement('nmo:hasObverse');
                        $writer->startElement('rdf:Description');
                        if (strlen($row['obv_thumb']) > 0 ){
                            $writer->startElement('foaf:thumbnail');
                                $writer->writeAttribute('rdf:resource', $row['obv_thumb']);
                            $writer->endElement();
                        }
                        if (strlen($row['obv_depiction']) > 0 ){
                            $writer->startElement('foaf:depiction');
                                $writer->writeAttribute('rdf:resource', $row['obv_depiction']);
                            $writer->endElement();
                        }
                        $writer->endElement();
                    $writer->endElement();
                }
                if (strlen($row['rev_thumb']) > 0 || strlen($row['rev_depiction']) > 0){
                    $writer->startElement('nmo:hasReverse');
                        $writer->startElement('rdf:Description');
                        if (strlen($row['rev_thumb']) > 0 ){
                            $writer->startElement('foaf:thumbnail');
                                $writer->writeAttribute('rdf:resource', $row['rev_thumb']);
                            $writer->endElement();
                        }
                        if (strlen($row['rev_depiction']) > 0 ){
                            $writer->startElement('foaf:depiction');
                                $writer->writeAttribute('rdf:resource', $row['rev_depiction']);
                            $writer->endElement();
                        }
                        $writer->endElement();
                    $writer->endElement();
                }
            
            } elseif (strlen($row['combined_thumb']) > 0){
                //try combined images next
                if (strlen($row['combined_thumb']) > 0 ){
                    $writer->startElement('foaf:thumbnail');
                        $writer->writeAttribute('rdf:resource', $row['combined_thumb']);
                    $writer->endElement();
                }
                if (strlen($row['combined_depiction']) > 0 ){
                    $writer->startElement('foaf:depiction');
                        $writer->writeAttribute('rdf:resource', $row['combined_depiction']);
                    $writer->endElement();
                }
            }
            
            //hoard URI
            if (strlen($row['hoard']) > 0){
                $writer->startElement('dcterms:isPartOf');
                    $writer->writeAttribute('rdf:resource', $row['hoard']);
                $writer->endElement();
            }
            
            //other typological attributes (hoard coins 
            if (strlen($row['material']) > 0){
                $writer->startElement('nmo:hasMaterial');
                    $writer->writeAttribute('rdf:resource', $row['material']);
                $writer->endElement();
            }
            
            if (strlen($row['denomination']) > 0){
                $writer->startElement('nmo:hasDenomination');
                    $writer->writeAttribute('rdf:resource', $row['denomination']);
                $writer->endElement();
            }
            
            if (strlen($row['mint']) > 0){
                $writer->startElement('nmo:hasMint');
                    $writer->writeAttribute('rdf:resource', $row['mint']);
                $writer->endElement();
            }
            
            //void:inDataset
            $writer->startElement('void:inDataset');
                $writer->writeAttribute('rdf:resource', 'https://www.britishmuseum.org/');
            $writer->endElement();
            
            //end nmo:NumismaticObject
        $writer->endElement();
    $count++;
    }
}
$writer->endElement();
$writer->flush();


/***** FUNCTIONS *****/
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