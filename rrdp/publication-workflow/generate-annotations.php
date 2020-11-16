<?php 

/*****
 * Author: Ethan Gruber
 * Date: November 2020
 * Function: Read the RRDP specimen spreadsheet and generate Nomisma-compliant RDF for die linking, to be ingested
 * into the Nomisma SPARQL endpoint at a named graph that reflects a URI of the scholar who makes the attribution
 *****/

ini_set("allow_url_fopen", 1);
define("DIE_URI_SPACE", "http://numismatics.org/rrdp/id/");
define("SPECIMEN_URI_SPACE", "http://localhost:8080/orbeon/numishare/rrdp-specimens/id/");

//an array of sheets for batches of RRC numbers
$sheets = array('https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=0&single=true&output=csv');

$errors = array();

//begin RDF file
$writer = new XMLWriter();
$writer->openURI("annotations.rdf");
//$writer->openURI('php://output');
$writer->startDocument('1.0','UTF-8');
$writer->setIndent(true);
//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
$writer->setIndentString("    ");

$writer->startElement('rdf:RDF');
$writer->writeAttribute('xmlns:rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
$writer->writeAttribute('xmlns:crm', "http://www.cidoc-crm.org/cidoc-crm/");
$writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema#');
$writer->writeAttribute('xmlns:nmo', "http://nomisma.org/ontology#");
$writer->writeAttribute('xmlns:dcterms', "http://purl.org/dc/terms/");
$writer->writeAttribute('xmlns:void', "http://rdfs.org/ns/void#");

foreach ($sheets as $sheet){
    $data = generate_json($sheet);    
    echo "Processing {$sheet}\n";
    
    foreach ($data as $row){
        if (strlen($row['Obv. Die ID']) > 0 && strlen($row['Rev. Die ID']) > 0){
            if (strlen($row['Canonical URI (CRRO only)']) > 0){
                $uri = $row['Canonical URI (CRRO only)'];
            } else {
                $uri = SPECIMEN_URI_SPACE . $row['ID'];
            }
            
            $writer->startElement('nmo:NumismaticObject');
                $writer->writeAttribute('rdf:about', $uri);
                
                $writer->startElement('nmo:hasObverse');
                    $writer->startElement('rdf:Description');
                        $writer->startElement('nmo:hasDie');
                            $writer->startElement('rdf:Description');
                                $writer->startElement('rdf:value');
                                    $writer->writeAttribute('rdf:resource', DIE_URI_SPACE . $row['Obv. Die ID']);
                                $writer->endElement();
                                if (strlen($row['Die Attribution']) > 0){
                                    $writer->startElement('crm:P141i_was_assigned_by');
                                        $writer->startElement('crm:E13_Attribute_Assignment');
                                            $writer->startElement('crm:P14_carried_out_by');
                                                $writer->writeAttribute('rdf:resource', "http://nomisma.org/editor/{$row['Die Attribution']}");
                                            $writer->endElement();
                                        $writer->endElement();
                                    $writer->endElement();
                                }
                            $writer->endElement();
                        $writer->endElement();
                    $writer->endElement();
                $writer->endElement();
                
                $writer->startElement('nmo:hasReverse');
                    $writer->startElement('rdf:Description');
                        $writer->startElement('nmo:hasDie');
                            $writer->startElement('rdf:Description');
                                $writer->startElement('rdf:value');
                                $writer->writeAttribute('rdf:resource', DIE_URI_SPACE . $row['Rev. Die ID']);
                                $writer->endElement();
                                if (strlen($row['Die Attribution']) > 0){
                                    $writer->startElement('crm:P141i_was_assigned_by');
                                        $writer->startElement('crm:E13_Attribute_Assignment');
                                            $writer->startElement('crm:P14_carried_out_by');
                                                $writer->writeAttribute('rdf:resource', "http://nomisma.org/editor/{$row['Die Attribution']}");
                                            $writer->endElement();
                                        $writer->endElement();
                                    $writer->endElement();
                                }
                            $writer->endElement();
                        $writer->endElement();
                    $writer->endElement();
                $writer->endElement();
                
                //insert dataset
                $writer->startElement('void:inDataset');
                    $writer->writeAttribute('rdf:resource', "http://numismatics.org/rrdp/attributions");
                $writer->endElement();
            $writer->endElement();
        } else {
            $errors[] = $row['ID'];
        }
    }

}

//end RDF file
$writer->endElement();
$writer->flush();

foreach ($errors as $error){
    echo "Missing die ID from {$error}\n";
}

//test URL http://images.numismatics.org/archivesimages%2Farchive%2Fschaefer_015_b01_p081-0.jpg/142,669,235,253/full/0/default.jpg

/***** CSV FUNCTIONS *****/
function generate_json($doc){
    $keys = array();
    $array = array();
    
    $csv = csvToArray($doc, ',');
    
    // Set number of elements (minus 1 because we shift off the first row)
    $count = count($csv) - 1;
    
    //Use first row for names
    $labels = array_shift($csv);
    
    foreach ($labels as $label) {
        $keys[] = $label;
    }
    
    // Bring it all together
    for ($j = 0; $j < $count; $j++) {
        $d = array_combine($keys, $csv[$j]);
        $array[$j] = $d;
    }
    return $array;
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

function url_exists($url) {
    if (!$fp = curl_init($url)) return false;
    return true;
}

?>