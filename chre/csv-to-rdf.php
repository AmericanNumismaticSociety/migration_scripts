<?php 

$data = generate_json('CHRE.csv');
$hoards = array();

foreach ($data as $row) {
    $id = $row['Hoard URI'];
    
    //insert hoard metadata into $hoards array
    if (!array_key_exists($id, $hoards)) {
        if (strlen($row['city']) > 0) {
            $placename = trim($row['city']);
        } else {
            $placename = trim($row['country']);
        }
        
        $hoards[$id] = array('uri'=>$id, 'prefLabel'=>$row['find_spot_name'], 'findspot_uri'=>$row['Findspot URI'], 'findspot_label'=>$placename);
        if (strlen($row['Pleiades']) > 0) {
            $hoards[$id]['pleiades'] = $row['Pleiades'];
        }
    }
    
    $hoards[$id]['contents'][] = $row['Type URI'];
}

//var_dump($hoards);

generate_rdf($hoards);

/*****
 * Functions
 *****/
function generate_rdf($hoards) {
    //start RDF/XML file
    //use XML writer to generate RDF
    $writer = new XMLWriter();
    $writer->openURI("chre.rdf");
    //$writer->openURI('php://output');
    $writer->startDocument('1.0','UTF-8');
    $writer->setIndent(true);
    //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
    $writer->setIndentString("    ");
    
    $writer->startElement('rdf:RDF');
    $writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema#');
    $writer->writeAttribute('xmlns:crm', "http://www.cidoc-crm.org/cidoc-crm/");
    $writer->writeAttribute('xmlns:nm', "http://nomisma.org/id/");
    $writer->writeAttribute('xmlns:nmo', "http://nomisma.org/ontology#");
    $writer->writeAttribute('xmlns:dcterms', "http://purl.org/dc/terms/");
    $writer->writeAttribute('xmlns:dcmitype', "http://purl.org/dc/dcmitype/");
    $writer->writeAttribute('xmlns:rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
    $writer->writeAttribute('xmlns:rdfs', "http://www.w3.org/2000/01/rdf-schema#");
    $writer->writeAttribute('xmlns:skos', "http://www.w3.org/2004/02/skos/core#");
    $writer->writeAttribute('xmlns:void', "http://rdfs.org/ns/void#");
    
    foreach ($hoards as $hoard){
        $uri = $hoard['uri'];
        
        $writer->startElement('nmo:Hoard');
            $writer->writeAttribute('rdf:about', $uri);
            $writer->startElement('rdf:type');
                $writer->writeAttribute('rdf:resource', 'http://www.w3.org/2004/02/skos/core#Concept');
            $writer->endElement();
            
            $writer->startElement('skos:prefLabel');
                $writer->writeAttribute('xml:lang', 'en');
                $writer->text($hoard['prefLabel']);
            $writer->endElement();
            $writer->startElement('skos:definition');
                $writer->writeAttribute('xml:lang', 'en');
                $writer->text($hoard['prefLabel'] . " hoard in Coin Hoards of the Roman Empire");
            $writer->endElement();
            
            //findspot
            if (array_key_exists('findspot_uri', $hoard)) {
                $writer->startElement('nmo:hasFindspot');
                    $writer->startElement('nmo:Find');
                        $writer->startElement('crm:P7_took_place_at');
                            $writer->startElement('crm:E53_Place');
                                //findspot name
                                $writer->startElement('rdfs:label');
                                    $writer->writeAttribute('xml:lang', 'en');
                                    $writer->text($hoard['findspot_label']);
                                $writer->endElement();
                                //gazetteer URI
                                $writer->startElement('crm:P89_falls_within');
                                    $writer->writeAttribute('rdf:resource', $hoard['findspot_uri']);
                                $writer->endElement();
                                
                                if (array_key_exists('pleiades', $hoard)){
                                    $writer->startElement('crm:P89_falls_within');
                                        $writer->writeAttribute('rdf:resource', $hoard['pleiades']);
                                    $writer->endElement();
                                }
                            $writer->endElement();
                        $writer->endElement();
                    $writer->endElement();
                $writer->endElement();
            }
            
            //contents
            if (array_key_exists('contents', $hoard)) {
                $writer->startElement('dcterms:tableOfContents');
                    $writer->startElement('dcmitype:Collection');
                    foreach ($hoard['contents'] as $type) {
                        $writer->startElement('nmo:hasTypeSeriesItem');
                            $writer->writeAttribute('rdf:resource', $type);
                        $writer->endElement();
                    }
                    $writer->endElement();
                $writer->endElement();
            }
            
            $writer->startElement('void:inDataset');
                $writer->writeAttribute('rdf:resource', 'https://chre.ashmus.ox.ac.uk/');
            $writer->endElement();
        $writer->endElement();
    }
    
    //end RDF file
    $writer->endElement();
    $writer->flush();
}

/*****
 * CSV Parsing
 *****/

//parse CSV
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