<?php 
 /*****
 * Author: Ethan Gruber
 * Date: May 2020
 * Function: Process the Seleucid Coins Online spreadsheet from Google Drive into NUDS/XML. EpiDoc TEI used for complex symbol placement
 *****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vR-U8FG5i6BSlYyWUqEYMi7SmVtLv1YhYD1siB0nlKvAyjQxMU2HsDytmwlOQvr1dh0R6Px8lJGQ_Hh/pub?gid=79107715&single=true&output=csv');
$deities = generate_json('https://docs.google.com/spreadsheet/pub?hl=en_US&hl=en_US&key=0Avp6BVZhfwHAdHk2ZXBuX0RYMEZzUlNJUkZOLXRUTmc&single=true&gid=0&output=csv');
$stylesheet = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vS0CuQ4VWS_K7NNKhPfm9Km2yEggDjBgw0TH0xJRNTf5BUHeiA3Ol_SNU_CHZl10KFHXstdTSie81xC/pub?gid=1557628448&single=true&output=csv');

$nomismaUris = array();
//$records = array();

$part = 1;
$count = 1;

foreach($data as $row){
    
    if ($row['Type Number'] == '1296'){
        $part = 2;
    }
	generate_nuds($row, $part, $count);
	
	if (strlen($row['Parent ID']) == 0){
	    $count++;
	}
}

//functions
function generate_nuds($row, $part, $count){
	GLOBAL $deities;
	GLOBAL $stylesheet;
	
	$uri_space = 'http://numismatics.org/sco/id/';
	
	$recordId = trim($row['SC no.']);
	
	
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
				
				//insert typeNumber just to capture the num.
				$doc->startElement('otherRecordId');
					$doc->writeAttribute('localType', 'typeNumber');
					$doc->text(str_replace('sc.1.', '', $recordId));
				$doc->endElement();	
				
				//handle semantic relation with other record
				if (strlen($row['Deprecated ID']) > 0){
				    $doc->startElement('otherRecordId');
    				    $doc->writeAttribute('semantic', 'dcterms:replaces');
    				    $doc->text(trim($row['Deprecated ID']));
    				$doc->endElement();
    				$doc->startElement('otherRecordId');
    				    $doc->writeAttribute('semantic', 'skos:exactMatch');
    				    $doc->text($uri_space . trim($row['Deprecated ID']));
				    $doc->endElement();
				}
				
				//PELLA concordance
				if (strlen($row['Price URI']) > 0){
				    $doc->startElement('otherRecordId');
    				    $doc->writeAttribute('semantic', 'skos:exactMatch');
    				    $doc->text(trim($row['Price URI']));
				    $doc->endElement();
				}
				
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
				    
				    $count++;
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
						$doc->writeElement('eventDescription', 'Generated from CSV from ANS Curatorial Google Drive.');
					$doc->endElement();
				$doc->endElement();
				
				//rightsStmt
				$doc->startElement('rightsStmt');
					$doc->writeElement('copyrightHolder', 'American Numismatic Society');
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
    			$doc->text('Seleucid Coins (part ' . $part . ') ' . str_replace('sc.1.', '', $recordId));
			$doc->endElement();
			
			/***** NOTES *****/
			if (strlen(trim($row['Mint Note'])) > 0 || strlen(trim($row['Note'])) > 0){
				$doc->startElement('noteSet');
				if (strlen(trim($row['Mint Note'])) > 0){
					$doc->startElement('note');
						$doc->writeAttribute('xml:lang', 'en');
						$doc->text(trim($row['Mint Note']));
					$doc->endElement();
				}
				if (strlen(trim($row['Note'])) > 0){
					$doc->startElement('note');
						$doc->writeAttribute('xml:lang', 'en');
						$doc->text(trim($row['Note']));
					$doc->endElement();
				}
				$doc->endElement();
			}
			
			/***** TYPEDESC *****/
			$doc->startElement('typeDesc');
			
				//objectType
				$doc->startElement('objectType');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/coin');
					$doc->text('Coin');
				$doc->endElement();
				
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
							$doc->writeAttribute('certainty', 'uncertain');
						}
						$doc->text($content['label']);
						$doc->endElement();
					}
				}
				
				//manufacture: if the SC no. includes P, it is plated, otherwise struck
				if (strpos($row['SC no.'], 'P') !== FALSE){
					$doc->startElement('manufacture');
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/plated');
						$doc->text('Plated');
					$doc->endElement();
				} else {
					$doc->startElement('manufacture');
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/struck');
						$doc->text('Struck');
					$doc->endElement();
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
				if (strlen($row['Authority URI']) > 0 || strlen($row['Stated Authority URI']) > 0 || strlen($row['Issuer URI']) > 0){
					$doc->startElement('authority');
						if (strlen($row['Authority URI']) > 0){
							$vals = explode('|', $row['Authority URI']);
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
								$role = 'authority';
								
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
						if (strlen($row['Stated Authority URI']) > 0){
							$vals = explode('|', $row['Stated Authority URI']);
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
								$role = 'statedAuthority';
								
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
						if (strlen($row['Issuer URI']) > 0){
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
										$doc->writeAttribute('certainty', 'uncertain');
									}
									$doc->text($content['label']);
								$doc->endElement();
							}
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
						    $uri =  $val;
						    $uncertainty = (strlen($row['Mint Certainty']) > 0) ? $row['Mint Certainty'] : null;
						    $content = processUri($uri);
							
							$doc->startElement('geogname');
								$doc->writeAttribute('xlink:type', 'simple');
								$doc->writeAttribute('xlink:role', 'mint');
								$doc->writeAttribute('xlink:href', $uri);
								if(isset($uncertainty)){
									$doc->writeAttribute('certainty', $uncertainty);
								}
								$doc->text($content['label']);
							$doc->endElement();
							
							unset($uncertainty);
						}
					}
					
					if (strlen($row['Region URI']) > 0){
						$vals = explode('|', $row['Region URI']);
						foreach ($vals as $val){
							if (substr($val, -2) == '??'){
								$uri = substr($val, 0, -2);
								$uncertainty = 'perhaps';
								$content = processUri($uri);
							} elseif (substr($val, -1) == '?' && substr($val, -2, 1) != '?'){
								$uri = substr($val, 0, -1);
								$uncertainty = 'probably';
								$content = processUri($uri);
							} else {
								$uri =  $val;
								$uncertainty = null;
								$content = processUri($uri);
							}
							
							$doc->startElement('geogname');
								$doc->writeAttribute('xlink:type', 'simple');
								$doc->writeAttribute('xlink:role', 'region');
								$doc->writeAttribute('xlink:href', $uri);
								if(isset($uncertainty)){
									$doc->writeAttribute('certainty', $uncertainty);
								}
								$doc->text($content['label']);
							$doc->endElement();
							
							unset($uncertainty);
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
    					if (strlen(trim($row['O Legend'])) > 0){
    					    $legend = trim($row['O Legend']);    					    
    					    
    					    $doc->startElement('legend');
        					    $doc->writeAttribute('scriptCode', 'Grek');
        					    $doc->writeAttribute('xml:lang', 'grc');
        					    
        					    //evaluate legibility
        					    if ($legend == 'Illegible' || $legend == 'Pseudo-legend' || $legend == 'Uncertain'){
        					        $doc->startElement('tei:div');
        					           $doc->writeAttribute('type', 'edition');
        					           $doc->startElement('tei:gap');
        					               $doc->writeAttribute('reason', 'illegible');
        					           $doc->endElement();
        					        $doc->endElement();
        					    } else {
        					        $doc->text($legend);
        					    }
    					    $doc->endElement();
    					}
					
						//multilingual type descriptions
						$doc->startElement('type');
							foreach ($stylesheet as $desc){
								if ($desc['Abbreviation'] == $key){
									$type = $desc['en'];
									foreach ($desc as $k=>$v){
										if ($k != 'Abbreviation'){
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
						
						//deity
						foreach($deities as $deity){
							if (strstr($deity['name'], ' ') !== FALSE){
								//haystack is string when the deity is multiple words
								$haystack = strtolower(trim($type));
								if (strstr($haystack, strtolower($deity['matches'])) !== FALSE) {
									$doc->startElement('persname');
									$doc->writeAttribute('xlink:type', 'simple');
									$doc->writeAttribute('xlink:role', 'deity');
									if (strlen($deity['bm_uri']) > 0){
										$doc->writeAttribute('xlink:href', $deity['bm_uri']);
									}
									$doc->text($deity['name']);
									$doc->endElement();
								}
							} else {
								//haystack is array
								$string = preg_replace('/[^a-z]+/i', ' ', trim($type));
								$haystack = explode(' ', $string);
								if (in_array($deity['matches'], $haystack)){
									$doc->startElement('persname');
									$doc->writeAttribute('xlink:type', 'simple');
									$doc->writeAttribute('xlink:role', 'deity');
									if (strlen($deity['bm_uri']) > 0){
										$doc->writeAttribute('xlink:href', $deity['bm_uri']);
									}
									$doc->text($deity['name']);
									$doc->endElement();
								}
							}
						}
						
						//symbols
						if (strlen($row['OBV']) > 0){
							$doc->writeElement('symbol', trim($row['OBV']));
						}
						
						//portrait
						if (strlen($row['Portrait URI']) > 0){
						    $vals = explode('|', $row['Portrait URI']);
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
						        $role = 'portrait';
						        
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
				} else {
					echo "Error: no obverse code for {$recordId}/\n";
				}
				
				//reverse
				if (strlen($row['R']) > 0){
					$key = trim($row['R']);
					$type = '';
					
					$doc->startElement('reverse');
					
						//legend
						if (strlen(trim($row['R Legend'])) > 0 || strlen(trim($row['R PhoenicianLegend'])) > 0){
							$legend = trim($row['R Legend']);							
							
							$doc->startElement('legend');
							
							//evaluate Phoenician
							if (strlen(trim($row['R PhoenicianLegend'])) > 0){
							    //if there is also a Greek legend, then create a tei:div[@edition] with two tei:div textparts with difference languages
							    if (strlen(trim($row['R Legend'])) > 0){
							        $doc->startElement('tei:div');
    							        $doc->writeAttribute('type', 'edition');
    							        $doc->startElement('tei:div');
    							             $doc->writeAttribute('type', 'textpart');
    							             $doc->writeAttribute('xml:lang', 'grc');
    							             $doc->startElement('tei:ab');
    							                 $doc->text($legend);
    							             $doc->endElement();
    							        $doc->endElement();
    							        $doc->startElement('tei:div');
        							        $doc->writeAttribute('type', 'textpart');
        							        $doc->writeAttribute('xml:lang', 'phn');
        							        $doc->startElement('tei:ab');
        							             $doc->text(trim($row['R PhoenicianLegend']));
        							        $doc->endElement();
        							    $doc->endElement();
							        $doc->endElement();
							    } else {
							        //otherwise insert script and legend directly within the legend element
							        $doc->writeAttribute('scriptCode', 'Phnx');
							        $doc->writeAttribute('xml:lang', 'phn');
							        $doc->text(trim($row['R PhoenicianLegend']));
							    }
							} else {
							    //create the script on the legend element
							    if (strlen($row['R Script']) > 0){
							        $doc->writeAttribute('scriptCode', 'Grek');
							        $doc->writeAttribute('xml:lang', 'grc');
							    }
							    
							    //evaluate legibility
							    if ($legend == 'Illegible' || $legend == 'Pseudo-legend' || $legend == 'Uncertain'){
							        $doc->startElement('tei:div');
    							        $doc->writeAttribute('type', 'edition');
    							        $doc->startElement('tei:gap');
    							          $doc->writeAttribute('reason', 'illegible');
    							        $doc->endElement();
							        $doc->endElement();
							    } else {
							        $doc->text($legend);
							    }
							}
							$doc->endElement();
						}
					
						//multilingual type descriptions
						$doc->startElement('type');
						 foreach ($stylesheet as $desc){
							 if ($desc['Abbreviation'] == $key){
								 $type = $desc['en'];
								 foreach ($desc as $k=>$v){
									 if ($k != 'Abbreviation'){
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
									$position = trim(str_replace('R:', '', $k));
									$doc->startElement('symbol');
    									$doc->writeAttribute('position', $position);
    									
    									//parse the text in the symbol field into TEI fragments, if applicable
    									parse_symbol($doc, trim($v));
    									
									$doc->endElement();
								}
							}
						}	
						
						//deity
						foreach($deities as $deity){
							if (strstr($deity['name'], ' ') !== FALSE){
								//haystack is string when the deity is multiple words
								$haystack = strtolower(trim($type));
								if (strstr($haystack, strtolower($deity['matches'])) !== FALSE) {
									$bm_uri = strlen($deity['bm_uri']) > 0 ? ' xlink:href="' . $deity['bm_uri'] . '"' : '';
									
									$doc->startElement('persname');
									$doc->writeAttribute('xlink:type', 'simple');
									$doc->writeAttribute('xlink:role', 'deity');
									if (strlen($deity['bm_uri']) > 0){
										$doc->writeAttribute('xlink:href', $deity['bm_uri']);
									}
									$doc->text($deity['name']);
									$doc->endElement();
								}
							} else {
								//haystack is array
								$string = preg_replace('/[^a-z]+/i', ' ', trim($type));
								$haystack = explode(' ', $string);
								if (in_array($deity['matches'], $haystack)){
									$doc->startElement('persname');
									$doc->writeAttribute('xlink:type', 'simple');
									$doc->writeAttribute('xlink:role', 'deity');
									if (strlen($deity['bm_uri']) > 0){
										$doc->writeAttribute('xlink:href', $deity['bm_uri']);
									}
									$doc->text($deity['name']);
									$doc->endElement();
								}
							}
						}			
					//end reverse
					$doc->endElement();
				}
				
				//Type Series should be explicit
				$doc->startElement('typeSeries');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/seleucid_coins');
					$doc->text('Seleucid Coins');
				$doc->endElement();
				
				//end typeDesc
				$doc->endElement();
				
				/***** REFDESC *****/				
				/*$doc->startElement('refDesc');
    				$doc->startElement('reference');
    				    $doc->startElement('tei:title');
    				        $doc->writeAttribute('key', 'http://nomisma.org/id/seleucid_coins');
    				        $doc->text('Seleucid Coins (part ' . $part . ')');
    				    $doc->endElement();
    				    $doc->startElement('tei:idno');
    				        $doc->text(str_replace('sc.1.', '', $row['SC no.']));
    				    $doc->endElement();
    				$doc->endElement();
				$doc->endElement();*/
				
			//end descMeta
			$doc->endElement();		
		//close NUDS
		$doc->endElement();
		
		//close file
		$doc->endDocument();
		$doc->flush();
	} else {
		echo "No SC number for {$row['SC no.']}.\n";
	}
}


 /***** FUNCTIONS *****/

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
    
    if (strpos($seg, '.svg') !== FALSE ){
        //insert a single monogram into an ab, if applicable
        if ($parent == false){
            $doc->startElement('tei:ab');
        }
        
        $doc->startElement('tei:am');
            $doc->startElement('tei:g');
                $doc->writeAttribute('type', 'nmo:Monogram');
                $doc->text(explode('.', $seg)[0]);
            $doc->endElement();
        $doc->endElement();
        
        if ($parent == false){
            $doc->endElement();
        }
    } else {
        //if there are parent TEI elements, then use tei:seg, otherwise tei:ab (tei:seg cannot appear directly in tei:div)
        
        if ($parent == true){
            $doc->writeElement('tei:seg', $seg);
        } else {
            $doc->writeElement('tei:ab', $seg);
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
			if ($file_headers[0] == 'HTTP/1.1 200 OK'){
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