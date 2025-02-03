<?php 
  /**** 
  * Author: Ethan Gruber
  * Date: January 2025
  * Function: Transform CSV for new contents with type URIs for IGCH
  ****/

$data = generate_json('IGCH-with-Type-IDs.csv');
$hoards = array();

//associative array of Nomisma IDs and preferred labels
$nomisma = array();
$coinTypes = array();

foreach($data as $row) {
    $contents = array();
    
    $id = $row['IGCH'];
    
    //counts
    if (strlen($row['Count']) > 0) {
        $contents['count'] = $row['Count'];
    }
    if (strlen($row['Min Count']) > 0) {
        $contents['minCount'] = $row['Min Count'];
    }
    if (strlen($row['Max Count']) > 0) {
        $contents['maxCount'] = $row['Max Count'];
    }
    if (strlen($row['Count Uncertain']) > 0) {
        $contents['certainty'] = "http://nomisma.org/id/uncertain_value";
    }    
    
    
    if (strlen($row['Type URI']) > 0) {
        $contents['coinType'] = $row['Type URI'];
    }
    
    //other typeDesc elements
    if (strlen($row['Start Date']) > 0 && strlen($row['End Date']) > 0) {
        $contents['fromDate'] = $row['Start Date'];
        $contents['toDate'] = $row['End Date'];
    }
    
    if (strlen($row['Denomination URI']) > 0) {
        $contents['denominations'][] = $row['Denomination URI'];
    }
    if (strlen($row['Material URI']) > 0) {
        $contents['materials'][] = $row['Material URI'];
    }
    
    //authority
    if (strlen($row['Authority 1 URI']) > 0) {
        $contents['authorities'][] = $row['Authority 1 URI'];
    }
    if (strlen($row['Authority 2 URI']) > 0) {
        $contents['authorities'][] = $row['Authority 2 URI'];
    }
    if (strlen($row['Authority 3 URI']) > 0) {
        $contents['authorities'][] = $row['Authority 3 URI'];
    }
    if (strlen($row['Authority Uncertain']) > 0) {
        $contents['authorities']['uncertain'] = true;
    }
    
    if (strlen($row['Stated Authority URI']) > 0) {
        $contents['statedauthorities'][] = $row['Stated Authority URI'];
    }
    
    //geographic
    if (strlen($row['Mint 1 URI']) > 0) {
        $contents['mints'][] = $row['Mint 1 URI'];
    }
    if (strlen($row['Mint 2 URI']) > 0) {
        $contents['mints'][] = $row['Mint 2 URI'];
    }
    if (strlen($row['Mint 3 URI']) > 0) {
        $contents['mints'][] = $row['Mint 3 URI'];
    }
    if (strlen($row['Mint 4 URI']) > 0) {
        $contents['mints'][] = $row['Mint 4 URI'];
    }
    if (strlen($row['Authority Uncertain']) > 0) {
        $contents['mints']['uncertain'] = true;
    }
    if (strlen($row['Region URI']) > 0) {
        $contents['regions'][] = $row['Region URI'];
    }
    
    //type description
    if (strlen(trim($row['Obverse Type'])) > 0) {
        $contents['obverse']['type'] = trim($row['Obverse Type']);
    }
    if (strlen(trim($row['Reverse Type'])) > 0) {
        $contents['reverse']['type'] = trim($row['Reverse Type']);
    }
    
    $hoards[$id]['contents'][] = $contents;
}

//var_dump($hoards);

foreach ($hoards as $recordId=>$hoard) {
    generate_nuds($recordId, $hoard);
}



/*****
 * FUNCTIONS FOR GENERATING NUDS 
 *****/
function generate_nuds($recordId, $hoard){
	$writer = new XMLWriter();  
	echo "Writing {$recordId}\n";
	$writer->openURI("contents/{$recordId}.xml");  
	//$writer->openURI('php://output');
	$writer->setIndent(true);
	$writer->setIndentString("    ");
	$writer->startDocument('1.0','UTF-8');
		
	//contentsDesc
	if (count($hoard['contents']) > 0){
		$writer->startElement('contentsDesc');			
		
			$writer->writeAttribute('xmlns', 'http://nomisma.org/nudsHoard');
			$writer->writeAttribute('xmlns:xlink', "http://www.w3.org/1999/xlink");
			$writer->writeAttribute('xmlns:nuds', "http://nomisma.org/nuds");
			$writer->writeAttribute('xmlns:mods', "http://www.loc.gov/mods/v3");
			$writer->writeAttribute('xmlns:gml', "http://www.opengis.net/gml");
			$writer->writeAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");	 
		
			$writer->startElement('contents');					
			
			foreach ($hoard['contents'] as $content){
				//coin or coinGrp
				if (array_key_exists('count', $content) && (int)$content['count'] == 1){
					$writer->startElement('coin');
					   parse_content($writer, $content);
					$writer->endElement();
				} else {
					$writer->startElement('coinGrp');
						if (array_key_exists('count', $content)){									
							$writer->writeAttribute('count', $content['count']);
						}
						if (array_key_exists('minCount', $content)){
							$writer->writeAttribute('minCount', $content['minCount']);
						}
						if (array_key_exists('maxCount', $content)){
							$writer->writeAttribute('maxCount', $content['maxCount']);
						}
						if (array_key_exists('certainty', $content)){
							$writer->writeAttribute('certainty', $content['certainty']);
						}
						parse_content($writer, $content);
					$writer->endElement();							
				}
			}
			$writer->endElement();
		$writer->endElement();
	}
	//end document
	return $writer->flush();
}

//generate typeDesc from metadata either stored from the row or extracted from JSON-LD remotely
function generate_typeDesc($writer, $content){
    $writer->startElement('nuds:typeDesc');
		//order elements to be compatible with the NUDS schema
		
		//date
		if (array_key_exists('fromDate', $content) && array_key_exists('toDate', $content)){
			if ($content['fromDate'] == $content['toDate']){
				$writer->startElement('nuds:date');
					$writer->writeAttribute('standardDate', number_pad($content['fromDate'], 4));
					$writer->text(get_date_textual($content['fromDate']));
				$writer->endElement();
			} else {
				$writer->startElement('nuds:dateRange');
					$writer->startElement('nuds:fromDate');
						$writer->writeAttribute('standardDate', number_pad($content['fromDate'], 4));
						$writer->text(get_date_textual($content['fromDate']));
					$writer->endElement();
					$writer->startElement('nuds:toDate');
						$writer->writeAttribute('standardDate', number_pad($content['toDate'], 4));
						$writer->text(get_date_textual($content['toDate']));
					$writer->endElement();
				$writer->endElement();
			}
		}
	
		if (array_key_exists('denominations', $content)){
		    $uncertain = (array_key_exists('uncertain', $content['denominations']) || count($content['denominations']) > 1) ? true : false;
		    
			foreach ($content['denominations'] as $uri){
			    if (strpos($uri, 'http') !== FALSE){
    			    $nm = get_nomisma_data($uri);
    				$writer->startElement('nuds:denomination');
        				$writer->writeAttribute('xlink:type', 'simple');
        				$writer->writeAttribute('xlink:href', $uri);
        				if ($uncertain == true){
        				    $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
        				}
    				$writer->text($nm['label']);
    				$writer->endElement();    
			    }				
			}
		}
		
		if (array_key_exists('materials', $content)){
		    $uncertain = (array_key_exists('uncertain', $content['materials']) || count($content['materials']) > 1) ? true : false;
		    foreach ($content['materials'] as $uri){
		        if (strpos($uri, 'http') !== FALSE){
    		        $nm = get_nomisma_data($uri);
    		        $writer->startElement('nuds:material');
        		        $writer->writeAttribute('xlink:type', 'simple');
        		        $writer->writeAttribute('xlink:href', $uri);
        		        if ($uncertain == true){
        		            $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
        		        }
        		        $writer->text($nm['label']);
    		        $writer->endElement();
		        }
		    }
			
		}
		
		//authority
		if (array_key_exists('authorities', $content) || array_key_exists('statedauthorities', $content)){
		    $writer->startElement('nuds:authority');
		    
    		if (array_key_exists('authorities', $content)){
    		    $uncertain = (array_key_exists('uncertain', $content['authorities'])) ? true : false;                
                
                foreach ($content['authorities'] as $uri){
                    if (strpos($uri, 'http') !== FALSE){
                        $nm = get_nomisma_data($uri);
                        if ($nm['type'] == 'foaf:Person'){
                            $element = 'nuds:persname';
                        } elseif ($nm['type'] == 'foaf:Organization' || $nm['type'] == 'foaf:Group'){
                            $element = 'nuds:corpname';
                        } elseif ($nm['type'] == 'rdac:Family'){
                            $element = 'nuds:famname';
                        } else {
                            $element = 'nuds:persname';
                        }
                        
                        $writer->startElement($element);
                            $writer->writeAttribute('xlink:type', 'simple');
                            $writer->writeAttribute('xlink:role', 'authority');
                            $writer->writeAttribute('xlink:href', $uri);
                            if ($uncertain == true){
                                $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                            }
                            $writer->text($nm['label']);
                        $writer->endElement();
                    }
                }
    		}
            if (array_key_exists('statedauthorities', $content)){
                foreach ($content['statedauthorities'] as $uri){
                    $uncertain = (array_key_exists('uncertain', $content['statedauthorities'])) ? true : false;
                    
                    if (strpos($uri, 'http') !== FALSE){
                        $nm = get_nomisma_data($uri);
                        if ($nm['type'] == 'foaf:Person'){
                            $element = 'nuds:persname';
                        } elseif ($nm['type'] == 'foaf:Organization' || $nm['type'] == 'foaf:Group'){
                            $element = 'nuds:corpname';
                        } elseif ($nm['type'] == 'rdac:Family'){
                            $element = 'nuds:famname';
                        } else {
                            $element = 'nuds:persname';
                        }
                        
                        $writer->startElement($element);
                            $writer->writeAttribute('xlink:type', 'simple');
                            $writer->writeAttribute('xlink:role', 'statedAuthority');
                            $writer->writeAttribute('xlink:href', $uri);
                            if ($uncertain == true){
                                $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                            }
                            $writer->text($nm['label']);
                        $writer->endElement();
                    }
                }                
    		}
    		
    		$writer->endElement();
		}
		
		//geographic
		if (array_key_exists('mints', $content) || array_key_exists('regions', $content)){
			$writer->startElement('nuds:geographic');
			if (array_key_exists('mints', $content)){
			    $uncertain = (array_key_exists('uncertain', $content['mints']) || count($content['mints']) > 1) ? true : false;
			    
			    foreach ($content['mints'] as $uri){
			        if (strpos($uri, 'http') !== FALSE){
    			        $nm = get_nomisma_data($uri);
    			        $writer->startElement('nuds:geogname');
        			        $writer->writeAttribute('xlink:type', 'simple');
        			        $writer->writeAttribute('xlink:role', 'mint');
        			        $writer->writeAttribute('xlink:href', $uri);
        			        if ($uncertain == true){
        			            $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
        			        }
        			        $writer->text($nm['label']);
    			        $writer->endElement();
    			    }
			    }
			}
			if (array_key_exists('regions', $content)){
			    $uncertain = (array_key_exists('uncertain', $content['regions']) || count($content['regions']) > 1) ? true : false;
			    
			    foreach ($content['regions'] as $uri){
			        if (strpos($uri, 'http') !== FALSE){
    			        $nm = get_nomisma_data($uri);
    			        $writer->startElement('nuds:geogname');
        			        $writer->writeAttribute('xlink:type', 'simple');
        			        $writer->writeAttribute('xlink:role', 'region');
        			        $writer->writeAttribute('xlink:href', $uri);
        			        if ($uncertain == true){
        			            $writer->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
        			        }
        			        $writer->text($nm['label']);
    			        $writer->endElement();
    			    }
			    }
			}
			$writer->endElement();
		}
		
		//obverse and reverse
		if (array_key_exists('obverse', $content)) {
		    $writer->startElement('nuds:obverse');
		      $writer->startElement('nuds:type');
		          $writer->startElement('nuds:description');
		              $writer->writeAttribute('xml:lang', 'en');
		              $writer->text($content['obverse']['type']);
		          $writer->endElement();
		      $writer->endElement();
		    $writer->endElement();
		}
		if (array_key_exists('reverse', $content)) {
		    $writer->startElement('nuds:reverse');
    		    $writer->startElement('nuds:type');
        		    $writer->startElement('nuds:description');
        		      $writer->writeAttribute('xml:lang', 'en');
        		      $writer->text($content['reverse']['type']);
        		    $writer->endElement();
    		    $writer->endElement();
		    $writer->endElement();
		}
		
		//end TypeDesc
		$writer->endElement();
}
	

/*****
 * parse content data object in order to generate a typeDesc for a coin or coinGrp
 *****/
//generate the content element from metadata in the content row (typeDesc and refDesc
function parse_content($writer, $content){
    GLOBAL $coinTypes;
    
    //begin with typeDesc
    //if there is a single coin type URI, then use that as the typeDesc
    if (array_key_exists('coinType', $content)){
        
        $uri = $content['coinType'];
        
        //ignore corpusnummorum; embed typeDesc elements
        if (strpos($uri, 'corpus-nummorum') !== FALSE) {
            //generate typeDesc
            generate_typeDesc($writer, $content);
            $pieces = explode("/", $uri);
            
            //generate refDesc
            $writer->startElement('nuds:refDesc');
                $writer->startElement('nuds:reference');
                    $writer->writeAttribute('xlink:type', 'simple');
                    $writer->writeAttribute('xlink:arcrole', 'nmo:hasTypeSeriesItem');
                    $writer->writeAttribute('xlink:href', $uri);
                    $writer->text("CN Type " . end($pieces));
                $writer->endElement();
            $writer->endElement();
        } else {
            if (array_key_exists($uri, $coinTypes)){
                $writer->startElement('nuds:typeDesc');
                    $writer->writeAttribute('xlink:type', 'simple');
                    $writer->writeAttribute('xlink:href', $coinTypes[$uri]);
                $writer->endElement();
            } else {
                $file_headers = @get_headers($uri);
                if (strpos($file_headers[0], '200') !== FALSE){
                    $coinTypes[$uri] = $uri;
                    
                    //write typeDesc
                    $writer->startElement('nuds:typeDesc');
                        $writer->writeAttribute('xlink:type', 'simple');
                        $writer->writeAttribute('xlink:href', $uri);
                    $writer->endElement();
                    
                } elseif (strpos($file_headers[0], '303') !== FALSE){
                    //redirect Svoronos references to CPE URIs
                    $newuri = str_replace('Location: ', '', $file_headers[7]);
                    $coinTypes[$uri] = $newuri;
                    
                    $writer->startElement('nuds:typeDesc');
                        $writer->writeAttribute('xlink:type', 'simple');
                        $writer->writeAttribute('xlink:href', $newuri);
                    $writer->endElement();
                } else {
                    echo "Error fetching {$uri}\n";
                }
            }
        }
    } else {
        generate_typeDesc($writer, $content);
    }
}

//get label from Nomisma JSON API
function get_nomisma_data($uri){
    GLOBAL $nomisma;
    
    if (preg_match('/^http:\/\/nomisma.org\/id\//', $uri)){
        $nm = array();
        
        if (array_key_exists($uri, $nomisma)){
            return $nomisma[$uri];
        } else {
            //get label and class from Nomisma JSON-LD
            $string = file_get_contents($uri . '.jsonld');
            $json = json_decode($string, true);
            
            echo "Reading {$uri}\n";
            
            foreach ($json["@graph"] as $obj){
                $curie = str_replace('http://nomisma.org/id/', 'nm:', $uri);
                
                if ($obj["@id"] == $uri || $obj["@id"] == $curie){
                    //get the English label
                    if (array_key_exists(0, $obj["skos:prefLabel"])) {
                        foreach ($obj["skos:prefLabel"] as $prefLabel){
                            if ($prefLabel["@language"] == 'en'){
                                $nm['label'] = $prefLabel["@value"];
                                break;
                            }
                        }
                    } else {
                        $nm['label'] = $obj["skos:prefLabel"]["@value"];
                    }
                    
                    foreach ($obj["@type"] as $type) {
                        if ($type != 'skos:Concept') {
                            $nm['type'] = $type;
                        }
                    }
                    
                    
                    
                    $nomisma[$uri] = $nm;
                    return $nm;
                    
                }
            }
        }
    }
}

/*****
 * Date Parsing
 *****/

//generate human-readable date based on the integer value
function get_date_textual($year){
    $textual_date = '';
    //display start date
    if($year < 0){
        $textual_date .= abs($year) . ' BCE';
    } elseif ($year > 0) {
        if ($year <= 600){
            $textual_date .= $year . ' CE';
        } else {
            $textual_date = $year;
        }
    }
    return $textual_date;
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
    if ($number > 0){
        $gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
    } elseif ($number < 0) {
        $bcNum = (int)abs($number);
        $gYear = '-' . str_pad($bcNum,$n,"0",STR_PAD_LEFT);
    }
    return $gYear;
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