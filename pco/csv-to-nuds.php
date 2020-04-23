<?php 
 /*****
 * Author: Ethan Gruber
 * Last modified: December 2018
 * Function: Process the Ptolemaic Coins Online spreadsheet from Google Drive into two sets of NUDS/XML documents:
 * 1. Lorber Coins of the Ptolemaic Empire numbers 
 * 2. Svoronos 1904 numbers that direct to Lorber numbers
 *****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQFHZHiQCr1MNrnZC9Rca1UbpawQDC86E-laoySz4cADKWgQxL0gOGCDJy531HiQQ82xbSroyZpldsl/pub?output=csv');
$deities = generate_json('https://docs.google.com/spreadsheet/pub?hl=en_US&hl=en_US&key=0Avp6BVZhfwHAdHk2ZXBuX0RYMEZzUlNJUkZOLXRUTmc&single=true&gid=0&output=csv');
$stylesheet = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQ9dm5-zYlLzEm_URfzguJhQr_VT0Xt5uh7qAQTK23YKzwNUvyRQO0LgcihP18h4JRTVD41C-6PZf9c/pub?output=csv');

$nomismaUris = array();
//$records = array();

$count = 1;

foreach($data as $row){
	//call generate_nuds twice to generate two sets of NUDS records
	
	if (strpos($row['Date original'], 'vacat') === FALSE){
	    generate_nuds($row, $count);
	    
	    if (strlen($row['Parent ID']) == 0){
	        $count++;
	    }
	}
}

//functions
function generate_nuds($row, $count){
	GLOBAL $deities;
	GLOBAL $stylesheet;
	
	$uri_space = 'http://numismatics.org/pco/id/';	
	$recordId = trim($row['Lorber no.']);
	$typeNumber = explode('.', $recordId)[2];
	
	if (strlen($recordId) > 0){
		echo "Processing {$recordId}\n";
		
		//parse Svoronos IDs
		$svoronosIDs = null;
		if (strlen($row['Svoronos Nr.']) > 0){
		    $svoronosIDs = explode('|', trim($row['Svoronos Nr.']));
		}
		
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
				
				//handle semantic relation with other record
				if (isset($svoronosIDs)){
				    foreach ($svoronosIDs as $id){
				        //only create URIs for Svoronos numbers from the original printed volume
				        if (strpos($id, 'Addenda') === FALSE && strpos($id, '(1890)') === FALSE){
    				        $svoronosID = 'svoronos-1904.' . normalizeID($id);
        				    $doc->startElement('otherRecordId');
        						$doc->writeAttribute('semantic', 'dcterms:replaces');
        						$doc->text($svoronosID);
        					$doc->endElement();
        					$doc->startElement('otherRecordId');
        						$doc->writeAttribute('semantic', 'skos:exactMatch');
        						$doc->text($uri_space . $svoronosID);
        					$doc->endElement();  
				        }
				    }
				}
				
				//Price concordance
				if (strlen($row['Price URI']) > 0){
				    $priceURIs = explode('|', $row['Price URI']);
				    
				    foreach ($priceURIs as $uri){
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
				
				//handle subtype hierarchy
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
			
			$pieces = explode('.', $recordId);
			switch ($pieces[1]){
			    case '1_1':
			        $vol = 'Vol. I, Part 1';
			        break;
			    case '1_2':
			        $vol = 'Vol. I, Part II';
			        break;
			}
			
			$doc->startElement('title');
    			$doc->writeAttribute('xml:lang', 'en');
    			$doc->text('Coins of the Ptolemaic Empire ' . $vol . ', no. '. $pieces[2]);
			$doc->endElement();
			
			/***** NOTES *****/
			if (strlen(trim($row['Note'])) > 0){
				$doc->startElement('noteSet');
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
				if (strlen($row['fromDate']) > 0 || strlen($row['toDate']) > 0){
					if (($row['fromDate'] == $row['toDate']) || (strlen($row['fromDate']) > 0 && strlen($row['toDate']) == 0)){
						//ascertain whether or not the date is a range
						$fromDate = intval(trim($row['fromDate']));
						
						$doc->startElement('date');
							$doc->writeAttribute('standardDate', number_pad($fromDate, 4));
							if (strlen($row['fromDate Certainty']) > 0){
							    $doc->writeAttribute('certainty', 'http://nomisma.org/id/' . $row['fromDate Certainty']);
							}
							$doc->text(get_date_textual($fromDate));
						$doc->endElement();
					} else {
						$fromDate = intval(trim($row['fromDate']));
						$toDate= intval(trim($row['toDate']));
						
						//only write date if both are integers
						if (is_int($fromDate) && is_int($toDate)){
							$doc->startElement('dateRange');
								$doc->startElement('fromDate');
									$doc->writeAttribute('standardDate', number_pad($fromDate, 4));
									if (strlen($row['fromDate Certainty']) > 0){
									    $doc->writeAttribute('certainty', 'http://nomisma.org/id/' . $row['fromDate Certainty']);
									}
									$doc->text(get_date_textual($fromDate));
								$doc->endElement();
								$doc->startElement('toDate');
									$doc->writeAttribute('standardDate', number_pad($toDate, 4));
									if (strlen($row['toDate Certainty']) > 0){
									    $doc->writeAttribute('certainty', 'http://nomisma.org/id/' . $row['toDate Certainty']);
									}
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
							$content = ($uri);
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
				
				//authority
				if (array_key_exists('Authority URI', $row) || array_key_exists('Stated Authority URI', $row) || array_key_exists('Issuer URI', $row)){
					$doc->startElement('authority');
						if (array_key_exists('Authority URI', $row)){
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
									    $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
									}
									$doc->text($content['label']);
								$doc->endElement();
							}
						}
						if (array_key_exists('Stated Authority URI', $row)){
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
									    $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
									}
									$doc->text($content['label']);
								$doc->endElement();
							}
						}
						if (array_key_exists('Issuer URI', $row)){
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
					        
					        unset($uncertainty);
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
							
							unset($uncertainty);
						}
					}
					
					$doc->endElement();
				}
				
				//obverse
				if (strlen($row['O']) > 0){
					$key = trim($row['O']);
					//$type = $row['O (en)'];
					
					$doc->startElement('obverse');					
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
						
						//symbols
						if (strlen($row['OBV']) > 0){
							$doc->writeElement('symbol', trim($row['OBV']));
						}
					
					//end obverse
					$doc->endElement();
				} else {
					echo "Error: no obverse code for {$recordId}/\n";
				}
				
				//reverse
				if (strlen($row['R']) > 0){
					$key = trim($row['R']);					
					
					$doc->startElement('reverse');
					
						//legend
						if (strlen(trim($row['R Legend'])) > 0){
							$legend = trim($row['R Legend']);
							
							
							$doc->startElement('legend');
							//get Script Code
							if (array_key_exists('R Script', $row)){
								switch ($row['R Script']){
									case 'Greek':
										$doc->writeAttribute('scriptCode', 'Grek');
										$doc->writeAttribute('xml:lang', 'grc');
										break;
								}
							}
							
							//evaluate legibility
							if ($legend == 'Illegible'){
								$doc->startElement('tei:div');
									$doc->writeAttribute('type', 'edition');
									$doc->startElement('tei:gap');
										$doc->writeAttribute('reason', 'illegible');
									$doc->endElement();
								$doc->endElement();
							} else {
								$doc->text(trim($row['R Legend']));
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
								    $position = str_replace('R:', '', $k);
								    $position = strpos($position, '_') !== FALSE ? substr($position, 0, strpos($position, '_')) : $position;									
									$doc->startElement('symbol');
									$doc->writeAttribute('position', $position);
									$doc->text(trim($v));
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
					//end reverse
					$doc->endElement();
				}
				
				//Type Series should be explicit
				$doc->startElement('typeSeries');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/coins_ptolemaic_empire');
					$doc->text('Coins of the Ptolemaic Empire');
				$doc->endElement();
				
				//end typeDesc
				$doc->endElement();
				
				/***** REFDESC *****/			
				if (isset($svoronosIDs) || strlen($row['Price URI']) > 0){
				    $doc->startElement('refDesc');
				    
				    if (isset($svoronosIDs)){
    				    foreach ($svoronosIDs as $id){
    				        //create a simple text reference for addenda items
    				        if (strpos($id, 'Addenda') !== FALSE){
    				            $doc->startElement('reference');
        				            $doc->startElement('tei:title');
        				                $doc->text('Svoronos (1904-1908)');
        				            $doc->endElement();
        				            $doc->startElement('tei:idno');
        				                $doc->text($id);
        				            $doc->endElement();
    				            $doc->endElement();    
    				        } elseif (strpos($id, '(1890)') !== FALSE) {
    				           //if it's a reference to the 1890 typology of Cyprus
    				            $doc->startElement('reference');
        				            $doc->startElement('tei:title');
        				            $doc->writeAttribute('key', 'http://nomisma.org/id/svoronos-1890');
        				                $doc->text('Svoronos (1890)');
        				            $doc->endElement();
        				            $doc->startElement('tei:idno');
        				                $doc->text(str_replace('(1890), ', '', $id));
        				            $doc->endElement();
    				            $doc->endElement();    
    				        } else {
    				            //otherwise, link to the URI    				            
        				        $svoronosID = 'svoronos-1904.' . normalizeID($id);
        				        
        				        $doc->startElement('reference');
            				        $doc->writeAttribute('xlink:type', 'simple');
            				        $doc->writeAttribute('xlink:href', $uri_space . $svoronosID);
            				        $doc->startElement('tei:title');
            				            $doc->writeAttribute('key', 'http://nomisma.org/id/svoronos-1904');
            				            $doc->text('Svoronos (1904-1908)');
        				            $doc->endElement();
            				        $doc->startElement('tei:idno');
            				            $doc->text($id);
        				            $doc->endElement();
        				        $doc->endElement();    
    				        }
    				    }    				    
    				}
    				//Price references
    				if (strlen($row['Price URI']) > 0){
    				    $priceURIs = explode('|', $row['Price URI']);
    				    
    				    foreach ($priceURIs as $uri){
    				        $doc->startElement('reference');
        				        $doc->writeAttribute('xlink:type', 'simple');
        				        $doc->writeAttribute('xlink:href', $uri);
        				        $doc->startElement('tei:title');
            				        $doc->writeAttribute('key', 'http://nomisma.org/id/price1991');
            				        $doc->text('Price (1991)');
        				        $doc->endElement();
        				        $doc->startElement('tei:idno');
        				            $doc->text(str_replace('http://numismatics.org/pella/id/price.', '', $uri));
        				        $doc->endElement();
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
		
		//unset $svoronosID
		unset($svoronosIDs);
	} else {
		echo "No number for {$row['ID']}.\n";
	}
}


 /***** FUNCTIONS *****/
//normalize the Svoronos subtype IDs for Greek letters into Latin letters
function normalizeID($id){
    //letters: αβγεζ
    //return strtr($id, 'αβγεζ', 'abcez');
    return str_replace(array('α', 'β', 'γ', 'ε', 'ζ'), array('a','b','g','e','z'), $id);
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