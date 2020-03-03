<?php 

/*****
 * Author: Ethan Gruber
 * Last modified: February 2020
 * Function: Process a Google Spreadsheet of IGCH Archival Records into TEI files of facsimile images, similar to the Newell notebooks
 *****/

ini_set("allow_url_fopen", 1);

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTRQbc1C_jdWLdV7jehdctKs7_BJG1TrOEGllRQhge-bXkBIeyEVEpS0cS-4vjmAsmqlYsOvaFzWH5J/pub?output=csv');
$people = generate_json('xeac-people.csv');

$objects = array();

foreach($data as $row){    
    //generate object from distinct IDs
    if ($row['Public'] != 'no' && strlen($row['Archival Record ID']) > 0){
        $objects[$row['Archival Record ID']] = generate_object($data, $row);
    }
}

//var_dump($objects);

//iterate through each data object and generate a TEI file
foreach ($objects as $id=>$array){
    generate_tei($id, $array);
}

/***** FUNCTIONS *****/
function generate_tei ($id, $array){
        
    //generate a human-readable title from file metadata
    $title = generate_title($array['hoard'], $array['files']);
    
    $types = array();
    $authors = array();
    $agents = array();
    $dates = array();
    
    foreach($array['files'] as $file){
        
        if ($file['public'] == TRUE){
            if (array_key_exists('type', $file)){
                if (strlen($file['type']) > 0 && !in_array($file['type'], $types)){
                    $types[] = $file['type'];
                }
            }
            if (array_key_exists('author', $file)){
                if (strlen($file['author']) > 0 && !in_array($file['author'], $authors)){
                    $authors[] = $file['author'];
                    
                    if (!in_array($file['author'], $agents)){
                        $agents[] = $file['author'];
                    }
                    
                }
            }            
            if (array_key_exists('recipient', $file)){
                if (strlen($file['recipient']) > 0 && !in_array($file['recipient'], $agents)){
                    $agents[] = $file['recipient'];
                }
            }
            if (array_key_exists('otherPeople', $file)){                
                foreach($file['otherPeople'] as $person){
                    if (strlen($person) > 0 && !in_array($person, $agents)){                        
                        $agents[] = $person;
                    }
                }
            }
            if (array_key_exists('date', $file)){
                if (strlen($file['date']) > 0 && !in_array($file['date'], $dates)){
                    $dates[] = $file['date'];
                }
            }
        }
    }
    
    $doc = new XMLWriter();
    
    //$doc->openUri('php://output');
    $doc->openUri('tei/' . $id . '.xml');
    $doc->setIndent(true);
    //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
    $doc->setIndentString("    ");
    
    $doc->startDocument('1.0','UTF-8');
    
    //start TEI file
    $doc->startElement('TEI');
        $doc->writeAttribute('xmlns', 'http://www.tei-c.org/ns/1.0');
        $doc->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $doc->writeAttribute('xsi:schemaLocation', 'http://www.tei-c.org/ns/1.0 http://www.tei-c.org/release/xml/tei/custom/schema/xsd/tei_all.xsd');
        $doc->writeAttribute('xml:id', $id);
        
        //TEI Header
        $doc->startElement('teiHeader');
            $doc->startElement('fileDesc');
                $doc->startElement('titleStmt');
                    $doc->writeElement('title', $title);
                $doc->endElement();
                
                foreach ($authors as $author){
                    $element = ($author == 'http://numismatics.org/authority/p_norrit_co' ? 'corpName' : 'persName');
                    
                    $doc->startElement('author');
                        $doc->startElement($element);
                            $doc->writeAttribute('ref', $author);
                            $doc->text(normalize_uri($author));
                        $doc->endElement();
                    $doc->endElement();
                }
                
                $doc->startElement('publicationStmt');
                    $doc->startElement('publisher');
                        $doc->writeElement('name', 'American Numismatic Society');
                        $doc->startElement('idno');
                            $doc->writeAttribute('type', 'URI');
                            $doc->text('http://numismatics.org/authority/american_numismatic_society');
                        $doc->endElement();
                    $doc->endElement();
                    $doc->writeElement('pubPlace', 'New York (N.Y.)');
                $doc->endElement();
                
                $doc->startElement('sourceDesc');                    
                    //conditional for generating a paragraph or more complex manuscript metadata
                    if (count($types) == 1 && ($types[0] == 'Correspondence' || $types[0] == 'Notes')){
                        $doc->startElement('msDesc');
                            $doc->startElement('msIdentifier');
                                $doc->writeElement('idno', $id);
                            $doc->endElement();
                            
                            if (count($authors) > 0 || count($dates) > 0){
                                $doc->startElement('msContents');
                                if (count($authors) > 0){
                                    $doc->startElement('msItemStruct');
                                    foreach ($authors as $author){
                                        $element = ($author == 'http://numismatics.org/authority/p_norrit_co' ? 'corpName' : 'persName');
                                        
                                        $doc->startElement('author');
                                            $doc->startElement($element);
                                                $doc->writeAttribute('ref', $author);
                                                $doc->text(normalize_uri($author));
                                            $doc->endElement();
                                        $doc->endElement();
                                    }
                                    $doc->endElement();
                                }
                                
                                if (count($dates) > 0){
                                    $doc->startElement('history');
                                        $doc->startElement('origin');
                                            $doc->startElement('date');
                                            
                                            //evaluate single date vs date range
                                            if (count($dates) == 1){
                                                $doc->writeAttribute('when', $dates[0]);
                                                $doc->text(safe_strtotime($dates[0]));
                                            } elseif (count($dates) > 1){
                                                asort($dates);
                                                $doc->writeAttribute('from', $dates[0]);
                                                $doc->writeAttribute('to', $dates[count($dates)-1]);
                                                $doc->text(safe_strtotime($dates[0]) . ' - ' . safe_strtotime($dates[count($dates)-1]));
                                            }
                                            
                                            $doc->endElement();                                            
                                        $doc->endElement();
                                    $doc->endElement();
                                }                                
                                $doc->endElement();    
                            }
                                                      
                        $doc->endElement();
                    } else {
                        $doc->writeElement('p', 'Archival record from the Inventory of Greek Coin Hoards and Coin Hoards folders in the American Numismatic Society.');
                    }                      
                //end sourceDesc
                $doc->endElement();
            //end fileDesc
            $doc->endElement();
            
            //profileDesc (general document metadata)
            $doc->startElement('profileDesc');
                $doc->startElement('langUsage');
                    $doc->startElement('language');
                        $doc->writeAttribute('ident', 'en');
                        $doc->text('English');
                    $doc->endElement();
                $doc->endElement();
                
                $doc->startElement('textClass');
                    //parse types in AAT
                    foreach ($types as $type){
                        $doc->startElement('classCode');
                        $doc->writeAttribute('scheme', 'http://vocab.getty.edu/aat/');
                        switch ($type){
                            case 'Correspondence':
                                $doc->text('300026877');                               
                                break;
                            case 'Hoard Photographs':
                                $doc->text('300046300');
                                break;
                            case 'Notes':
                                $doc->text('300265639');
                                break;
                            case 'Invoice':
                                $doc->text('300027568');
                                break;
                        }
                        $doc->endElement();
                    }
                    
                    //subjects
                    $doc->startElement('keywords');
                        $doc->writeAttribute('scheme', 'nmo:Hoard');
                        $doc->startElement('term');
                            $doc->writeAttribute('ref', $array['hoard']);
                            $doc->text(normalize_hoard($array['hoard']));                        
                        $doc->endElement();
                    $doc->endElement();
                    
                    if (count($agents) > 0){
                        $doc->startElement('particDesc');
                        if (in_array('http://numismatics.org/authority/p_norrit_co', $agents) || in_array('http://numismatics.org/authority/american_numismatic_society', $agents)){
                            $doc->startElement('listOrg');
                            foreach($agents as $agent){
                                $doc->startElement('org');
                                    $doc->startElement('orgName');
                                        $doc->writeAttribute('ref', $agent);
                                        $doc->text(normalize_uri($agent));
                                    $doc->endElement();
                                $doc->endElement();
                            }
                            $doc->endElement();
                        } else {
                            $doc->startElement('listPerson');
                            foreach($agents as $agent){
                                $doc->startElement('person');
                                    $doc->startElement('persName');
                                        $doc->writeAttribute('ref', $agent);
                                        $doc->text(normalize_uri($agent));
                                    $doc->endElement();
                                $doc->endElement();
                            }
                            $doc->endElement();
                        }
                        $doc->endElement();
                    }
                
                $doc->endElement();
            //end profileDesc
            $doc->endElement();
            
            $doc->startElement('revisionDesc');
                $doc->startElement('change');
                $doc->writeAttribute('when', substr(date(DATE_W3C), 0, 10));
                    $doc->text('Generated TEI file from Google spreadsheet of IGCH archival records ' .
                        '(https://docs.google.com/spreadsheets/d/e/2PACX-1vTRQbc1C_jdWLdV7jehdctKs7_BJG1TrOEGllRQhge-bXkBIeyEVEpS0cS-4vjmAsmqlYsOvaFzWH5J/pubhtml).');
                $doc->endElement();
            $doc->endElement();
            
        //end TEI Header
        $doc->endElement();
        
        //create facsimilies
        foreach ($array['files'] as $file){
            if ($file['public'] == TRUE){
                $filename = str_replace('.tif', '', $file['filename']);
                
                //get the dimensions of the image
                $path = "/e/IGCH Archives/archive/{$filename}.jpg";
                
                if (file_exists($path)) {
                    $size = getimagesize($path);
                    
                    $width = $size[0];
                    $height = $size[1];
                    
                    $doc->startElement('facsimile');
                        $doc->writeAttribute('xml:id', $filename);
                        $doc->writeAttribute('style', 'depiction');
                        
                        $doc->startElement('media');
                            $doc->writeAttribute('url', "http://images.numismatics.org/archivesimages%2Farchive%2F{$filename}.jpg");
                            if (array_key_exists('type', $file)){
                                $doc->writeAttribute('n', $file['type']);
                            }
                            
                            $doc->writeAttribute('mimeType', 'image/jpeg');
                            $doc->writeAttribute('type', 'IIIFService');
                            $doc->writeAttribute('height', "{$height}px");
                            $doc->writeAttribute('width', "{$width}px");
                        $doc->endElement();
                    $doc->endElement();
                } else {
                    echo "File {$filename} not found\n";
                } 
            }            
        }    
        
    //end TEI file
    $doc->endElement();
    
    //close file
    $doc->endDocument();
    $doc->flush();
    
    echo "Wrote {$id}\n";
}

//parse metadata pieces in order to generate a standardized human-readable title
function generate_title($uri, $files){
    $types = array();
    $authors = array();
    $recipients = array();
    $dates = array();
    
    foreach($files as $file){
        
        if ($file['public'] == TRUE){
            if (array_key_exists('type', $file)){
                if (strlen($file['type']) > 0 && !in_array($file['type'], $types)){
                    $types[] = $file['type'];
                }
            }
            if (array_key_exists('author', $file)){
                if (strlen($file['author']) > 0 && !in_array($file['author'], $authors)){
                    $authors[] = $file['author'];
                }
            }
            if (array_key_exists('recipient', $file)){
                if (strlen($file['recipient']) > 0 && !in_array($file['recipient'], $recipients)){
                    $recipients[] = $file['recipient'];
                }
            }
            if (array_key_exists('date', $file)){
                if (strlen($file['date']) > 0 && !in_array($file['date'], $dates)){
                    $dates[] = $file['date'];
                }
            }
        }
    }
    
    $title = (count($types) == 0 ? 'Misc.' : implode('/', $types));
    
    if (count($authors) > 0 || count($recipients) > 0){
        if (count($authors) >= 1 || count($recipients) >= 1){
            $title .= ' of ';
        }
        
        if (count($authors) >= 1){
            $names = array();
            foreach ($authors as $a){
                $names[] = normalize_uri_commas($a);
            }
            
            $title .= implode('/', $names);
            
            unset($names);
        }
        //sep
        if (count($authors) >= 1 && count($recipients) >= 1){
            $title .= ' and ';
        }
        
        if (count($recipients) >= 1) {
            $names = array();
            foreach ($recipients as $r){
                $names[] = normalize_uri_commas($r);
            }
            
            $title .= implode('/', $names);
            
            unset($names);
        }
    }
    
    $title .= ' about ';
    
    //parse IGCH URI
    $title .= normalize_hoard($uri);
    
    if (count($dates) == 1){
        $title .= " (" . safe_strtotime($dates[0]) . ")";
    }
    
    return $title;
}

function normalize_hoard($uri){
    switch($uri){
        case (strpos($uri, 'igch') !== FALSE):
            $num = preg_replace('/^http:\/\/.*\/igch([0-9]{4})$/', '$1', $uri);
            return 'IGCH ' . ltrim($num, '0');
        case (strpos($uri, 'ch.') !== FALSE):
            $pieces = explode('.', str_replace('http://coinhoards.org/id/', '', $uri));
            switch($pieces[1]){
                case ('1'):
                    $vol = 'I';
                    break;
                case ('2'):
                    $vol = 'II';
                    break;
                case ('3'):
                    $vol = 'III';
                    break;
                case ('4'):
                    $vol = 'IV';
                    break;
                case ('5'):
                    $vol = 'V';
                    break;
                case ('6'):
                    $vol = 'VI';
                    break;
            }
            
            return "Coin Hoards {$vol} {$pieces[2]}";
    }
}

function normalize_uri_commas ($uri){
    GLOBAL $people;
    
    foreach ($people as $row){
        if ($row['uri'] == $uri){
            if (strpos($row['name'], ',') !== FALSE){
                $pieces = explode(',', $row['name']);
                return $pieces[0];
            } else {
                return $row['name'];
            }
        }
    }
}

function normalize_uri ($uri){
    GLOBAL $people;
    
    foreach ($people as $row){
        if ($row['uri'] == $uri){
            return $row['name'];
        }
    }
}

function generate_object($data, $row){
    $object = array();
    
    $id = $row['Archival Record ID'];
    
    $object['id'] = $id;
    $object['hoard'] = "http://coinhoards.org/id/" . $row['Coin Hoard ID'];    
    
    //re-iterate through each row looking for tif files that match the ID pattern
    foreach ($data as $page){
        if (strpos($page['File Name'], $id) !== FALSE){
            $file = array();
            $file['filename'] = $page['File Name'];
            
            if (strlen($page['Document Type']) > 0){
                $file['type'] = $page['Document Type'];
            }
            if (strlen($page['Date (yyyy-mm-dd)']) > 0){
                $file['date'] = $page['Date (yyyy-mm-dd)'];
            }
            if (strlen($page['Document Author']) > 0){
                $file['author'] = $page['Document Author'];
            }
            if (strlen($page['Document Recipient']) > 0){
                $file['recipient'] = $page['Document Recipient'];
            }
            if (strlen($page['Other Person 1']) > 0){
                $file['otherPeople'][] = $page['Other Person 1'];
            }
            if (strlen($page['Other Person 2']) > 0){
                $file['otherPeople'][] = $page['Other Person 2'];
            }
            if (strlen($page['Other Person 3']) > 0){
                $file['otherPeople'][] = $page['Other Person 3'];
            }
            if (strlen($page['Other Person 4']) > 0){
                $file['otherPeople'][] = $page['Other Person 4'];
            }
            if (strlen($page['Other Person 5']) > 0){
                $file['otherPeople'][] = $page['Other Person 5'];
            }
            if (strlen($page['Other Person 6']) > 0){
                $file['otherPeople'][] = $page['Other Person 6'];
            }
            
            if ($page['Public'] == 'no'){
                $file['public'] = false;
            } else {
                $file['public'] = true;
            }
            
            $object['files'][] = $file;
            
            unset($file);
        }
    }
    return $object;
}

//handle xsd:date before 1970 (https://stackoverflow.com/questions/33581012/create-date-object-in-php-for-dates-before-1970-in-certain-format)
function safe_strtotime($string) {
    $pattern = "F j, Y";
    
    if(!preg_match("/\d{4}/", $string, $match)) return null; //year must be in YYYY form
    $year = intval($match[0]);//converting the year to integer
    if($year >= 1970) return date($pattern, strtotime($string));//the year is after 1970 - no problems even for Windows
    if(stristr(PHP_OS, "WIN") && !stristr(PHP_OS, "DARWIN")) //OS seems to be Windows, not Unix nor Mac
    {
        $diff = 1975 - $year;//calculating the difference between 1975 and the year
        $new_year = $year + $diff;//year + diff = new_year will be for sure > 1970
        $new_date = date($pattern, strtotime(str_replace($year, $new_year, $string)));//replacing the year with the new_year, try strtotime, rendering the date
        return str_replace($new_year, $year, $new_date);//returning the date with the correct year
    }
    return date($pattern, strtotime($string));//do normal strtotime
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