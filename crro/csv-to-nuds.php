<?php 
/*****
 * Author: Ethan Gruber
 * Date: May 2022
 * Function: Generate NUDS/XML for new version of RRC data stored on Google Sheets
 *****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vRxzNNLc4uLaPfNO60mNG4QJ09nWg0D4mosOCM-eQfbO4vqTj8skEE6zkJ7IbLdCrHVbWslehKDh5LN/pub?gid=1579772728&single=true&output=csv');
$o_stylesheet = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vRxzNNLc4uLaPfNO60mNG4QJ09nWg0D4mosOCM-eQfbO4vqTj8skEE6zkJ7IbLdCrHVbWslehKDh5LN/pub?gid=1411485313&single=true&output=csv');
$r_stylesheet = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vRxzNNLc4uLaPfNO60mNG4QJ09nWg0D4mosOCM-eQfbO4vqTj8skEE6zkJ7IbLdCrHVbWslehKDh5LN/pub?gid=1676912151&single=true&output=csv');




$nomismaUris = array();
//$errors = array();

$count = 1;

foreach($data as $row){
    generate_nuds($row, $count);    
    if (strlen($row['Parent ID']) == 0){
        $count++;
    }
}


/***** FUNCTIONS *****/
function generate_nuds($row, $count){
    GLOBAL $o_stylesheet;
    GLOBAL $r_stylesheet;
    
    $uri_space = 'http://numismatics.org/crro/id/';    
    $recordId = trim($row['ID']);
    
    if (strlen($recordId) > 0){
        echo "Processing {$recordId}\n";
        $doc = new XMLWriter();
        
        //$doc->openUri('php://output');
        $doc->openUri('nuds/' . $recordId . '.xml');
        $doc->setIndent(true);
        //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
        $doc->setIndentString("    ");
        
        $doc->startDocument('1.0','UTF-8');
        
        $doc->startElement('nuds');
            $doc->writeAttribute('xmlns', 'http://nomisma.org/nuds');
            $doc->writeAttribute('xmlns:xs', 'http://www.w3.org/2001/XMLSchema');
            $doc->writeAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
            $doc->writeAttribute('xmlns:tei', 'http://www.tei-c.org/ns/1.0');
            $doc->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $doc->writeAttribute('xsi:schemaLocation', 'http://nomisma.org/nuds http://nomisma.org/nuds.xsd');
            $doc->writeAttribute('recordType', 'conceptual');
            
            //control
            $doc->startElement('control');
                $doc->writeElement('recordId', $recordId);
                
                //hierarchy
                if (strlen($row['Parent ID']) > 0){
                    $doc->startElement('otherRecordId');
                        $doc->writeAttribute('semantic', 'skos:broader');
                        $doc->text(trim($row['Parent ID']));
                    $doc->endElement();
                    $doc->writeElement('publicationStatus', 'approvedSubtype');
                } else {
                    //insert a sortID
                    $doc->startElement('otherRecordId');
                        $doc->writeAttribute('localType', 'sortId');
                        $doc->text(number_pad(intval($count), 4));
                    $doc->endElement();                    
                    $doc->writeElement('publicationStatus', 'approved');
                }
                
                $doc->writeElement('maintenanceStatus', 'derived');
                $doc->startElement('maintenanceAgency');
                    $doc->writeElement('agencyName', 'American Numismatic Society');
                $doc->endElement();
                
                //maintenanceHistory
                $doc->startElement('maintenanceHistory');
                    //original publication date
                    $doc->startElement('maintenanceEvent');
                        $doc->writeElement('eventType', 'derived');
                        $doc->startElement('eventDateTime');
                            $doc->writeAttribute('standardDateTime', '2015-01-09T08:58:35-05:00');
                            $doc->text('Fri, 09 Jan 2015 08:58:35 -0500');
                        $doc->endElement();
                        $doc->writeElement('agentType', 'machine');
                        $doc->writeElement('agent', 'PHP');
                        $doc->writeElement('eventDescription', 'Generated from original RRC spreadsheet compiled by Eleanor Ghey and extracted from the British Museum catalog and supplemented by Rick Witschonke.');
                    $doc->endElement();
                    
                    //current process
                    $doc->startElement('maintenanceEvent');
                        $doc->writeElement('eventType', 'derived');
                        $doc->startElement('eventDateTime');
                            $doc->writeAttribute('standardDateTime', date(DATE_W3C));
                            $doc->text(date(DATE_RFC2822));
                        $doc->endElement();
                        $doc->writeElement('agentType', 'machine');
                        $doc->writeElement('agent', 'PHP');
                        $doc->writeElement('eventDescription', 'Generated from CSV from ANS Curatorial Google Drive.');
                    $doc->endElement();
                $doc->endElement();
                
                //rightsStmt
                $doc->startElement('rightsStmt');
                    $doc->startElement('license');
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:href', 'http://opendatacommons.org/licenses/odbl/');
                    $doc->endElement();
                $doc->endElement();
                
                //semanticDeclaration
                $doc->startElement('semanticDeclaration');
                    $doc->writeElement('prefix', 'dcterms');
                    $doc->writeElement('namespace', 'http://purl.org/dc/terms/');
                $doc->endElement();
                
                $doc->startElement('semanticDeclaration');
                    $doc->writeElement('prefix', 'nmo');
                    $doc->writeElement('namespace', 'http://nomisma.org/ontology#');
                $doc->endElement();
                
                $doc->startElement('semanticDeclaration');
                    $doc->writeElement('prefix', 'skos');
                    $doc->writeElement('namespace', 'http://www.w3.org/2004/02/skos/core#');
                $doc->endElement();
            
            //end control
            $doc->endElement();
            
            //start descMeta
            $doc->startElement('descMeta');
            
            //title
            $doc->startElement('title');
                $doc->writeAttribute('xml:lang', 'en');
                $doc->text(str_replace('.', '/', str_replace('rrc-', 'RRC ', $recordId)));
            $doc->endElement();
            
            //typeDesc
            $doc->startElement('typeDesc');
            //objectType
            if (strlen($row['objectType URI']) > 0){
                $vals = explode('|', $row['objectType URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            //sort dates
            if (strlen($row['Start Date']) > 0 || strlen($row['End Date']) > 0){
                if (($row['Start Date'] == $row['End Date']) || (strlen($row['Start Date']) > 0 && strlen($row['End Date']) == 0)){
                    if (is_numeric(trim($row['Start Date']))){
                        
                        $fromDate = intval(trim($row['Start Date']));
                            $doc->startElement('date');
                                $doc->writeAttribute('standardDate', number_pad($fromDate, 4));
                            $doc->text(get_date_textual($fromDate));
                        $doc->endElement();
                    }
                } else {
                    $fromDate = intval(trim($row['Start Date']));
                    $toDate= intval(trim($row['End Date']));
                    
                    //only write date if both are integers
                    if (is_int($fromDate) && is_int($toDate)){
                        $doc->startElement('dateRange');
                            $doc->startElement('fromDate');
                                $doc->writeAttribute('standardDate', number_pad($fromDate, 4));
                                $doc->text(get_date_textual($fromDate));
                            $doc->endElement();
                            $doc->startElement('toDate');
                                $doc->writeAttribute('standardDate', number_pad($toDate, 4));
                                $doc->text(get_date_textual($toDate));
                            $doc->endElement();
                        $doc->endElement();
                    }
                }
            }
            
            if (strlen($row['Denomination URI']) > 0){
                $vals = explode('|', $row['Denomination URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            if (strlen($row['Manufacture URI']) > 0){
                $vals = explode('|', $row['Manufacture URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'uncertain');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            if (strlen($row['Material URI']) > 0){
                $vals = explode('|', $row['Material URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'uncertain');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            //authority
            if (strlen($row['Issuer URI']) > 0){               
                $doc->startElement('authority');
                $vals = explode('|', $row['Issuer URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    $role = 'issuer';
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', $role);
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }                
                $doc->endElement();
            }
            
            //geography
            //mint
            if (strlen($row['Mint URI']) > 0 || strlen($row['Region URI']) > 0){
                $doc->startElement('geographic');
                if (strlen($row['Mint URI']) > 0){
                    $vals = explode('|', $row['Mint URI']);
                    foreach ($vals as $val){
                        if (substr($val, -1) == '?'){
                            $uri = substr($val, 0, -1);
                            $uncertainty = true;
                            $content = processUri($uri);
                        } else {
                            $uri =  $val;
                            $uncertainty = false;
                            $content = processUri($uri);
                        }
                        
                        $doc->startElement('geogname');
                            $doc->writeAttribute('xlink:type', 'simple');
                            $doc->writeAttribute('xlink:role', 'mint');
                            $doc->writeAttribute('xlink:href', $uri);
                            if($uncertainty == true){
                                $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                            }
                            $doc->text($content['label']);
                        $doc->endElement();
                    }
                }
                
                if (strlen($row['Region URI']) > 0){
                    $vals = explode('|', $row['Region URI']);
                    foreach ($vals as $val){
                        if (substr($val, -1) == '?'){
                            $uri = substr($val, 0, -1);
                            $uncertainty = true;
                            $content = processUri($uri);
                        } else {
                            $uri =  $val;
                            $uncertainty = false;
                            $content = processUri($uri);
                        }
                        
                        $doc->startElement('geogname');
                            $doc->writeAttribute('xlink:type', 'simple');
                            $doc->writeAttribute('xlink:role', 'region');
                            $doc->writeAttribute('xlink:href', $uri);
                            if($uncertainty == true){
                                $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                            }
                            $doc->text($content['label']);
                        $doc->endElement();
                    }
                }
                $doc->endElement();
            }
            
            //obverse
            if (strlen($row['O']) > 0){
                $key = trim($row['O']);
                $type = '';
                
                $doc->startElement('obverse');
                
                //legend
                if (strlen(trim($row['Obverse Legend'])) > 0){
                    $legend = trim($row['Obverse Legend']);
                    
                    $doc->startElement('legend');
                    
                    //evaluate legibility
                    if (strpos($legend, '<hi') !== FALSE){                        
                        $legend = str_replace('hi', 'tei:hi', $legend);
                        
                        $doc->startElement('tei:div');
                            $doc->writeAttribute('type', 'edition');
                            $doc->startElement('tei:ab');
                                $doc->writeRaw($legend);             
                            $doc->endElement();                                           
                        $doc->endElement();
                    } else {
                        $doc->text($legend);
                    }
                    
                    $doc->endElement();
                }
                
                //multilingual type descriptions
                $doc->startElement('type');
                foreach ($o_stylesheet as $desc){
                    if ($desc['code'] == $key){
                        foreach ($desc as $k=>$v){
                            if ($k != 'code'){
                                if (strlen($v) > 0){
                                    $doc->startElement('description');
                                        $doc->writeAttribute('xml:lang', $k);
                                        $doc->text(trim($v));
                                    $doc->endElement();
                                }
                            }
                        }
                        break;
                    }
                }
                $doc->endElement();
                
                foreach ($row as $k=>$v){
                    //reverse symbols are preceded with R:
                    if (substr($k, 0, 2) == 'O:'){
                        if (strlen(trim($v)) > 0){
                            $position = trim(str_replace('O:', '', $k));
                            $doc->startElement('symbol');
                                $doc->writeAttribute('position', $position);
                                $doc->text($v);
                                //parse the text in the symbol field into TEI fragments, if applicable
                                //parse_symbol($doc, trim($v));                            
                            $doc->endElement();
                        }
                    }
                }	
                
                //portrait
                if (strlen($row['Obverse Portrait URI']) > 0){
                    $vals = explode('|', $row['Obverse Portrait URI']);
                    foreach ($vals as $val){
                        if (substr($val, -1) == '?'){
                            $uri = substr($val, 0, -1);
                            $uncertainty = true;
                            $content = processUri($uri);
                        } else {
                            $uri =  $val;
                            $uncertainty = false;
                            $content = processUri($uri);
                        }
                        
                        if (array_key_exists('role', $content)){
                            $role = 'deity';
                        } else {
                            if ($content['element'] == 'corpname'){
                                //Dioscuri
                                $role = 'deity';
                            } else {
                                $role = 'portrait';
                            }
                            
                        }
                        
                        $doc->startElement($content['element']);
                            $doc->writeAttribute('xlink:type', 'simple');
                            $doc->writeAttribute('xlink:role', $role);
                            $doc->writeAttribute('xlink:href', $uri);
                            if($uncertainty == true){
                                $doc->writeAttribute('certainty', 'uncertain');
                            }
                            $doc->text($content['label']);
                        $doc->endElement();
                    }
                }
                
                //end obverse
                $doc->endElement();
            }
            
            //reverse
            if (strlen($row['R']) > 0){
                $key = trim($row['R']);
                
                $doc->startElement('reverse');
                
                //legend
                if (strlen(trim($row['Reverse Legend'])) > 0){
                    $legend = trim($row['Reverse Legend']);
                    
                    $doc->startElement('legend');
                    
                    //evaluate legibility
                    if (strpos($legend, '<hi') !== FALSE){
                        $legend = str_replace('hi', 'tei:hi', $legend);  
                        
                        $doc->startElement('tei:div');
                            $doc->writeAttribute('type', 'edition');
                            $doc->startElement('tei:ab');
                                $doc->writeRaw($legend);
                            $doc->endElement(); 
                        $doc->endElement();
                    } else {
                        $doc->text($legend);
                    }
                    
                    $doc->endElement();
                }
                
                //multilingual type descriptions
                $doc->startElement('type');
                foreach ($r_stylesheet as $desc){
                    if ($desc['code'] == $key){
                        foreach ($desc as $k=>$v){
                            if ($k != 'code'){
                                if (strlen($v) > 0){
                                    $doc->startElement('description');
                                        $doc->writeAttribute('xml:lang', $k);
                                        $doc->text(trim($v));
                                    $doc->endElement();
                                }
                            }
                        }
                        break;
                    }
                }
                $doc->endElement();
                
                foreach ($row as $k=>$v){
                    //reverse symbols are preceded with R:
                    if (substr($k, 0, 2) == 'R:'){
                        if (strlen(trim($v)) > 0){
                            $position = trim(str_replace('R:', '', $k));
                            $doc->startElement('symbol');
                                $doc->writeAttribute('position', $position);
                                $doc->text($v);
                                //parse the text in the symbol field into TEI fragments, if applicable
                                //parse_symbol($doc, trim($v));
                            $doc->endElement();
                        }
                    }
                }
                
                //portrait
                if (strlen($row['Reverse Portrait URI']) > 0){
                    $vals = explode('|', $row['Reverse Portrait URI']);
                    foreach ($vals as $val){
                        if (substr($val, -1) == '?'){
                            $uri = substr($val, 0, -1);
                            $uncertainty = true;
                            $content = processUri($uri);
                        } else {
                            $uri =  $val;
                            $uncertainty = false;
                            $content = processUri($uri);
                        }
                        
                        if (array_key_exists('role', $content)){
                            $role = 'deity';
                        } else {
                            if ($content['element'] == 'corpname'){
                                //Dioscuri
                                $role = 'deity';
                            } else {
                                $role = 'portrait';
                            }
                            
                        }
                        
                        $doc->startElement($content['element']);
                            $doc->writeAttribute('xlink:type', 'simple');
                            $doc->writeAttribute('xlink:role', $role);
                            $doc->writeAttribute('xlink:href', $uri);
                            if($uncertainty == true){
                                $doc->writeAttribute('certainty', 'uncertain');
                            }
                            $doc->text($content['label']);
                        $doc->endElement();
                    }
                }
                
                //end reverse
                $doc->endElement();
            }
            
            //end typeDesc
            $doc->endElement();
           
            //end descMeta
            $doc->endElement();
            
        //end NUDS
        $doc->endElement();
        //close file
        $doc->endDocument();
        $doc->flush();
    }
}

function processUri($uri){
    GLOBAL $nomismaUris;
    $content = array();
    $uri = trim($uri);
    $type = '';
    $label = '';
    $node = '';
    
    //if the key exists, then formulate the XML response
    if (array_key_exists($uri, $nomismaUris)){
        $type = $nomismaUris[$uri]['type'];
        $label = $nomismaUris[$uri]['label'];
        if (isset($nomismaUris[$uri]['parent'])){
            $parent = $nomismaUris[$uri]['parent'];
        }
    } else {
        //if the key does not exist, look the URI up in Nomisma
        $pieces = explode('/', $uri);
        $id = $pieces[4];
        if (strlen($id) > 0){
            $uri = 'http://nomisma.org/id/' . $id;
            $file_headers = @get_headers($uri);
            
            //only get RDF if the ID exists
            if (strpos($file_headers[0], '200') !== FALSE){
                $xmlDoc = new DOMDocument();
                $xmlDoc->load('http://nomisma.org/id/' . $id . '.rdf');
                $xpath = new DOMXpath($xmlDoc);
                $xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
                $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
                $type = $xpath->query("/rdf:RDF/*")->item(0)->nodeName;
                $label = $xpath->query("descendant::skos:prefLabel[@xml:lang='en']")->item(0)->nodeValue;
                
                if (!isset($label)){
                    echo "Error with {$id}\n";
                }
                
                //get the parent, if applicable
                $parents = $xpath->query("descendant::org:organization");
                if ($parents->length > 0){
                    $nomismaUris[$uri] = array('label'=>$label,'type'=>$type, 'parent'=>$parents->item(0)->getAttribute('rdf:resource'));
                    $parent = $parents->item(0)->getAttribute('rdf:resource');
                } else {
                    $nomismaUris[$uri] = array('label'=>$label,'type'=>$type);
                }
            } else {
                //otherwise output the error
                echo "Error: {$uri} not found.\n";
                $nomismaUris[$uri] = array('label'=>$uri,'type'=>'nmo:Mint');
            }
        }
    }
    switch($type){
        case 'nmo:Mint':
        case 'nmo:Region':
            $content['element'] = 'geogname';
            $content['label'] = $label;
            if (isset($parent)){
                $content['parent'] = $parent;
            }
            break;
        case 'nmo:Material':
            $content['element'] = 'material';
            $content['label'] = $label;
            break;
        case 'nmo:Denomination':
            $content['element'] = 'denomination';
            $content['label'] = $label;
            break;
        case 'nmo:Manufacture':
            $content['element'] = 'manufacture';
            $content['label'] = $label;
            break;
        case 'nmo:ObjectType':
            $content['element'] = 'objectType';
            $content['label'] = $label;
            break;
        case 'rdac:Family':
            $content['element'] = 'famname';
            $content['label'] = $label;
            break;
        case 'foaf:Organization':
        case 'foaf:Group':
        case 'nmo:Ethnic':
            $content['element'] = 'corpname';
            $content['label'] = $label;
            break;
        case 'foaf:Person':
            $content['element'] = 'persname';
            $content['label'] = $label;
            if (isset($parent)){
                $content['parent'] = $parent;
            }
            break;
        case 'wordnet:Deity':
            $content['element'] = 'persname';
            $content['label'] = $label;
            $content['role'] = 'deity';
            break;
        case 'crm:E4_Period':
            $content['element'] = 'periodname';
            $content['label'] = $label;
            break;
        default:
            $content['element'] = 'ERR';
            $content['label'] = $label;
    }
    return $content;
}

function get_date_textual($year){
    $textual_date = '';
    //display start date
    if($year < 0){
        $textual_date .= abs($year) . ' BC';
    } elseif ($year > 0) {
        if ($year <= 600){
            $textual_date .= 'AD ';
        }
        $textual_date .= $year;
    }
    return $textual_date;
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
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