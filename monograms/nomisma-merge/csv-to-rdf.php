<?php 
/*****
 * Author: Ethan Gruber 
 * Date: September 2024
 * Function: Generate Monogram RDF/XML for Nomisma based on merged CSV file.
 *****/

$namespaces = array(
    'agco'=>'http://numismatics.org/agco/symbol/',
    'bigr'=>'https://numismatics.org/bigr/symbol/',
    'ocre'=>'http://numismatics.org/ocre/symbol/',
    'pella'=>'http://numismatics.org/pella/symbol/',
    'sco'=>'http://numismatics.org/sco/symbol/',
    'pco'=>'http://numismatics.org/pco/symbol/',
    'cn'=>'https://data.corpus-nummorum.eu/api/monograms/'
);

$data = generate_json('2024-09-25-monogram.csv');

$monograms = array();

$count = 1;

foreach ($data as $row) {
    
    if ($row['duplicate'] != 'delete'){
        $monogram = array();        
        
        $id = 'monogram.' . number_pad($count, 5);
        
        $monogram['id'] = $id;
        
        echo "Processing {$row['image']}\n";
        
        $monogram['uri'] = "http://nomisma.org/symbol/" . $id;
        
        //prefLabel and definition
        $monogram['prefLabel'] = "Monogram {$count}";
        $monogram['definition'] = "Monogram {$count} published by Nomisma.org. See matching URIs and bibliographic references for further information about the usage of this symbol on coinage.";
        
        $monogram['image_url'] = 'https://nomisma.org/svg/' . $id . '.svg';
        
        //add field of numismatics and source. Hard code for CN and OCRE but query RDF for others
        $values = source_and_fon($row, $namespaces);
        
        if (array_key_exists('fon', $values)){
            foreach ($values['fon'] as $fon) {
                $monogram['fon'][] = $fon;
            }
        }
        if (array_key_exists('source', $values)){
            foreach ($values['source'] as $source) {
                $monogram['source'][] = $source;
            }
        }
        
        if (array_key_exists('creator', $values)){
            $monogram['creator'] = $values['creator'];
        }
        
        unset($values);
        
        //add matching IDs
        $image = trim($row['image']);
        if ($row['category'] == 'pco' && strpos($image, '_') !== FALSE) {
            $image = strtok($image, '_');
            $monogram['matches'][] = $namespaces[$row['category']] . $image;
        } elseif ($row['category'] == 'cn') {
            $monogram['matches'][] = $namespaces[$row['category']] . str_replace('cn_controlmark_', '', $image);
        } else {
            $monogram['matches'][] = $namespaces[$row['category']] . $image;
        }   
        
        if (strlen(trim($row['duplicate'])) > 0) {
            $otherLinks = array();
            $otherLinks[] = trim($row['duplicate']);
            
            if (strlen(trim($row['duplicate2'])) > 0) {
                $otherLinks[] = trim($row['duplicate2']);
            }
            if (strlen(trim($row['duplicate3'])) > 0) {
                $otherLinks[] = trim($row['duplicate3']);
            }
            
            foreach ($otherLinks as $otherLink){                
                
                //perform fon/source lookup on $otherLink id
                foreach ($data as $otherRow) {
                    if ($otherRow['image'] == $otherLink){
                        
                        if ($otherRow['category'] == 'pco' && strpos($otherLink, '_') !== FALSE) {
                            $otherLink = strtok($otherLink, '_');                            
                            $monogram['matches'][] = $namespaces[$otherRow['category']] . $otherLink;
                        } elseif ($otherRow['category'] == 'cn') {
                            $monogram['matches'][] = $namespaces[$otherRow['category']] . str_replace('cn_controlmark_', '', $otherLink);
                        } else {
                            $monogram['matches'][] = $namespaces[$otherRow['category']] . $otherLink;
                        }
                        
                        $values = source_and_fon($otherRow, $namespaces);
                        
                        if (array_key_exists('fon', $values)){
                            foreach ($values['fon'] as $fon) {
                                if (!in_array($fon, $monogram['fon'])){
                                    $monogram['fon'][] = $fon;
                                }
                            }
                        }
                        if (array_key_exists('source', $values)){
                            foreach ($values['source'] as $source) {
                                if (!in_array($source, $monogram['source'])){
                                    $monogram['source'][] = $source;
                                }
                            }
                        }
                        
                        if (array_key_exists('creator', $values)){
                            $monogram['creator'] = $values['creator'];
                        }
                        
                        unset($values);
                    }
                }                
            }
            
            unset($otherLinks);
        }
        
        //add letters
        if (strlen($row['Lettercombo_NEW']) > 0) {
            $letters = str_replace(' ', '', $row['Lettercombo_NEW']);
            
            //replace accidental Latin with Greek letters in any combo that isn't in OCRE
            if ($row['category'] != 'ocre'){
                $latin = array('A','B','E','H', 'I','K','M','N','O','P','T','X','Y','Z');
                $greek = array('Α','Β','Ε','Η','Ι','Κ','Μ','Ν','Ο','Ρ','Τ','Χ','Υ','Ζ');
                
                $letters = str_replace($latin, $greek, $letters);
            }
            
            $monogram['letters'] = preg_split('//u', $letters, -1, PREG_SPLIT_NO_EMPTY);
        }
        
        $count++;
        
        $monograms[] = $monogram;
        //var_dump($monogram);
    } else {
        echo "Skipping {$row['image']}\n";
    }
}


write_monogram_rdf($monograms);

write_csv($monograms);


/***** FUNCTIONS *****/
function write_csv($monograms) {
    
    $rows[] = array('URI', 'exactMatch 1', 'exactMatch 2', 'exactMatch 3', 'exactMatch 4');
    
    foreach ($monograms as $monogram) {
        $row = array();
        $row[] = $monogram['uri'];
        foreach ($monogram['matches'] as $match) {
            $row[] = $match;
        }
        
        $rows[] = $row;
    }
    
    $fp = fopen('concordance.csv', 'w');
    
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    
    fclose($fp);
}

function write_monogram_rdf($monograms) {
    foreach ($monograms as $monogram) {
       
        $doc = new XMLWriter();
        //$doc->openUri('php://output');
        $doc->openUri('rdf/' . $monogram['id'] . '.rdf');
        
        $doc->setIndent(true);
        //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
        $doc->setIndentString("    ");
        
        $doc->startDocument('1.0','UTF-8');
        
        $doc->startElement('rdf:RDF');
        $doc->writeAttribute('xmlns:rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $doc->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $doc->writeAttribute('xmlns:skos', 'http://www.w3.org/2004/02/skos/core#');
        $doc->writeAttribute('xmlns:rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
        $doc->writeAttribute('xmlns:crmdig', 'http://www.ics.forth.gr/isl/CRMdig/');
        $doc->writeAttribute('xmlns:nmo', 'http://nomisma.org/ontology#');
        $doc->writeAttribute('xmlns:dcterms', 'http://purl.org/dc/terms/');
        $doc->writeAttribute('xmlns:prov', 'http://www.w3.org/ns/prov#');
        $doc->writeAttribute('xmlns:foaf', 'http://xmlns.com/foaf/0.1/');
        $doc->writeAttribute('xmlns:crm', 'http://www.cidoc-crm.org/cidoc-crm/');
        
            //monogram RDF
            $doc->startElement('nmo:Monogram');
                $doc->writeAttribute('rdf:about', $monogram['uri']);
                $doc->startElement('rdf:type');
                    $doc->writeAttribute('rdf:resource', 'http://www.w3.org/2004/02/skos/core#Concept');
                $doc->endElement();
                
                $doc->startElement('skos:inScheme');
                    $doc->writeAttribute('rdf:resource', 'http://nomisma.org/symbol/');
                $doc->endElement();
                
                $doc->startElement('skos:prefLabel');
                    $doc->writeAttribute('xml:lang', 'en');
                    $doc->text($monogram['prefLabel']);
                $doc->endElement();
                
                $doc->startElement('skos:definition');
                    $doc->writeAttribute('xml:lang', 'en');
                    $doc->text($monogram['definition']);
                $doc->endElement();
                
                if (array_key_exists('letters', $monogram)) {
                    foreach ($monogram['letters'] as $letter) {
                        $doc->writeElement('crm:P106_is_composed_of', $letter);
                    }
                }                
                
                if (array_key_exists('matches', $monogram)){
                    foreach ($monogram['matches'] as $match) {
                        $doc->startElement('skos:exactMatch');
                            $doc->writeAttribute('rdf:resource', $match);
                        $doc->endElement();
                    }
                }
                
                if (array_key_exists('fon', $monogram)) {
                    foreach ($monogram['fon'] as $fon) {
                        $doc->startElement('dcterms:isPartOf');
                            $doc->writeAttribute('rdf:resource', $fon);
                        $doc->endElement();
                    }
                }
                
                
                foreach ($monogram['source'] as $source) {
                    $doc->startElement('dcterms:source');
                        $doc->writeAttribute('rdf:resource', $source);
                    $doc->endElement();
                }
                
                //image
                $doc->startElement('crm:P165i_is_incorporated_in');
                    $doc->startElement('crmdig:D1_Digital_Object');
                        $doc->writeAttribute('rdf:about', $monogram['image_url']);
                        $doc->writeElement('dcterms:format', 'image/svg+xml');
                        
                        $doc->startElement('dcterms:license');
                            $doc->writeAttribute('rdf:resource', 'https://creativecommons.org/choose/mark/');
                        $doc->endElement();
                        
                        if (array_key_exists('creator', $monogram)) {
                            $doc->startElement('dcterms:creator');
                                $doc->writeAttribute('rdf:resource', $monogram['creator']);
                            $doc->endElement();
                        }
                        
                    $doc->endElement();
                $doc->endElement();
                
                $doc->startElement('skos:changeNote');
                    $doc->writeAttribute('rdf:resource', $monogram['uri'] . '#provenance');
                $doc->endElement();
                
                //end nmo:Monogram
            $doc->endElement();
        
            //data provenance
            $doc->startElement('dcterms:ProvenanceStatement');
                $doc->writeAttribute('rdf:about', $monogram['uri'] . '#provenance');
                
                $doc->startElement('foaf:topic');
                    $doc->writeAttribute('rdf:resource', $monogram['uri']);
                $doc->endElement();
                
                //creation activity
                $doc->startElement('prov:wasGeneratedBy');
                    $doc->startElement('prov:Activity');
                    
                        $doc->startElement('rdf:type');
                            $doc->writeAttribute('rdf:resource', 'http://www.w3.org/ns/prov#Create');
                        $doc->endElement(); 
                        
                        $doc->startElement('prov:atTime');
                            $doc->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#dateTime');
                            $doc->text(date(DATE_W3C));
                        $doc->endElement();
                        
                        //add Nomisma editors
                        $doc->startElement('prov:wasAssociatedWith');
                            $doc->writeAttribute('rdf:resource', 'http://nomisma.org/editor/ktolle');
                        $doc->endElement();
                        $doc->startElement('prov:wasAssociatedWith');
                            $doc->writeAttribute('rdf:resource', 'http://nomisma.org/editor/upeter');
                        $doc->endElement();
                        $doc->startElement('prov:wasAssociatedWith');
                            $doc->writeAttribute('rdf:resource', 'http://nomisma.org/editor/pvalfen');
                        $doc->endElement();
                        $doc->startElement('prov:wasAssociatedWith');
                            $doc->writeAttribute('rdf:resource', 'http://nomisma.org/editor/egruber');
                        $doc->endElement();
                        
                        //description
                        $doc->startElement('dcterms:description');
                            $doc->writeAttribute('xml:lang', 'en');
                            $doc->text("Unique Greek monogram shapes were extracted by Computer Vision methods implemented by Karsten Tolle. Ulrike Peter and Peter van Alfen performed quality assurance and contributed to original metadata production. Ethan Gruber merged data derived from source material.");
                        $doc->endElement();
                        
                    $doc->endElement();
                $doc->endElement();
                
                //end dcterms:ProvenanceStatement
            $doc->endElement();
        
        //close rdf:RDF
        $doc->endElement();
        
        //close file
        $doc->endDocument();
        $doc->flush();
    }
}

function source_and_fon($row, $namespaces) {
    
    $values = array();
    
    if ($row['category'] == 'cn') {        
        $values['fon'][] = 'http://nomisma.org/id/greek_numismatics';
        $values['source'][] = 'http://nomisma.org/id/CN';
        $values['creator'] = 'http://nomisma.org/editor/vstolba';
    } elseif ($row['category'] == 'ocre') {
        $values['fon'][] = 'http://nomisma.org/id/roman_numismatics';
        $values['source'][] = 'http://nomisma.org/id/ric';
    } else {
        $image = trim($row['image']);
        
        if ($row['category'] == 'pco' && strpos($image, '_') !== FALSE) {
            $image = strtok($image, '_');
            $uri = $namespaces[$row['category']] . $image;
        } else {
            $uri = $namespaces[$row['category']] . $image;            
        }
        
        $values = processUri($uri);
        
    }
    
    return $values;
}

function processUri($uri) {
    echo "Querying {$uri}.\n";
    
    $values = array();
    
    $xmlDoc = new DOMDocument();
    $xmlDoc->load($uri . '.rdf');
    $xpath = new DOMXpath($xmlDoc);
    $xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
    $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    $xpath->registerNamespace('dcterms', 'http://purl.org/dc/terms/');
    
    $fons = $xpath->query("descendant::dcterms:isPartOf");
    $sources = $xpath->query("descendant::dcterms:source");
    
    foreach ($fons as $fon) {
        $values['fon'][] = $fon->getAttribute('rdf:resource');
    }
    
    foreach ($sources as $source) {
        $values['source'][] = $source->getAttribute('rdf:resource');
    }
    
    $creator = $xpath->query("descendant::dcterms:creator")->item(0);
    $values['creator'] = $creator->getAttribute('rdf:resource');
    
    return $values;
}


function number_pad($number,$n) {
    if ($number > 0){
        $gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
    } elseif ($number < 0) {
        $gYear = '-' . str_pad((int) abs($number),$n,"0",STR_PAD_LEFT);
    }
    return $gYear;
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