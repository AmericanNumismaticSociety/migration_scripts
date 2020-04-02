<?php 
  /**** 
  * Author: Ethan Gruber
  * Date: March 2020
  * Function: Transform CSV files from Google Sheets into NUDS-Hoard XML 
  ****/

$contents_sheet = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vS4u59ilkRmFMFaABssQMrR5OEjvQYt0IcwVmK5xaJmlIfjyNmKhH2mRIM7ExV8F6RqcDqaJdgYbjC8/pub?output=csv');
$counts = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTr01VFU1jNLPm_YPZ4RAEKlIppeAGDOR56Go3uW0rfMC6ZEjyzmQ3BgbKDcOJbjjQOfDR_NR6tg4Zt/pub?output=csv');
$findspots = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vR9SerPnG1ITct4EyaUIk6pUqQIgZ6iWqHnJg7o3kz0XTWukq45HxqFNhSFKtQz4BgJub2nDl40FYWz/pub?output=csv');
$depositDates = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTI1-N57bT5PxIen4dafjV3MoSQa-4gV5ZVQNstLB4FeTkIuT8CcRfe9f8o9MmkQoE4iM1izCtbpDDw/pub?output=csv');
$refNotes = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vSye6cSV45CuOpVDvYDaUrABoP7W7CesN_lTlZo9G4ydfBh_kbEUuaWXq3ZiBkEPkojyAyI0B46zYPW/pub?output=csv');

//restructure the contents so that they can be more easily accessed from the the process below without constant reiteration through all lines
$contents = array();
foreach ($contents_sheet as $row){
    $contents[$row['id']][] = $row;
}

$hoards = array();

//associative array of Nomisma IDs and preferred labels
$nomisma = array();
$coinTypes = array();

$count = 0;

foreach($counts as $hoard){
    if (strlen($hoard['id']) > 0 && $count <= 1680){
        $id = trim($hoard['id']);
        if ($count == 1680){
            $hoards[$id] = process_hoard($hoard, $contents[$id]);
        }
        
        $count++;
    }
}

//var_dump($hoards);

$count = 0;
foreach ($hoards as $k=>$v){
	//var_dump($v);
    echo "Writing {$k}\n";
    generate_nuds($k, $v);	
	$count++;
}

//var_dump($nomisma);


/***** FUNCTIONS *****/
function process_hoard($hoard, $contents){
    GLOBAL $refNotes;
    GLOBAL $depositDates;
    GLOBAL $findspots;
    
    $id = trim($hoard['id']);
    
    $record = array();
    
    //handle counts
    if (strlen($hoard['total count']) && is_numeric(trim($hoard['total count']))){
        $record['total'] = (int)$hoard['total count'];
    }
    if (strlen($hoard['min count']) && is_numeric(trim($hoard['min count']))){
        $record['min'] = (int)$hoard['min count'];
    }
    if (strlen($hoard['max count']) && is_numeric(trim($hoard['max count']))){
        $record['max'] = (int)$hoard['max count'];
    }
    if (trim($hoard['approximate']) == 'yes'){
        $record['is_approximate'] = true;
    }
    $record['content_notes'] = $hoard['contents'] . (strlen($hoard['notes']) > 0 ? ': ' . $hoard['notes'] : '');
    
    //extract deposit dates
    foreach ($depositDates as $date){
        if ($date['id'] == $id){
            $record['deposit']['fromDate'] = (int) $date['fromDate'];
            $record['deposit']['toDate'] = (int) $date['toDate'];
            break;
        }
    }
    
    //extract discovery and find information
    foreach ($findspots as $findspot){
        if ($findspot['id'] == $id){
            //findspots
            $record['findspot']['desc'] = $findspot['value'];
            if (strlen($findspot['Place Name']) > 0){
                $record['findspot']['label'] = $findspot['Place Name'];
            }
            if (strlen($findspot['Canonical Geonames URI']) > 0){
                $record['findspot']['uri'] = $findspot['Canonical Geonames URI'];
            }
            if (strlen($findspot['GML-compliant Coordinates']) > 0){
                $record['findspot']['coords'] = $findspot['GML-compliant Coordinates'];
            }
            
            //discovery
            if (is_numeric($findspot['fromDate']) || is_numeric($findspot['toDate'])){
                //if both are numeric, then it is a date range
                if (is_numeric($findspot['fromDate']) && is_numeric($findspot['toDate'])){
                    $record['discovery']['fromDate'] = $findspot['fromDate'];
                    $record['discovery']['toDate'] = $findspot['toDate'];
                    
                    //certainty: approximate takes precedence over uncertain
                    if ($findspot['fromDate_approximate'] == 'TRUE'){
                        $record['discovery']['fromDate_certainty'] = 'approximate';
                    } elseif ($findspot['fromDate_uncertain'] == 'TRUE'){
                        $record['discovery']['fromDate_certainty'] = 'uncertain';
                    }
                    
                    if ($findspot['toDate_approximate'] == 'TRUE'){
                        $record['discovery']['toDate_certainty'] = 'approximate';
                    } elseif ($findspot['toDate_uncertain'] == 'TRUE'){
                        $record['discovery']['toDate_certainty'] = 'uncertain';
                    }
                    
                    
                } elseif (is_numeric($findspot['fromDate']) && !is_numeric($findspot['toDate'])){
                    $record['discovery']['date'] = $findspot['fromDate'];
                    //certainty: approximate takes precedence over uncertain
                    if ($findspot['fromDate_approximate'] == 'TRUE'){
                        $record['discovery']['date_certainty'] = 'approximate';
                    } elseif ($findspot['fromDate_uncertain'] == 'TRUE'){
                        $record['discovery']['date_certainty'] = 'uncertain';
                    }
                } elseif (!is_numeric($findspot['fromDate']) && is_numeric($findspot['toDate'])){
                    $record['discovery']['notAfter'] = $findspot['toDate'];
                    if ($findspot['toDate_approximate'] == 'TRUE'){
                        $record['discovery']['date_certainty'] = 'approximate';
                    } elseif ($findspot['toDate_uncertain'] == 'TRUE'){
                        $record['discovery']['date_certainty'] = 'uncertain';
                    }
                }
            }
        }
    }
    
    //disposition, notes, refs
    foreach ($refNotes as $row){
        if ($row['id'] == $id){
            if ($row['type'] == 'disposition'){
                $record['disposition'] = trim($row['value']);
            } elseif ($row['type'] == 'note'){
                $record['notes'][] = trim($row['value']);
            } elseif ($row['type'] == 'ref'){
                $record['refs'][] = trim($row['value']);
            }
        }
    }
    
    //reconstruct the contents into an associative array
    foreach ($contents as $row){
        //ignore blank rows
        if ($row['id'] == $id){
            $content = array();
            
            if (is_numeric($row['count'])){
                $content['count'] = $row['count'];
            }
            if (is_numeric($row['min count'])){
                $content['minCount'] = $row['min count'];
            }
            if (is_numeric($row['max count'])){
                $content['maxCount'] = $row['max count'];
            }
            //certainty
            if ($row['count approximate'] == 'TRUE'){
                $content['certainty'] = 'approximate';
            } elseif ($row['count uncertain'] == 'TRUE'){
                $content['certainty'] = 'uncertain';
            }
            
            //typeDesc
            foreach ($row as $k=>$v){
                $v = trim($v);
                if (strlen($v) > 0){
                    switch($k){
                        case 'general type desc.':
                            $content['desc'] = $v;
                            break;
                        case 'obv_type':
                            $content['obverse']['type'] = $v;
                            break;
                        case 'rev_type':
                            $content['reverse']['type'] = $v;
                            break;
                        case 'rev_legend':
                            $content['reverse']['legend'] = $v;
                            break;
                        case 'dob':
                            $content['dob'] = $v;
                            break;
                        case strpos($k, 'Mint') !== FALSE:
                            $content['mints'][] = $v;
                            break;
                        case strpos($k, 'Region') !== FALSE:
                            $content['regions'][] = $v;
                            break;
                        case strpos($k, 'Authority') !== FALSE || strpos($k, 'Dynasty') !== FALSE:
                            $content['authorities'][] = $v;
                            break;
                        case strpos($k, 'Material') !== FALSE:
                            $content['materials'][] = $v;
                            break;
                        case strpos($k, 'Denomination') !== FALSE:
                            $content['denominations'][] = $v;
                            break;
                        case strpos($k, 'ref') !== FALSE:
                            if (!preg_match('/^Svoronos\s\d+$/', $v)){
                                $content['refs'][] = $v;
                            }                            
                            break;
                        case strpos($k, 'Coin Type') !== FALSE:
                            $types = explode('|', $v);
                            
                            foreach ($types as $type){
                                $content['coinTypes'][] = trim($type);
                            }
                            break;
                        case "Editor's notes":
                            $content['note'] = $v;
                            break;
                    }
                }
            }
            
            if ($row['mint uncertain'] == TRUE){
                $content['mints']['uncertain'] = true;
            }
            if ($row['region uncertain'] == TRUE){
                $content['regions']['uncertain'] = true;
            }
            if ($row['authority uncertain'] == TRUE){
                $content['authorities']['uncertain'] = true;
            }
            if ($row['denomination uncertain'] == TRUE){
                $content['denominations']['uncertain'] = true;
            }
            if ($row['material uncertain'] == TRUE){
                $content['materials']['uncertain'] = true;
            }
            
            if (strlen($row['from_date']) > 0 && strlen($row['to_date']) > 0){
                $content['fromDate'] = $row['from_date'];
                $content['toDate'] = $row['to_date'];
            }
            
            
            $record['contents'][] = $content;
        }
    }
    
    //add hoard record into master hoards array
    return $record;
}

/*****
 * FUNCTIONS FOR GENERATING NUDS 
 *****/
function generate_nuds($recordId, $hoard){
	$writer = new XMLWriter();  
	$writer->openURI("nuds/{$recordId}.xml");  
	//$writer->openURI('php://output');
	$writer->setIndent(true);
	$writer->setIndentString("    ");
	$writer->startDocument('1.0','UTF-8');
	
	//begin XML document
	$writer->startElement('nudsHoard');  
		$writer->writeAttribute('xmlns', 'http://nomisma.org/nudsHoard');  
		$writer->writeAttribute('xmlns:xlink', "http://www.w3.org/1999/xlink");  
		$writer->writeAttribute('xmlns:nuds', "http://nomisma.org/nuds");  
		$writer->writeAttribute('xmlns:mods', "http://www.loc.gov/mods/v3");
		$writer->writeAttribute('xmlns:gml', "http://www.opengis.net/gml");
		$writer->writeAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");	 
		//begin control
		$writer->startElement('control');  
			$writer->writeElement('recordId', $recordId);
			$writer->writeElement('maintenanceStatus', 'derived');
			$writer->writeElement('publicationStatus', 'approved');
			$writer->startElement('maintenanceAgency');
				$writer->writeElement('agencyName', 'American Numismatic Society');
			$writer->endElement();
			$writer->startElement('maintenanceHistory');
				$writer->startElement('maintenanceEvent');
					$writer->writeElement('eventType', 'derived');
					$writer->startElement('eventDateTime');
						$writer->writeAttribute('standardDateTime', date(DATE_W3C));
						$writer->text(date(DATE_RFC2822));						
					$writer->endElement();
					$writer->writeElement('agentType', 'human');
					$writer->writeElement('agent', 'Ethan Gruber');
					$writer->writeElement('eventDescription', 'NUDS-Hoard records generated by Ethan Gruber from spreadsheets Disnarda Pinilla cleaned from the IGCH records.s');
				$writer->endElement();
			$writer->endElement();
			$writer->startElement('rightsStmt');
				$writer->writeElement('copyrightHolder', 'American Numismatic Society');
				$writer->writeElement('license', 'http://opendatacommons.org/licenses/odbl/');
			$writer->endElement();
		//end control
		$writer->endElement();
		
		//descMeta
		$writer->startElement('descMeta');
			$writer->startElement('title');
				$writer->writeAttribute('xml:lang', 'en');
				$writer->text('IGCH ' . ltrim(str_replace('igch', '', $recordId), '0'));
			$writer->endElement();
		
			//noteSet
			if (array_key_exists('notes', $hoard)){
				$writer->startElement('noteSet');
				foreach ($hoard['notes'] as $note){
					$writer->writeElement('note', $note);
				}
				$writer->endElement();
			}
			
			//hoardDesc = nuds:findspotDesc
			$writer->startElement('hoardDesc');			
				//findspot
			if (array_key_exists('findspot', $hoard)){
				$writer->startElement('findspot');
					$writer->startElement('description');
						$writer->writeAttribute('xml:lang', 'en');
						$writer->text($hoard['findspot']['desc']);
					$writer->endElement();
					if (array_key_exists('label', $hoard['findspot']) && array_key_exists('uri', $hoard['findspot'])){
					    $writer->startElement('fallsWithin');
                            if (array_key_exists('coords', $hoard['findspot'])){
                                $writer->startElement('gml:location');
                                if (strpos($hoard['findspot']['coords'], ' ') !== FALSE){                                    
                                    $writer->startElement('gml:Polygon');
                                        $writer->writeElement('gml:coordinates', $hoard['findspot']['coords']);
                                    $writer->endElement();
                                } else {
                                    $writer->startElement('gml:Point');
                                        $writer->writeElement('gml:coordinates', $hoard['findspot']['coords']);
                                    $writer->endElement();
                                }
                                $writer->endElement();
                            }					    
        					$writer->startElement('geogname');
        						$writer->writeAttribute('xlink:type', 'simple');
        						$writer->writeAttribute('xlink:role', 'findspot');
        						$writer->writeAttribute('xlink:href', $hoard['findspot']['uri']);
        						$writer->text($hoard['findspot']['label']);
        					$writer->endElement();
    					$writer->endElement();
					}						
				$writer->endElement();
			}
				
				//deposit
			if (array_key_exists('deposit', $hoard)){
				$writer->startElement('deposit');
					if ($hoard['deposit']['fromDate'] == $hoard['deposit']['toDate']){
						$writer->startElement('date');
							$writer->writeAttribute('standardDate', number_pad($hoard['deposit']['fromDate'], 4));
							$writer->text(get_date_textual($hoard['deposit']['fromDate']));
						$writer->endElement();
					} else {
						$writer->startElement('dateRange');
							$writer->startElement('fromDate');
								$writer->writeAttribute('standardDate', number_pad($hoard['deposit']['fromDate'], 4));
								$writer->text(get_date_textual($hoard['deposit']['fromDate']));
							$writer->endElement();
							$writer->startElement('toDate');
								$writer->writeAttribute('standardDate', number_pad($hoard['deposit']['toDate'], 4));
								$writer->text(get_date_textual($hoard['deposit']['toDate']));
							$writer->endElement();
						$writer->endElement();
					}
				$writer->endElement();
			}
			
				//discovery
				if (array_key_exists('discovery', $hoard)){
					$writer->startElement('discovery');
						if (array_key_exists('date', $hoard['discovery'])){
							$writer->startElement('date');
								$writer->writeAttribute('standardDate', number_pad($hoard['discovery']['date'], 4));							
								if (array_key_exists('date_certainty', $hoard['discovery'])){
									$writer->writeAttribute('certainty', $hoard['discovery']['date_certainty']);									
								}
								$writer->text(get_date_textual($hoard['discovery']['date']));
								
							$writer->endElement();
						} elseif (array_key_exists('notAfter', $hoard['discovery'])){
							$writer->startElement('date');
								$writer->writeAttribute('notAfter', number_pad($hoard['discovery']['notAfter'], 4));
								if (array_key_exists('date_certainty', $hoard['discovery'])){
									$writer->writeAttribute('certainty', $hoard['discovery']['date_certainty']);
								}
								$writer->text('before ' . get_date_textual($hoard['discovery']['notAfter']));
							$writer->endElement();
						} elseif (array_key_exists('fromDate', $hoard['discovery']) && array_key_exists('toDate', $hoard['discovery'])){
							$writer->startElement('dateRange');
								$writer->startElement('fromDate');
									$writer->writeAttribute('standardDate', number_pad($hoard['discovery']['fromDate'], 4));
									if (array_key_exists('fromDate_certainty', $hoard['discovery'])){
										$writer->writeAttribute('certainty', $hoard['discovery']['fromDate_certainty']);
									}
									$writer->text(get_date_textual($hoard['discovery']['fromDate']));
								$writer->endElement();
								$writer->startElement('toDate');
									$writer->writeAttribute('standardDate', number_pad($hoard['discovery']['toDate'], 4));
									if (array_key_exists('toDate_certainty', $hoard['discovery'])){
										$writer->writeAttribute('certainty', $hoard['discovery']['toDate_certainty']);
									}
									$writer->text(get_date_textual($hoard['discovery']['toDate']));
								$writer->endElement();
							$writer->endElement();
						}
					$writer->endElement();
				}
				
				//disposition
				if (array_key_exists('disposition', $hoard)){
					$writer->startElement('disposition');
						$writer->startElement('description');
							$writer->writeAttribute('xml:lang', 'en');
							$writer->text($hoard['disposition']);
						$writer->endElement();
					$writer->endElement();
				}
			
			$writer->endElement();
		
			//contentsDesc
			if (count($hoard['contents']) > 0 || array_key_exists('content_notes', $hoard)){
				$writer->startElement('contentsDesc');					
					$writer->startElement('contents');
					//add counts
					if (array_key_exists('total', $hoard)){
						$writer->writeAttribute('count', $hoard['total']);
					}
					if (array_key_exists('min', $hoard)){
						$writer->writeAttribute('minCount', $hoard['min']);
					}
					if (array_key_exists('max', $hoard)){
						$writer->writeAttribute('maxCount', $hoard['max']);
					}
					
					if (array_key_exists('content_notes', $hoard)){
						$writer->startElement('description');
							$writer->writeAttribute('xml:lang', 'en');
							$writer->text($hoard['content_notes']);
						$writer->endElement();
					}
					
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
			
			//refDesc
			if (array_key_exists('refs', $hoard)){
				$writer->startElement('refDesc');
					foreach ($hoard['refs'] as $ref){
						$writer->writeElement('reference', $ref);
					}
				$writer->endElement();
			}
			
		//end descMeta
		$writer->endElement();	
	//end nudsHoard
	$writer->endElement();	
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
		
		/*if (array_key_exists('dob', $content)){
			
		}*/
	
		if (array_key_exists('denominations', $content)){
		    $uncertain = (array_key_exists('uncertain', $content['denominations']) || count($content['denominations']) > 1) ? true : false;
		    
			foreach ($content['denominations'] as $uri){
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
		
		if (array_key_exists('materials', $content)){
		    $uncertain = (array_key_exists('uncertain', $content['materials']) || count($content['materials']) > 1) ? true : false;
		    foreach ($content['materials'] as $uri){
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
		
		//authority
		if (array_key_exists('authorities', $content)){
		    $uncertain = (array_key_exists('uncertain', $content['authorities'])) ? true : false;
            
            $writer->startElement('nuds:authority');
            foreach ($content['authorities'] as $uri){
                $nm = get_nomisma_data($uri);
                if ($nm['type'] == 'http://xmlns.com/foaf/0.1/Person'){
                    $element = 'nuds:persname';
                } elseif ($nm['type'] == 'http://xmlns.com/foaf/0.1/Organization'){
                    $element = 'nuds:corpname';
                } elseif ($nm['type'] == 'http://www.rdaregistry.info/Elements/c/Family'){
                    $element = 'nuds:famname';
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
            $writer->endElement();
		}
		
		//geographic
		if (array_key_exists('mints', $content) || array_key_exists('regions', $content)){
			$writer->startElement('nuds:geographic');
			if (array_key_exists('mints', $content)){
			    $uncertain = (array_key_exists('uncertain', $content['mints']) || count($content['mints']) > 1) ? true : false;
			    
			    foreach ($content['mints'] as $uri){
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
			if (array_key_exists('regions', $content)){
			    $uncertain = (array_key_exists('uncertain', $content['regions']) || count($content['regions']) > 1) ? true : false;
			    
			    foreach ($content['regions'] as $uri){
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
			$writer->endElement();
		}
		
		//obv
		if (array_key_exists('obverse', $content)){
			generate_side($writer, 'obverse', $content['obverse']);
		}
		//rev
		if (array_key_exists('reverse', $content)){
			generate_side($writer, 'reverse', $content['reverse']);
		}
	//end typeDesc
	$writer->endElement();
}

//generate obverse or reverse description
function generate_side($writer, $side, $arr){
	$writer->startElement('nuds:' . $side);
		if (array_key_exists('legend', $arr)){
			$writer->writeElement('nuds:legend', $arr['legend']);
		}
		if (array_key_exists('type', $arr)){
			$writer->startElement('nuds:type');
				$writer->startElement('nuds:description');
					$writer->writeAttribute('xml:lang', 'en');
					$writer->text($arr['type']);
				$writer->endElement();
			$writer->endElement();
		}
	$writer->endElement();
}

/*****
 * parse content data object in order to generate a typeDesc for a coin or coinGrp
 *****/
//generate the content element from metadata in the content row (typeDesc and refDesc
function parse_content($writer, $content){
    GLOBAL $coinTypes;
    $contentTypes = array();
    
    if (array_key_exists('refs', $content)){
        $refs = $content['refs'];
    }
    
    //begin with typeDesc
    //if there is a single coin type URI, then use that as the typeDesc
    if (array_key_exists('coinTypes', $content)){
        $uncertain = count($content['coinTypes']) == 1 ? false : true;
        
        foreach ($content['coinTypes'] as $uri){
            if (array_key_exists($uri, $coinTypes)){
                $coinType = array('label'=>$coinTypes[$uri]['label'], 'uri'=>$coinTypes[$uri]['uri'], 'uncertain'=>$uncertain);
                $contentTypes[$uri] = $coinType;
            } else {
                $file_headers = @get_headers($uri);
                if ($file_headers[0] == 'HTTP/1.1 200 OK'){
                    //echo "Found {$uri}\n";
                    //generate the title from the NUDS
                    $typeData = get_type_data($uri);
                    $coinTypes[$uri] = array('uri'=>$uri, 'label'=>$typeData['label'], 'data'=>$typeData['data']);
                    $coinType = array('label'=>$typeData['label'], 'uri'=>$uri, 'uncertain'=>$uncertain);
                    $contentTypes[$uri] = $coinType;
                } elseif ($file_headers[0] == 'HTTP/1.1 303 See Other'){
                    //redirect Svoronos references to CPE URIs
                    $newuri = str_replace('Location: ', '', $file_headers[7]);
                    //echo "Matching: {$uri} -> {$newuri}\n";
                    
                    //generate the title from the NUDS
                    $typeData = get_type_data($newuri);
                    $coinTypes[$uri] = array('uri'=>$newuri, 'label'=>$typeData['label'], 'data'=>$typeData['data']);
                    $coinType = array('label'=>$typeData['label'], 'uri'=>$newuri, 'uncertain'=>$uncertain);
                    $contentTypes[$uri] = $coinType;
                }
            }
        }
        
        if (count($contentTypes) == 1){
            $writer->startElement('nuds:typeDesc');
                $writer->writeAttribute('xlink:type', 'simple');
                $writer->writeAttribute('xlink:href', array_values($contentTypes)[0]['uri']);
            $writer->endElement();
        } else {
            //overwrite content array with the typeDesc metadata extracted from canonical sources
            $content = parse_coinTypes($contentTypes);
            generate_typeDesc($writer, $content);
        }
    } else {
        generate_typeDesc($writer, $content);
    }
    
    //refDesc
    if (isset($refs) || count($contentTypes) > 0){
        $writer->startElement('nuds:refDesc');
        if (isset($refs)){
            foreach($refs as $ref){
                $writer->writeElement('nuds:reference', $ref);
            }
        }        
        foreach ($contentTypes as $type){
            $writer->startElement('nuds:reference');
                $writer->writeAttribute('xlink:arcrole', 'nmo:hasTypeSeriesItem');
                $writer->writeAttribute('xlink:type', 'simple');
                $writer->writeAttribute('xlink:href', $type['uri']);    
                $writer->text($type['label']);
            $writer->endElement();            
        }
        $writer->endElement();
    }
    
    unset($refs);
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
	        
	        //$label = $json["@graph"][0]["skos:prefLabel"][0]["@value"];
	        
	        foreach ($json as $obj){
	            if ($obj["@id"] == $uri){
	                //get the English label
	                foreach ($obj["http://www.w3.org/2004/02/skos/core#prefLabel"] as $prefLabel){
	                    if ($prefLabel["@language"] == 'en'){
	                        $nm['label'] = $prefLabel["@value"];
	                        break;
	                    }
	                }
	                foreach ($obj["@type"] as $class){
	                    if ($class != 'http://www.w3.org/2004/02/skos/core#Concept'){
	                        $nm['type'] = $class;
	                    }
	                }
	                
	                $nomisma[$uri] = $nm;
	                return $nm;
	            }
	        }
	    }
	}
	
	
}


/*
 * Parse the Nomisma JSON-LD for the coin type to extract title and metadata
 */
function get_type_data($uri){
    $typeData = array();
    //get the NUDS XML URL based on domain
    $string = file_get_contents($uri . '.jsonld');
    $json = json_decode($string, true);
    
    $label = $json["@graph"][0]["skos:prefLabel"][0]["@value"];
    
    $typeData['label'] = $label;
    $typeData['data'] = $json["@graph"][0];

    return $typeData;
    
}

//parse the JSON-LD data from each associated type to extract a unique list of entities and values
function parse_coinTypes ($types){
    GLOBAL $coinTypes;
    
    $content = array();
    
    $authorities = array();
    $denominations = array();
    $materials = array();
    $mints = array();
    $regions = array();
    $dates = array();
    
    foreach ($types as $originalType=>$type){
        foreach ($coinTypes[$originalType]['data'] as $k=>$v){
            if ($k == 'nmo:hasAuthority'){
                foreach ($v as $array){
                    $uri = $array["@id"];
                    if (!in_array($uri, $authorities)){
                        $authorities[] = $uri;
                    }
                }               
            }
            if ($k == 'nmo:hasDenomination'){
                foreach ($v as $array){
                    $uri = $array["@id"];
                    if (!in_array($uri, $denominations)){
                        $denominations[] = $uri;
                    }
                }
            }
            if ($k == 'nmo:hasMaterial'){
                foreach ($v as $array){
                    $uri = $array["@id"];
                    if (!in_array($uri, $materials)){
                       $materials[] = $uri;
                    }
                }
            }
            if ($k == 'nmo:hasMint'){
                foreach ($v as $array){
                    $uri = $array["@id"];
                    if (!in_array($uri, $mints)){
                       $mints[] = $uri;
                    }
                }
            }
            if ($k == 'nmo:hasRegion'){
                foreach ($v as $array){
                    $uri = $array["@id"];
                    if (!in_array($uri, $regions)){
                        $mints[] = $uri;
                    }
                }
            }
            if ($k == 'nmo:hasStartDate' || $k == 'nmo:hasEndDate'){
                foreach ($v as $array){
                    $dates[] = (int) $array["@value"];
                }
            }
        }
    }
    
    
    //populate the content array to be returned
    if (count($authorities) > 0){
        $content['authorities'] = $authorities;
    }
    if (count($denominations) > 0){
        $content['denominations'] = $denominations;
    }
    if (count($materials) > 0){
        $content['materials'] = $materials;
    }
    if (count($mints) > 0){
        $content['mints'] = $mints;
    }
    if (count($regions) > 0){
        $content['regions'] = $regions;
    }
    
    //sort dates and return the earliest and latest possible dates
    asort($dates);    
    if (count($dates) > 0){        
        $content['fromDate'] = $dates[0];
        $content['toDate'] = $dates[count($dates) - 1];
    }
    
    return $content;    
}

/*****
 * Date Parsing
 *****/

//generate human-readable date based on the integer value
function get_date_textual($year){
    $textual_date = '';
    //display start date
    if($year < 0){
        $textual_date .= abs($year) . ' B.C.';
    } elseif ($year > 0) {
        if ($year <= 600){
            $textual_date .= 'A.D. ';
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