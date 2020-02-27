<?php 

/*****
 * Author: Ethan Gruber
 * Last modified: February 2020
 * Function: Process a Google Spreadsheet of biographical metadata into skeleton EAC-CPF records for publication of
 * new authorities into xEAC for IGCH archival records.
 *****/

ini_set("allow_url_fopen", 1);

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vRTGuolaKr3petLr16lTbFzam4ysSr5GT2AWvIZZpj4gaCn9h5GjTifD6_b7Lt2CMp_w8w82J3hWaHG/pub?output=csv');

$count = 1;
foreach($data as $row){
    
    
    if ($row['public'] != 'no'){
        generate_eac($row, $count);
    }
}

function generate_eac($row, $count){
    $recordId = $row['ID'];
    $entities = array();
    
    $doc = new XMLWriter();
    
    //$doc->openUri('php://output');
    $doc->openUri('eac/' . $recordId . '.xml');
    $doc->setIndent(true);
    //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
    $doc->setIndentString("    ");
    
    $doc->startDocument('1.0','UTF-8');
    
    $doc->startElement('eac-cpf');
        $doc->writeAttribute('xmlns', 'urn:isbn:1-931666-33-4');
        $doc->writeAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        
        $doc->startElement('control');
            $doc->writeElement('recordId', $recordId);
            $doc->writeElement('maintenanceStatus', 'new');
            $doc->writeElement('publicationStatus', 'approved');
            $doc->startElement('maintenanceAgency');
                $doc->writeElement('agencyName', 'American Numismatic Society');
                $doc->writeElement('agencyCode', 'US-nnan');
            $doc->endElement();
            $doc->startElement('maintenanceHistory');
                $doc->startElement('maintenanceEvent');
                    $doc->writeElement('eventType', 'derived');
                    $doc->startElement('eventDateTime');
                        $doc->writeAttribute('standardDateTime', date(DATE_W3C));
                        $doc->text(date(DATE_RFC2822));
                    $doc->endElement();
                    $doc->writeElement('agentType', 'human');
                    $doc->writeElement('agent', 'Ethan Gruber');
                    $doc->writeElement('eventDescription', 'Generated from CSV ' . 
                        '(https://docs.google.com/spreadsheets/d/e/2PACX-1vRTGuolaKr3petLr16lTbFzam4ysSr5GT2AWvIZZpj4gaCn9h5GjTifD6_b7Lt2CMp_w8w82J3hWaHG/pubhtml) ' . 
                        'from Google Drive for IGCH records.');
                $doc->endElement();
            $doc->endElement();
            //end maintenance history
            $doc->startElement('conventionDeclaration');
                $doc->writeElement('abbreviation', 'ANS');
                $doc->writeElement('citation', 'American Numismatic Society');
            $doc->endElement();
            
            //semanticDeclaration
            $doc->startElement('localTypeDeclaration');
                $doc->writeElement('abbreviation', 'dcterms');
                $doc->startElement('citation');
                    $doc->writeAttribute('xlink:role', 'semantic');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', 'http://purl.org/dc/terms/');
                    $doc->text('http://purl.org/dc/terms/');
                $doc->endElement();
            $doc->endElement();
            $doc->startElement('localTypeDeclaration');
                $doc->writeElement('abbreviation', 'foaf');
                   $doc->startElement('citation');
                    $doc->writeAttribute('xlink:role', 'semantic');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', 'http://xmlns.com/foaf/0.1/');
                    $doc->text('http://xmlns.com/foaf/0.1/');
                $doc->endElement();
            $doc->endElement();
            $doc->startElement('localTypeDeclaration');
                $doc->writeElement('abbreviation', 'org');
                $doc->startElement('citation');
                    $doc->writeAttribute('xlink:role', 'semantic');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', 'http://www.w3.org/ns/org#');
                    $doc->text('http://www.w3.org/ns/org#');
                $doc->endElement();
            $doc->endElement();
            $doc->startElement('localTypeDeclaration');
                $doc->writeElement('abbreviation', 'rel');
                $doc->startElement('citation');
                    $doc->writeAttribute('xlink:role', 'semantic');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', 'http://purl.org/vocab/relationship/');
                    $doc->text('http://purl.org/vocab/relationship/');
                $doc->endElement();
            $doc->endElement();
            $doc->startElement('localTypeDeclaration');
                $doc->writeElement('abbreviation', 'skos');
                $doc->startElement('citation');
                    $doc->writeAttribute('xlink:role', 'semantic');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', 'http://www.w3.org/2004/02/skos/core#');
                    $doc->text('http://www.w3.org/2004/02/skos/core#');
                $doc->endElement();
            $doc->endElement();
            $doc->startElement('localTypeDeclaration');
                $doc->writeElement('abbreviation', 'foaf');
                $doc->startElement('citation');
                    $doc->writeAttribute('xlink:role', 'semantic');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', 'https://github.com/ewg118/xEAC#');
                    $doc->text('https://github.com/ewg118/xEAC#');
                $doc->endElement();
            $doc->endElement();            
        $doc->endElement();
        //end control
        
        //begin cpfDescription
        $doc->startElement('cpfDescription');
            //identity
            $doc->startElement('identity');
                $doc->writeElement('entityType', $row['type']);
                $doc->startElement('nameEntry');
                    $doc->writeElement('part', str_replace('&', '&amp;', $row['Preferred Label']));
                    $doc->writeElement('preferredForm', 'ANS');
                $doc->endElement();
                
                //process entityIDs
                if (strlen($row['SNAC URI']) > 0){
                    $entities[] = $row['SNAC URI'];
                    
                    //call SNAC API
                    $matches = read_snac($row['SNAC URI']);
                    foreach ($matches as $match){
                        $entities[] = $match;
                    }
                    
                }
                
                if (strlen($row['VIAF URI']) > 0){
                    $entities[] = $row['VIAF URI'];
                    
                    //call VIAF API
                    $matches = read_viaf($row['VIAF URI']);
                    foreach ($matches as $match){
                        $entities[] = $match;
                    }
                }
                
                if (strlen($row['Wikidata URI']) > 0){
                    $entities[] = $row['Wikidata URI'];
                    
                    $matches = read_wikidata($row['Wikidata URI']);
                    foreach ($matches as $match){
                        $entities[] = $match;
                    }
                }
                
                foreach (array_unique($entities) as $uri){
                    $doc->startElement('entityId');
                        $doc->writeAttribute('localType', 'skos:exactMatch');
                        $doc->text($uri);
                    $doc->endElement();
                }                
                
            
            $doc->endElement();
            
            //description
            $doc->startElement('description');
            
            //existDates
            if (strlen($row['Birth']) > 0 || strlen($row['Death']) > 0){
                $doc->startElement('existDates');
                    $doc->writeElement('localType', 'xeac:life');
                    $doc->startElement('dateRange');
                        if (strlen($row['Birth']) > 0){
                            $doc->startElement('fromDate');
                                $doc->writeAttribute('standardDate', $row['Birth']);
                                //if the year is numeric (just a year)
                                if (is_numeric($row['Birth'])){
                                    $doc->text($row['Birth']);
                                } else {
                                    $doc->text(safe_strtotime($row['Birth']));
                                }                            
                            $doc->endElement();
                        }
                        
                        if (strlen($row['Death']) > 0){
                            $doc->startElement('toDate');
                                $doc->writeAttribute('standardDate', $row['Death']);
                                //if the year is numeric (just a year)
                                if (is_numeric($row['Death'])){
                                    $doc->text($row['Death']);
                                } else {
                                    $doc->text(safe_strtotime($row['Death']));
                                }   
                            $doc->endElement();
                        }                        
                    $doc->endElement();
                $doc->endElement();
            }
            
            $doc->startElement('biogHist');
                $doc->writeElement('abstract', $row['Bio']);
                //$doc->writeElement('p', $row['Bio']);                
            $doc->endElement();
            
            //occupations
            if (strlen($row['Occupation 1 Label']) > 0 && strlen($row['Occupation 1 URI']) > 0){
                $doc->startElement('occupation');
                    $doc->startElement('term');
                        $doc->writeAttribute('vocabularySource', $row['Occupation 1 URI']);
                        $doc->text($row['Occupation 1 Label']);
                    $doc->endElement();
                $doc->endElement();
            }
            if (strlen($row['Occupation 2 Label']) > 0 && strlen($row['Occupation 2 URI']) > 0){
                $doc->startElement('occupation');
                    $doc->startElement('term');
                        $doc->writeAttribute('vocabularySource', $row['Occupation 2 URI']);
                        $doc->text($row['Occupation 2 Label']);
                    $doc->endElement();
                $doc->endElement();
            }
            if (strlen($row['Occupation 3 Label']) > 0 && strlen($row['Occupation 3 URI']) > 0){
                $doc->startElement('occupation');
                    $doc->startElement('term');
                        $doc->writeAttribute('vocabularySource', $row['Occupation 3 URI']);
                        $doc->text($row['Occupation 3 Label']);
                    $doc->endElement();
                $doc->endElement();
            }
            //end desctription
            $doc->endElement();
            
            //relations (reprocess later?)
        $doc->endElement();
    $doc->endElement();
    //end eac-cpf
    
    //close file
    $doc->endDocument();
    $doc->flush();
    
    echo "Wrote {$recordId}\n";
}

//read justlinks JSON from VIAF
function read_viaf($uri){
    $matches = array();
    $url = str_replace('http://', 'https://', $uri) . '/justlinks.json';
    
    $result = file_get_contents($url);
    
    $json = json_decode($result);
    
    foreach ($json as $k=>$v){
        switch($k){
            case 'BNF':
                $matches[] = $v[0];
                break;
            case 'DNB':
                $matches[] = $v[0];
                break;
            case 'ISNI':
                $matches[] = 'http://isni.org/' . $v[0];
                break;
            case 'LC':
                $matches[] = 'http://id.loc.gov/authorities/names/' . $v[0];
                break;
            case 'JPG':
                $matches[] = 'http://vocab.getty.edu/ulan/' . $v[0];
        }
    }
    
    return $matches;
}

function read_wikidata($uri){
    $matches = array();
    
    $pieces = explode('/', $uri);
    
    $url = "https://www.wikidata.org/w/api.php?action=wbgetclaims&entity={$pieces[4]}&format=json";
    $result = file_get_contents($url);
    $json = json_decode($result);
    
    foreach ($json->claims as $prop=>$array){
        switch ($prop){
            case 'P213':
                $matches[] = 'http://isni.org/' . str_replace(' ', '', $array[0]->mainsnak->datavalue->value);
                break;
            case 'P214':
                $matches[] = 'http://viaf.org/viaf/' . $array[0]->mainsnak->datavalue->value;
                break;
            case 'P227':
                $matches[] = 'http://d-nb.info/gnd/' . $array[0]->mainsnak->datavalue->value;
                break;
            case 'P245':
                $matches[] = 'http://vocab.getty.edu/ulan/' . $array[0]->mainsnak->datavalue->value;
                break;
            case 'P244':
                $matches[] = 'http://id.loc.gov/authorities/names/' . $array[0]->mainsnak->datavalue->value;
                break;
            case 'P268':
                $matches[] = 'http://catalogue.bnf.fr/ark:/12148/cb' . $array[0]->mainsnak->datavalue->value;
                break;
            case 'P269':
                $matches[] = 'http://www.idref.fr/' . $array[0]->mainsnak->datavalue->value;
                break;
            case 'P1225':
                $matches[] = 'https://catalog.archives.gov/id/' . $array[0]->mainsnak->datavalue->value;
                break;
            case 'P3430':
                $matches[] = 'http://n2t.net/ark:/99166/' . $array[0]->mainsnak->datavalue->value;
                break;
        }
    }
    return $matches;
}

//read JSON from SNAC API
function read_snac($uri){
    $matches = array();
    
    $ch = curl_init('http://api.snaccooperative.org/');
    # Setup request to send json via POST.
    $payload = json_encode(array('command'=>'read', 'arkid'=>$uri));
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    # Return response instead of printing.
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    # Send request.
    $result = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($result);
    
    if (property_exists($json->constellation, 'sameAsRelations')){
        foreach ($json->constellation->sameAsRelations as $rel){
            $match = $rel->uri;
            
            if (strpos($match, 'wikipedia') === FALSE){
                $matches[] = $match;
            }
        }
    }    
    
    return $matches;
}

//handle xsd:date before 1970 (https://stackoverflow.com/questions/33581012/create-date-object-in-php-for-dates-before-1970-in-certain-format)
function safe_strtotime($string)
{
    if(!preg_match("/\d{4}/", $string, $match)) return null; //year must be in YYYY form
    $year = intval($match[0]);//converting the year to integer
    if($year >= 1970) return date("Y-m-d", strtotime($string));//the year is after 1970 - no problems even for Windows
    if(stristr(PHP_OS, "WIN") && !stristr(PHP_OS, "DARWIN")) //OS seems to be Windows, not Unix nor Mac
    {
        $diff = 1975 - $year;//calculating the difference between 1975 and the year
        $new_year = $year + $diff;//year + diff = new_year will be for sure > 1970
        $new_date = date("Y-m-d", strtotime(str_replace($year, $new_year, $string)));//replacing the year with the new_year, try strtotime, rendering the date
        return str_replace($new_year, $year, $new_date);//returning the date with the correct year
    }
    return date("F j, Y", strtotime($string));//do normal strtotime
}

//CSV processing functions
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