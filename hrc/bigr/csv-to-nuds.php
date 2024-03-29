<?php 
 /*****
 * Author: Ethan Gruber
 * Last modified: August 2022
 * Function: Transform the Bactrian and Indo-Greek typology into NUDS
 *****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQ5yn9InMwKrrK2UKvbBACm8PZSn5Ht9YSG7iv4wQ2TOu9a-h3WcTQE1E3lLO58hy22cHahMeVBQe5k/pub?gid=0&single=true&output=csv');
$obverses = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQ5yn9InMwKrrK2UKvbBACm8PZSn5Ht9YSG7iv4wQ2TOu9a-h3WcTQE1E3lLO58hy22cHahMeVBQe5k/pub?gid=453963359&single=true&output=csv');
$reverses = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQ5yn9InMwKrrK2UKvbBACm8PZSn5Ht9YSG7iv4wQ2TOu9a-h3WcTQE1E3lLO58hy22cHahMeVBQe5k/pub?gid=224949462&single=true&output=csv');

$nomismaUris = array();
$monograms = array();
$errors = array();

$count = 1;

foreach($data as $row){
	//call generate_nuds twice to generate two sets of NUDS records
	
	if (strlen($row['ID']) > 0){
	    generate_nuds($row, $count);
	    $count++;
	}
}

foreach ($errors as $error) {
    echo $error . "\n";
}

//functions
function generate_nuds($row, $count){
	GLOBAL $obverses;
	GLOBAL $reverses;
	
	$uri_space = 'http://numismatics.org/bigr/id/';	
	$recordId = trim($row['ID']);
	
	if (strlen($recordId) > 0){
	    $idPieces = explode('.', $recordId);
	    
	    if (isset($idPieces[4])) {
	        $typeNumber = $idPieces[2] . '.' . $idPieces[3] . '.' . $idPieces[4];
	    } elseif (isset($idPieces[3])) {
	        $typeNumber = $idPieces[2] . '.' . $idPieces[3];
	    } else {
	        $typeNumber = $idPieces[2];
	    }
	    
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
				//Price concordance
				if (strlen(trim($row['Matching URI'])) > 0){
				    $uris = explode('|', trim($row['Matching URI']));
				    
				    foreach ($uris as $uri){
				        $uri = trim($uri);
				        $doc->startElement('otherRecordId');
    				        $doc->writeAttribute('semantic', 'skos:exactMatch');
    				        $doc->text($uri);
				        $doc->endElement(); 
				    }
				}
				
				//insert typeNumber just to capture the num.
				$doc->startElement('otherRecordId');
    				$doc->writeAttribute('localType', 'typeNumber');
    				$doc->text($typeNumber);
    			$doc->endElement();	
				
    			//insert a sortID
    			$doc->startElement('otherRecordId');
        			$doc->writeAttribute('localType', 'sortId');
        			$doc->text(number_pad(intval($count), 4));
    			$doc->endElement();		
    			
				//handle subtype hierarchy
				if (strlen($row['Parent ID']) > 0){
				    $doc->startElement('otherRecordId');
    				    $doc->writeAttribute('semantic', 'skos:broader');
    				    $doc->text(trim($row['Parent ID']));
				    $doc->endElement();
				    $doc->writeElement('publicationStatus', 'approvedSubtype');
				} else {				    		    
				    $doc->writeElement('publicationStatus', 'approved');
				}
				
				$doc->writeElement('maintenanceStatus', 'derived');
				$doc->startElement('maintenanceAgency');
					$doc->writeElement('agencyName', 'American Numismatic Society');
				$doc->endElement();				
				
				//maintenanceHistory
				$doc->startElement('maintenanceHistory');
					$doc->startElement('maintenanceEvent');
						$doc->writeElement('eventType', 'derived');
						$doc->startElement('eventDateTime');
							$doc->writeAttribute('standardDateTime', date(DATE_W3C));
							$doc->text(date(DATE_RFC2822));
						$doc->endElement();
						$doc->writeElement('agentType', 'machine');
						$doc->writeElement('agent', 'PHP');
						$doc->writeElement('eventDescription', 'Generated from CSV from Google Drive.');
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
			$title = parse_title($recordId);
			
			$doc->startElement('title');
    			$doc->writeAttribute('xml:lang', 'en');
    			$doc->text($title);
			$doc->endElement();
			
			/***** NOTES *****/
			/*if (strlen(trim($row['Note'])) > 0){
				$doc->startElement('noteSet');
				if (strlen(trim($row['Note'])) > 0){
					$doc->startElement('note');
						$doc->writeAttribute('xml:lang', 'en');
						$doc->text(trim($row['Note']));
					$doc->endElement();
				}
				$doc->endElement();
			}*/
			
			/***** TYPEDESC *****/
			$doc->startElement('typeDesc');
			
				//objectType
				$doc->startElement('objectType');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/coin');
					$doc->text('Coin');
				$doc->endElement();
				
				//sort dates
				if (strlen($row['From Date']) > 0 || strlen($row['To Date']) > 0){
				    if (strlen($row['Textual Date']) > 0){
				        $fromDate = intval(trim($row['From Date']));
				        $toDate = intval(trim($row['To Date']));
				        
				        $doc->startElement('date');
    				        $doc->writeAttribute('notBefore', number_pad($fromDate, 4));
    				        $doc->writeAttribute('notAfter', number_pad($toDate, 4));
				        $doc->text(trim($row['Textual Date']));
				        $doc->endElement();
				    } else {
				        if (($row['From Date'] == $row['To Date']) || (strlen($row['From Date']) > 0 && strlen($row['To Date']) == 0)){
				            //ascertain whether or not the date is a range
				            $fromDate = intval(trim($row['From Date']));
				            
				            $doc->startElement('date');
    				            $doc->writeAttribute('standardDate', number_pad($fromDate, 4));
    				            $doc->text(get_date_textual($fromDate));
				            $doc->endElement();
				        } else {
				            $fromDate = intval(trim($row['From Date']));
				            $toDate = intval(trim($row['To Date']));
				            
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
				}
				
				if (strpos($row['Denomination URI'], 'http') !== FALSE){
					$vals = explode('|', $row['Denomination URI']);
					foreach ($vals as $val){
					    $val = trim($val);
					    if (substr($val, -1) == '?'){
					        $uri = substr($val, 0, -1);
					        $uncertainty = true;
					        $content = processUri($uri);
					    } else {
					        $uri =  $val;
					        $uncertainty = false;
					        $content = processUri($uri);
					    }
					    
						$doc->startElement('denomination');
    						$doc->writeAttribute('xlink:type', 'simple');
    						$doc->writeAttribute('xlink:href', $uri);
    						if($uncertainty == true){
    						    $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
    						}
    						$doc->text($content['label']);
						$doc->endElement();
					}
				}
				
				//manufacture: if the SC no. includes P, it is plated, otherwise struck
				$doc->startElement('manufacture');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/struck');
					$doc->text('Struck');
				$doc->endElement();				
				
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
						
						$doc->startElement('material');
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', $uri);
						if($uncertainty == true){
						    $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
						}
						$doc->text($content['label']);
						$doc->endElement();
					}
				}
				
				if (strlen($row['Shape URI']) > 0){
				    $uri = trim($row['Shape URI']);
				    $content = processUri($uri);
				    
				    $doc->startElement('shape');
    				    $doc->writeAttribute('xlink:type', 'simple');
    				    $doc->writeAttribute('xlink:href', $uri);
    				    $doc->text($content['label']);
				    $doc->endElement();		
				}
				
				//authority
				if (strlen($row['Authority URI']) > 0 || strlen($row['Stated Authority URI']) > 0 || strlen($row['Authenticity URI']) > 0){
					$doc->startElement('authority');
					if (strlen($row['Authority URI']) > 0){
							$vals = explode('|', $row['Authority URI']);
							foreach ($vals as $val){
							    $val = trim($val);
							    
								if (substr($val, -1) == '?'){
									$uri = substr($val, 0, -1);
									$uncertainty = true;
									$content = processUri($uri);
								} else {
									$uri =  $val;
									$uncertainty = false;
									$content = processUri($uri);
								}
								$role = 'authority';
								
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
						}
						if (strlen($row['Stated Authority URI']) > 0){
							$vals = explode('|', $row['Stated Authority URI']);
							foreach ($vals as $val){
							    $val = trim($val);
								if (substr($val, -1) == '?'){
									$uri = substr($val, 0, -1);
									$uncertainty = true;
									$content = processUri($uri);
								} else {
									$uri =  $val;
									$uncertainty = false;
									$content = processUri($uri);
								}
								$role = 'statedAuthority';
								
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
						}
						
						if (strlen($row['Authenticity URI']) > 0){
						    $uri = trim($row['Authenticity URI']);
						    $content = processUri($uri);
						    
						    $doc->startElement('authenticity');
    						    $doc->writeAttribute('xlink:type', 'simple');
    						    $doc->writeAttribute('xlink:href', $uri);
    						    $doc->text($content['label']);
						    $doc->endElement();
						}
					$doc->endElement();
				}
				
				//geography					
				/*if (strlen($row['Region URI']) > 0){
				    $doc->startElement('geographic');
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
						
						unset($uncertainty);
					}
					$doc->endElement();
				}*/
				
				//obverse
				if (strlen($row['Obverse Type Code']) > 0){
					$key = trim($row['Obverse Type Code']);
					
					$doc->startElement('obverse');	
					
    					//legend
    					if (strlen(trim($row['Obverse Legend (Grek)'])) > 0){
    					    $legend = trim($row['Obverse Legend (Grek)']);
    					    
    					    $doc->startElement('legend');
    					    //edition
    					    $doc->startElement('tei:div');
        					    $doc->writeAttribute('type', 'edition');
            					    $doc->startElement('tei:ab');
            					    if (strlen($row['Obverse Legend Orientation']) > 0){
            					        $doc->writeAttribute('rend', trim($row['Obverse Legend Orientation']));
            					    }
            					    $doc->text($legend);
        					    $doc->endElement();
    					    $doc->endElement();
    					    
    					    if (strlen(trim($row['Obverse Legend Transliteration'])) > 0){
    					        //transliteration
    					        $doc->startElement('tei:div');
        					        $doc->writeAttribute('type', 'transliteration');
        					        $doc->startElement('tei:ab');
        					           $doc->text(trim($row['Obverse Legend Transliteration']));
        					        $doc->endElement();
    					        $doc->endElement();
    					    }
    					    $doc->endElement();
    					}
					
						//multilingual type descriptions
						$doc->startElement('type');
							foreach ($obverses as $desc){
								if ($desc['code'] == $key){
								    $type = $desc['en'];
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
						
						if (strlen($row['Obverse Portrait URI']) > 0){
							$vals = explode('|', $row['Obverse Portrait URI']);
							foreach ($vals as $val){
							    $val = trim($val);
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
									$doc->writeAttribute('xlink:role', $content['role']);
									$doc->writeAttribute('xlink:href', $uri);
									if($uncertainty == true){
									    $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
									}
									$doc->text($content['label']);
								$doc->endElement();
							}
						}
						if (strlen($row['Obverse Deity URI']) > 0){
						    $vals = explode('|', $row['Obverse Deity URI']);
						    foreach ($vals as $val){
						        $val = trim($val);
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
    						        $doc->writeAttribute('xlink:role', 'deity');
    						        $doc->writeAttribute('xlink:href', $uri);
    						        if($uncertainty == true){
    						            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
    						        }
    						        $doc->text($content['label']);
						        $doc->endElement();
						    }
						}
						
						//symbols
						if (strlen($row['Obverse Symbol']) > 0){
							$doc->startElement('symbol');
							     parse_symbol($doc, trim($row['Obverse Symbol']));
							$doc->endElement();
						}
						if (strlen($row['O:leftField']) > 0){
						    $doc->startElement('symbol');
                                $doc->writeAttribute('position', 'leftField');						     
                                parse_symbol($doc, trim($row['O:leftField']));
						    $doc->endElement();
						}
						if (strlen($row['O:rightField']) > 0){
						    $doc->startElement('symbol');
    						    $doc->writeAttribute('position', 'rightField');
    						    parse_symbol($doc, trim($row['O:rightField']));
						    $doc->endElement();
						}
					//end obverse
					$doc->endElement();
				}
				
				//reverse
				if (strlen($row['Reverse Type Code']) > 0){
					$key = trim($row['Reverse Type Code']);					
					
					$doc->startElement('reverse');
					
						//legend
						if (strlen(trim($row['Reverse Legend'])) > 0){
							$legend = trim($row['Reverse Legend']);							
							
                            $doc->startElement('legend');                            
                                //edition
                                $doc->startElement('tei:div');
                                    $doc->writeAttribute('type', 'edition');
                                    $doc->startElement('tei:ab');
                                        if (strlen($row['Reverse Legend Orientation']) > 0){
                                            $doc->writeAttribute('rend', trim($row['Reverse Legend Orientation']));
                                        }
                                        $doc->text($legend);
                                    $doc->endElement();
                                $doc->endElement();
                            
                               if (strlen(trim($row['Reverse Legend Transliteration'])) > 0){
                                    //transliteration
                                    $doc->startElement('tei:div');
                                        $doc->writeAttribute('type', 'transliteration');
                                        $doc->startElement('tei:ab');
                                            $doc->text(trim($row['Reverse Legend Transliteration']));
                                        $doc->endElement();
                                    $doc->endElement();
                                }
                            $doc->endElement();
						}
					
						//multilingual type descriptions
						$doc->startElement('type');
						 foreach ($reverses as $desc){
							 if ($desc['code'] == $key){
							     $type = $desc['en'];
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
						 
						//symbols
						foreach ($row as $k=>$v){
							//reverse symbols are preceded with R:
							if (substr($k, 0, 2) == 'R:'){
								if (strlen(trim($v)) > 0){
								    $position = str_replace('R:', '', $k);
								    $position = strpos($position, '_') !== FALSE ? substr($position, 0, strpos($position, '_')) : $position;									
									$doc->startElement('symbol');
									   $doc->writeAttribute('position', $position);
									   parse_symbol($doc, trim($v));
									$doc->endElement();
								}
							}
						}
						
						if (strlen($row['Reverse Portrait URI']) > 0){
							$vals = explode('|', $row['Reverse Portrait URI']);
							foreach ($vals as $val){
							    $val = trim($val);
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
    								$doc->writeAttribute('xlink:role', $content['role']);
    								$doc->writeAttribute('xlink:href', $uri);
    								if($uncertainty == true){
    								    $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
    								}
    								$doc->text($content['label']);
								$doc->endElement();
							}
						}
						if (strlen($row['Reverse Deity URI']) > 0){
						    $vals = explode('|', $row['Reverse Deity URI']);
						    foreach ($vals as $val){
						        $val = trim($val);
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
						        $doc->writeAttribute('xlink:role', 'deity');
						        $doc->writeAttribute('xlink:href', $uri);
						        if($uncertainty == true){
						            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
						        }
						        $doc->text($content['label']);
						        $doc->endElement();
						    }
						}
					//end reverse
					$doc->endElement();
				}
				
				//Type Series should be explicit
				$doc->startElement('typeSeries');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/bigr');
					$doc->text('Bactrian and Indo-Greek Coinage');
				$doc->endElement();
				
				//end typeDesc
				$doc->endElement();
				
				/***** REFDESC *****/			
				if (strlen($row['Bopearachchi ref.']) > 0 || strlen($row['Mitchiner ref.']) > 0 || strlen($row['Matching URI']) > 0){
				    $doc->startElement('refDesc');
    				    if (strlen($row['Bopearachchi ref.'])){
    				        $doc->startElement('reference');
    				            $doc->startElement('tei:title');
    				                $doc->writeAttribute('key', 'http://nomisma.org/id/bopearachchi-1991');
    				                $doc->text('Bopearachchi');
    				            $doc->endElement();
    				            $doc->writeElement('tei:idno', trim($row['Bopearachchi ref.']));
    				        $doc->endElement();
    				    }
    				    if (strlen($row['Mitchiner ref.'])){
    				        $doc->startElement('reference');
        				        $doc->startElement('tei:title');
            				        $doc->writeAttribute('key', 'http://nomisma.org/id/mitchiner-1976');
            				        $doc->text('Mitchiner');
        				        $doc->endElement();
        				        $doc->writeElement('tei:idno', trim($row['Mitchiner ref.']));
    				        $doc->endElement();
    				    }
    				    
    				    if (strlen($row['Matching URI']) > 0){
    				        $uris = explode('|', trim($row['Matching URI']));
    				        
    				        foreach ($uris as $uri){
    				            $uri = trim($uri);
    				            
    				            $doc->startElement('reference');
    				                $doc->writeAttribute('xlink:type', 'simple');
    				                $doc->writeAttribute('xlink:href', $uri);
    				                
    				                $doc->startElement('tei:title');
    				                    $doc->writeAttribute('key', 'http://nomisma.org/id/seleucid_coins');
    				                    $doc->text('Seleucid Coins (part 1)');
    				                $doc->endElement();
    				                $doc->writeElement('tei:idno', str_replace('http://numismatics.org/sco/id/sc.1.', '', $uri));
    				            $doc->endElement();
    				        }
    				    }
    				    
    				//end refDesc
				    $doc->endElement();
				}
				
			//end descMeta
			$doc->endElement();		
		//close NUDS
		$doc->endElement();
		
		//close file
		$doc->endDocument();
		$doc->flush();
	} else {
		echo "No number for {$row['ID']}.\n";
	}
}


 /***** FUNCTIONS *****/
//create the title
function parse_title($recordId){
    $name = '';
    
    $idPieces = explode('.', $recordId);
    
    if (isset($idPieces[4])) {
        $typeNumber = $idPieces[2] . '.' . $idPieces[3] . '.' . $idPieces[4];
    } elseif (isset($idPieces[3])) {
        $typeNumber = $idPieces[2] . '.' . $idPieces[3];
    } else {
        $typeNumber = $idPieces[2];
    }
    
    $authority = $idPieces[1];    
    
    switch ($authority){
        case 'diodotus_i_ii':
            $name = 'Diodotus I or Diodotus II';
            break;
        case 'heliocles_laodice':
            $name = 'Heliocles and Laodice';
            break;
        case 'agathocleia_strato_i':
            $name = 'Strato I and Agathocleia';
            break;
        case 'lysias_antialcidas':
            $name = 'Lysias and Antialcidas';
            break;
        case 'hermaeus_calliope':
            $name = 'Hermaeus and Calliope';
            break;
        case 'strato_ii_iii':
            $name = 'Strato II and Strato III';
            break;
        default:
            $namePieces = explode('_', $authority);
            $newName = array();
            
            foreach ($namePieces as $frag){
                if (preg_match('/^i/', $frag)){
                    $newName[] = str_replace('i', 'I', $frag);
                } else {
                    $newName[] = ucfirst($frag);
                }
            }
            
            $name = implode(' ', $newName);
    }
    
    return "Bactrian and Indo-Greek Coinage {$name} {$typeNumber}";
}

//parse symbol text into TEI
function parse_symbol($doc, $text){
    
    $doc->startElement('tei:div');
        $doc->writeAttribute('type', 'edition');
        
        //split into two pieces
        if (strpos($text, ' above ') !== FALSE){
            $positions = explode(' above ', $text);
            
            foreach ($positions as $k=>$pos){
                $pos = trim($pos);
                
                $doc->startElement('tei:ab');
                if ($k == 0) {
                    $rend = 'above';
                } else {
                    $rend = 'below';
                }
                
                $doc->writeAttribute('rend', $rend);
                    parse_horizontal($doc, $pos, 2);
                $doc->endElement();
            }
        } elseif ($text == '[no monogram]') {
            //semantically encode intentional blank space for subtypes
            $doc->startElement('tei:ab');
                $doc->writeElement('tei:space');
            $doc->endElement();
        } elseif ($text == '[unclear]') {
            //semantically encode intentional blank space for subtypes
            $doc->startElement('tei:ab');
                $doc->writeElement('tei:unclear');
            $doc->endElement();
        } else {
            parse_horizontal($doc, $text, 1);
        }
    
    $doc->endElement();
}

//parse segments separated by ||, which signifies side-by-side glyphs
function parse_horizontal ($doc, $text, $level){
    if (strpos($text, '||') !== FALSE){
        $horizontal = explode('||', $text);
        
        foreach ($horizontal as $seg){
            $seg = trim($seg);
            
            if ($level == 1){
                $doc->startElement('tei:ab');
                    parse_conditional($doc, $seg, true);
                $doc->endElement();
            } else {
                $doc->startElement('tei:seg');
                    parse_conditional($doc, $seg, true);
                $doc->endElement();
            }
        }
    } else {
        //no horizontal configuration, so parse ' or '
        parse_conditional($doc, $text, false);
    }
}

//split choices separated by ' or '
function parse_conditional($doc, $text, $parent){
    if (strpos($text, ' or ') !== FALSE){
        $choices = explode(' or ', $text);
        
        //begin choice element
        $doc->startElement('tei:choice');
        foreach ($choices as $choice){
            $choice = trim($choice);
            
            parse_seg($doc, $choice, true);
        }
        $doc->endElement();
    } else {
        parse_seg($doc, $text, $parent);
    }
}

//parse an atomized seg into a monogram glyph, seg, or just cdata
function parse_seg($doc, $seg, $parent){
    
    if (preg_match('/(.*)\s?\((.*)\)$/', $seg, $matches)){
        write_seg_tei($doc, trim($matches[1]), trim($matches[2]), $parent);
    } else {
        write_seg_tei($doc, trim($seg), null, $parent);
    }
}

function write_seg_tei ($doc, $seg, $rend, $parent){
    GLOBAL $monograms;
    GLOBAL $errors;
    
    if (preg_match('/^(monogram\..*)/', $seg, $matches)){
        $id = trim($matches[1]);
        $pieces = explode('.', $id);
        $num = $pieces[2];
        
        switch ($pieces[1]){
            case 'kharoshthi':
                $auth = 'Kharoshthi';
                break;
            case 'antialcidas':
                $auth = 'Antialcidas';
                break;
            case 'apollodotus_ii':
                $auth = 'Apollodotus II';
                break;
            case 'artemidorus':
                $auth = 'Artemidorus';
                break;
            case 'bop':
                $auth = 'Bopearachchi';
                break;
            case 'hermaeus':
                $auth = 'Hermaeus';
                break;
            case 'hippostratus':
                $auth = 'Hippostratus';
                break;
            case 'PMC':
                $auth = 'PMC';
                break;
            case 'tamga':
                $auth = 'Tamga';
                break;
            case 'zoilus_i':
                $auth = 'Zoilus I';
                break;
            default:
                $auth = 'Other';                
        }
        
        //echo "{$id}\n";
        
        if (strpos($id, 'lorber') !== FALSE) {
            $uri = "http://numismatics.org/pco/symbol/" . $id;
        } else {
            $uri = "https://numismatics.org/bigr/symbol/" . $id;
        }
        
        if ($parent == false){
            $doc->startElement('tei:ab');
        }
        //insert a single monogram into an ab, if applicable
        $doc->startElement('tei:am');
            $doc->startElement('tei:g');
                $doc->writeAttribute('type', 'nmo:Monogram');
                if (isset($rend)){
                    if ($rend == '?'){
                        $doc->writeAttribute('rend', 'unclear');
                    } else {
                        $doc->writeAttribute('rend', $rend);
                    }
                }
                
                //validate monogram URI before inserting the ref attribute                
                if (in_array($uri, $monograms)){
                    $doc->writeAttribute('ref', $uri);
                } else {
                    $file_headers = @get_headers($uri);
                    if (strpos($file_headers[0], '200') !== FALSE){
                        $doc->writeAttribute('ref', $uri);
                        $monograms[] = $uri;
                        echo "Found {$uri}.\n";
                    } else {
                        echo "ERROR: {$uri} not found.\n";
                        if (!in_array($uri, $errors)){
                            $errors[] = $uri;
                        }
                    }
                }
                
                if ($auth == 'Kharoshthi'){
                    $doc->text($auth . " " . str_replace('_', '.', $num));
                } else {
                    $doc->text($auth . " Monogram " . str_replace('_', '.', $num));
                }
                
            $doc->endElement();
        $doc->endElement();
        
        if ($parent == false){
            $doc->endElement();
        }
    } else {
        //if there are parent TEI elements, then use tei:seg, otherwise tei:ab (tei:seg cannot appear directly in tei:div)
        
        if ($parent == true){
            if (isset($rend)){
                if ($rend == '?'){
                    $doc->startElement('tei:seg');
                        $doc->writeElement('tei:unclear', $seg);
                    $doc->endElement();
                } else {
                    $doc->startElement('tei:seg');
                        $doc->writeAttribute('rend', $rend);
                        $doc->text($seg);
                    $doc->endElement();
                }
            } else {
                $doc->writeElement('tei:seg', $seg);
            }
            
        } else {
            if (isset($rend)){
                if ($rend == '?'){
                    $doc->startElement('tei:ab');
                        $doc->writeElement('tei:unclear', $seg);
                    $doc->endElement();
                } else {
                    $doc->startElement('tei:ab');
                        $doc->writeAttribute('rend', $rend);
                        $doc->text($seg);
                    $doc->endElement();
                }
            } else {
                $doc->writeElement('tei:ab', $seg);
            }
        }
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
		case 'nmo:Shape':
		    $content['element'] = 'shape';
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
			$content['role'] = 'portrait';
			if (isset($parent)){
				$content['parent'] = $parent;
			}
			break;
		case 'wordnet:Deity':
		    $content['element'] = 'persname';
		    $content['role'] = 'deity';
		    $content['label'] = $label;
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

//normalize integer into human-readable date
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