<?php 

/*****
 * Author: Ethan Gruber
 * Date: October 2022
 * Function: Read Google Sheet and generate NUDS for OCRE
 *****/

//load JSON projects
$projects_json = file_get_contents('projects.json');
$projects = json_decode($projects_json, false);

$projectExists = false;

//require arguments
if (isset($argv[1])){
    foreach ($projects->projects as $project){
        if ($project->name == $argv[1]){
            $projectExists = true;
            
            //if the project has been found, then look for parts            
            if (array_key_exists('parts', $project)){
                if (isset($argv[2])){
                    $partExists = false;
                    
                    foreach ($project->parts as $part){
                        if ($part->name == $argv[2]){
                            $partExists = true;
                            $spreadsheet_url = $part->types;
                            $o_url = $part->o;
                            $r_url = $part->r;
                        }
                    }
                    
                    //output message if there is no part with the second argument
                    if ($partExists == false) {
                        echo "No part matching the second argument has been found.\n";
                    }
                } else {
                    echo "No part has been selected.\n";
                }
            } else {
                $spreadsheet_url = $project->types;
                $o_url = $project->o;
                $r_url = $project->r;
            }
        }
    }
    
    if ($projectExists == false) {
        echo "No project matching the first argument has been found.\n";
    }
} else {
    echo "No arguments set, terminating script.\n";
}

//var_dump($projects);

//if the URLs are set, then proceed
if (isset($spreadsheet_url) && isset($o_url) && isset($r_url)){
    $data = generate_json($spreadsheet_url);
    $obverses = generate_json($o_url);
    $reverses = generate_json($r_url);
    
    //get the eXist-db password from disk
    $eXist_config_path = '/usr/local/projects/numishare/exist-config.xml';
    $eXist_config = simplexml_load_file($eXist_config_path);
    $eXist_url = $eXist_config->url;
    $eXist_credentials = $eXist_config->username . ':' . $eXist_config->password;
    
    $errors = array();
    $nomismaUris = array();
    //$records = array();
    
    $count = 1;
    
    //evaluate an argument for IDs
    
    if (isset($argv[3])){
        $ids = explode('&', $argv[3]);
        
        foreach($data as $row){
            if (in_array($row['ID'], $ids)){
                generate_nuds($row, $count, $spreadsheet_url);
            }
        }
        
    } else {
        echo "Process all\n";
        
        foreach($data as $row){
            
            generate_nuds($row, $count, $spreadsheet_url);
            
            /*if (file_exists($eXist_config_path)) {
                $recordId = trim($row['ID']);
                $filename =  'nuds/' . $recordId . '.xml';
                put_to_exist($filename, $recordId, $eXist_url, $eXist_credentials);
            }*/
        }
    }
    
    
}



//functions
function generate_nuds($row, $count, $spreadsheet){
    GLOBAL $obverses;
    GLOBAL $reverses;
	
	$uri_space = 'http://numismatics.org/ocre/id/';
	
	$recordId = trim($row['ID']);
	
	
	if (strlen($recordId) > 0){
		echo "Processing {$recordId}\n";
		$doc = new XMLWriter();
		
		$doc->openUri('php://output');
		//$doc->openUri('nuds/' . $recordId . '.xml');
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
    				$doc->text(explode('.', $recordId)[3]);
				$doc->endElement();	
				
				//hierarchy
				if (strlen($row['Parent ID']) > 0){
				    $doc->startElement('otherRecordId');
    				    $doc->writeAttribute('semantic', 'skos:broader');
    				    $doc->text(trim($row['Parent ID']));
				    $doc->endElement();				    
				    $doc->writeElement('publicationStatus', 'approvedSubtype');
				} else {
				    $doc->writeElement('publicationStatus', 'approved');
				}
				
				//handle semantic relation with other record
				if (strlen($row['Deprecated ID']) > 0){
				    $replaces = explode('|', $row['Deprecated ID']);
				    
				    foreach ($replaces as $deprecatedID){
				        $deprecatedID = trim($deprecatedID);				    
				        $doc->startElement('otherRecordId');
        				    $doc->writeAttribute('semantic', 'dcterms:replaces');
        				    $doc->text($deprecatedID);
    				    $doc->endElement();
    				    $doc->startElement('otherRecordId');
        				    $doc->writeAttribute('semantic', 'skos:exactMatch');
        				    $doc->text($uri_space . $deprecatedID);
    				    $doc->endElement();
				    }
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
						$doc->writeElement('source', $spreadsheet);
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
    			$doc->text(get_ocre_title($recordId));
			$doc->endElement();
			
			/***** TYPEDESC *****/
			$doc->startElement('typeDesc');
			
				//objectType
    			if (strlen($row['Object Type URI']) > 0){
    			    $vals = explode('|', $row['Object Type URI']);
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
				if (strlen($row['From Date']) > 0 || strlen($row['To Date']) > 0){
					if (($row['From Date'] == $row['To Date']) || (strlen($row['From Date']) > 0 && strlen($row['To Date']) == 0)){
					    if (is_numeric(trim($row['From Date']))){
					        
					        $fromDate = intval(trim($row['From Date']));					        
					        $doc->startElement('date');
    					        $doc->writeAttribute('standardDate', number_pad($fromDate, 4));
    					        $doc->text(get_date_textual($fromDate));
					        $doc->endElement();
					    }
					} else {
						$fromDate = intval(trim($row['From Date']));
						$toDate= intval(trim($row['To Date']));
						
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
    				            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
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
							$doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
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
										$doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
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
				
				if (strlen($row['Obverse Type Code']) > 0) {
				    $key = $row['Obverse Type Code'];
				    
    				$doc->startElement('obverse');
    				
    					//legend
    					if (strlen(trim($row['Obverse Legend'])) > 0){
    					    $legend = trim($row['Obverse Legend']);    					    
    					    
    					    $doc->startElement('legend');
        					    $doc->writeAttribute('scriptCode', 'Latn');
        					    $doc->writeAttribute('xml:lang', 'la');
        					    $doc->text($legend);
    					    $doc->endElement();
    					}
    				
    					//multilingual type descriptions
    					$doc->startElement('type');
        					foreach ($obverses as $desc){
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
    					
    					if (strlen($row['Obverse Symbol']) > 0){
    					    $doc->startElement('symbol');
    					       parse_symbol($doc, trim($row['Obverse Symbol']));
    					    $doc->endElement();
    					}
    					
    					//deity
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
    					        $role = 'portrait';
    					        
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
    					
    				
    				//end obverse
    				$doc->endElement();				
				}
				
				//reverse			
				if (strlen($row['Obverse Type Code']) > 0) {
				    $key = $row['Obverse Type Code'];
				    
    				$doc->startElement('reverse');
    				
    					//legend
    					if (strlen(trim($row['Reverse Legend'])) > 0){
    						$legend = trim($row['Reverse Legend']);							
    						
    						$doc->startElement('legend');
        						$doc->writeAttribute('scriptCode', 'Latn');
        						$doc->writeAttribute('xml:lang', 'la');
        						$doc->text($legend);
    						$doc->endElement();
    					}
    				
    					//multilingual type descriptions
    					$doc->startElement('type');
        					foreach ($reverses as $desc){
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
    					        $role = 'portrait';
    					        
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
    					
    					//deity
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
    					
    					//symbols
    					foreach ($row as $k=>$v){
    					    //reverse symbols are preceded with R:
    					    if (substr($k, 0, 2) == 'R:'){
    					        if (strlen(trim($v)) > 0){
    					            $position = trim(str_replace('R:', '', $k));
    					            $position = strpos($position, '_') !== FALSE ? substr($position, 0, strpos($position, '_')) : $position;
    					            					           
    					            $doc->startElement('symbol');
        					            if ($position == 'officinaMark' || $position == 'mintMark'){
        					                $doc->writeAttribute('localType', $position);
        					            } else {
        					                $doc->writeAttribute('position', $position);
        					            }
        					            parse_symbol($doc, trim($v));
    					            $doc->endElement();
    					            
    					            
    					        }
    					    }
    					}
    					
    				//end reverse
    				$doc->endElement();
				}
				
				//end typeDesc
				$doc->endElement();
				
				/***** REFDESC *****/				
				//create references to previous volumes
				
				if (strlen($row['Deprecated ID']) > 0){
				    $replaces = explode('|', $row['Deprecated ID']);
				    $doc->startElement('refDesc');
    				    foreach ($replaces as $deprecatedID){
    				        $deprecatedID = trim($deprecatedID);
    				        $doc->startElement('reference');
        				        $doc->writeAttribute('xlink:type', 'simple');
        				        $doc->writeAttribute('xlink:href', $uri_space . $deprecatedID);
        				        $doc->text(get_ocre_title($deprecatedID));
    				        $doc->endElement();      
    				    }
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
		echo "No ID.\n";
	}
}


/***** FUNCTIONS *****/
function put_to_exist($filename, $recordId, $eXist_url, $eXist_credentials){
    if (($readFile = fopen($filename, 'r')) === FALSE){
        echo "Unable to read {$recordId}.xml\n";
    } else {
        //PUT xml to eXist
        $putToExist=curl_init();
        
        //set curl opts
        curl_setopt($putToExist,CURLOPT_URL, $eXist_url . 'ocre/objects/' . $recordId . '.xml');
        curl_setopt($putToExist,CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=utf-8"));
        curl_setopt($putToExist,CURLOPT_CONNECTTIMEOUT,2);
        curl_setopt($putToExist,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($putToExist,CURLOPT_PUT,1);
        curl_setopt($putToExist,CURLOPT_INFILESIZE,filesize($filename));
        curl_setopt($putToExist,CURLOPT_INFILE,$readFile);
        curl_setopt($putToExist,CURLOPT_USERPWD,$eXist_credentials);
        $response = curl_exec($putToExist);
        
        $http_code = curl_getinfo($putToExist,CURLINFO_HTTP_CODE);
        
        //error and success logging
        if (curl_error($putToExist) === FALSE){
            echo "{$recordId} failed to write to eXist.\n";
        }
        else {
            if ($http_code == '201'){
                echo "{$recordId} written.\n";
            }
        }
        //close eXist curl
        curl_close($putToExist);
        
        //close files and delete from /tmp
        fclose($readFile);
        //unlink($filename);
    }
}

/***** FUNCTIONS FOR PROCESSING SYMBOLS INTO EPIDOC TEI *****/
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
                parse_split($doc, $pos, 2);
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
        parse_split($doc, $text, 1);
    }
    
    $doc->endElement();
}

//parse segments separated by ||, a high-level conditional split between options
function parse_split ($doc, $text, $level){
    if (strpos($text, '||') !== FALSE){
        $choices = explode('||', $text);
        
        //begin choice element
        $doc->startElement('tei:choice');
            foreach ($choices as $choice){
                $choice = trim($choice);            
                parse_horizontal($doc, $choice, $level);
            }
        $doc->endElement();
    } else {
        parse_horizontal($doc, $text, $level);
    }
}

//parse segments separated by |, which signifies side-by-side glyphs
function parse_horizontal ($doc, $text, $level){
    if (strpos($text, '|') !== FALSE){
        $horizontal = explode('|', $text);
        
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
    GLOBAL $errors;
    
    if (preg_match('/^(https?:\/\/.*)/', $seg, $matches)){
        $uri = trim($matches[1]);
               
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
            $content = processUri($uri);
            
            $doc->writeAttribute('ref', $uri);
            $doc->text($content['label']);
            
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

//parse the ID sequence to create a title
function get_ocre_title($recordId){
    $pieces = explode('.', $recordId);
    switch ($pieces[1]) {
        case '1':
            $vol = 'I';
            break;
        case '1(2)':
            $vol = 'I (second edition)';
            break;
        case '2':
            $vol = 'II';
            break;
        case '2_1(2)':
            $vol = 'II, Part 1 (second edition)';
            break;
        case '2_3(2)':
            $vol = 'II, Part 3 (second edition)';
            break;
        case '3':
            $vol = 'III';
            break;
        case '4':
            $vol = 'IV';
            break;
        case '5':
            $vol = 'V';
            break;
        case '6':
            $vol = 'VI';
            break;
        case '7':
            $vol = 'VII';
            break;
        case '8':
            $vol = 'VIII';
            break;
        case '9':
            $vol = 'IX';
            break;
        case '10':
            $vol = 'X';
            break;
    }
    
    switch ($pieces[2]) {
        case 'aug':
            $auth = 'Augustus';
            break;
        case 'tib':
            $auth = 'Tiberius';
            break;
        case 'gai':
            $auth = 'Gaius/Caligula';
            break;
        case 'cl':
            $auth = 'Claudius';
            break;
        case 'ner':
            if ($pieces[1] == '1(2)'){
                $auth = 'Nero';
            } else if ($pieces[1] == '2'){
                $auth = 'Nerva';
            }
            break;
        case 'clm':
            $auth = 'Clodius Macer';
            break;
        case 'cw':
            $auth = 'Civil Wars';
            break;
        case 'gal':
            $auth = 'Galba';
            break;
        case 'ot':
            $auth = 'Otho';
            break;
        case 'vit':
            $auth = 'Vitellius';
            break;
        case 'ves':
            $auth = 'Vespasian';
            break;
        case 'tit':
            $auth = 'Titus';
            break;
        case 'dom':
            $auth = 'Domitian';
            break;
        case 'anys':
            $auth = 'Anonymous';
            break;
        case 'tr':
            $auth = 'Trajan';
            break;
        case 'hdn':
            $auth = 'Hadrian';
            break;
        case 'ant':
            $auth = 'Antoninus Pius';
            break;
        case 'm_aur':
            $auth = 'Marcus Aurelius';
            break;
        case 'com':
            $auth = 'Commodus';
            break;
        case 'pert':
            $auth = 'Pertinax';
            break;
        case 'dj':
            $auth = 'Didius Julianus';
            break;
        case 'pn':
            $auth = 'Pescennius Niger';
            break;
        case 'ca':
            $auth = 'Clodius Albinus';
            break;
        case 'ss':
            $auth = 'Septimius Severus';
            break;
        case 'crl':
            $auth = 'Caracalla';
            break;
        case 'ge':
            $auth = 'Geta';
            break;
        case 'mcs':
            $auth = 'Macrinus';
            break;
        case 'el':
            $auth = 'Elagabalus';
            break;
        case 'sa':
            $auth = 'Severus Alexander';
            break;
        case 'max_i':
            $auth = 'Maximinus Thrax';
            break;
        case 'pa':
            $auth = 'Caecilia Paulina';
            break;
        case 'mxs':
            $auth = 'Maximus';
            break;
        case 'gor_i':
            $auth = 'Gordian I';
            break;
        case 'gor_ii':
            $auth = 'Gordian II';
            break;
        case 'balb':
            $auth = 'Balbinus';
            break;
        case 'pup':
            $auth = 'Pupienus';
            break;
        case 'gor_iii_caes':
            $auth = 'Gordian III (Caesar)';
            break;
        case 'gor_iii':
            $auth = 'Gordian III';
            break;
        case 'ph_i':
            $auth = 'Philip I';
            break;
        case 'pac':
            $auth = 'Pacatianus';
            break;
        case 'jot':
            $auth = 'Jotapianus';
            break;
        case 'mar_s':
            $auth = 'Mar. Silbannacus';
            break;
        case 'spon':
            $auth = 'Sponsianus';
            break;
        case 'tr_d':
            $auth = 'Trajan Decius';
            break;
        case 'tr_g':
            $auth = 'Trebonianus Gallus';
            break;
        case 'vo':
            $auth = 'Volusian';
            break;
        case 'aem':
            $auth = 'Aemilian';
            break;
        case 'uran_ant':
            $auth = 'Uranius Antoninus';
            break;
        case 'val_i':
            $auth = 'Valerian';
            break;
        case 'val_i-gall':
            $auth = 'Valerian and Gallienus';
            break;
        case 'val_i-gall-val_ii-sala':
            $auth = 'Valerian, Gallienus, Valerian II, and Salonina';
            break;
        case 'marin':
            $auth = 'Mariniana';
            break;
        case 'gall(1)':
            $auth = 'Gallienus (joint reign)';
            break;
        case 'gall_sala(1)':
            $auth = 'Gallienus and Salonina';
            break;
        case 'gall_sals':
            $auth = 'Gallienus and Saloninus';
            break;
        case 'sala(1)':
            $auth = 'Salonina';
            break;
        case 'val_ii':
            $auth = 'Valerian II';
            break;
        case 'sals':
            $auth = 'Saloninus';
            break;
        case 'qjg':
            $auth = 'Quintus Julius Gallienus';
            break;
        case 'gall(2)':
            $auth = 'Gallienus';
            break;
        case 'gall_sala(2)':
            $auth = 'Gallienus and Salonina (2)';
            break;
        case 'sala(2)':
            $auth = 'Salonina (2)';
            break;
        case 'cg':
            $auth = 'Claudius Gothicus';
            break;
        case 'qu':
            $auth = 'Quintillus';
            break;
        case 'aur':
            $auth = 'Aurelian';
            break;
        case 'aur_seva':
            $auth = 'Aurelian and Severina';
            break;
        case 'seva':
            $auth = 'Severina';
            break;
        case 'tac':
            $auth = 'Tacitus';
            break;
        case 'fl':
            $auth = 'Florian';
            break;
        case 'intr':
            $auth = 'Anonymous';
            break;
        case 'pro':
            $auth = 'Probus';
            break;
        case 'car':
            $auth = 'Carus';
            break;
        case 'dio':
            $auth = 'Diocletian';
            break;
        case 'post':
            $auth = 'Postumus';
            break;
        case 'lae':
            $auth = 'Laelianus';
            break;
        case 'mar':
            $auth = 'Marius';
            break;
        case 'vict':
            $auth = 'Victorinus';
            break;
        case 'tet_i':
            $auth = 'Tetricus I';
            break;
        case 'cara':
            $auth = 'Carausius';
            break;
        case 'cara-dio-max_her':
            $auth = 'Carausius issuing for Diocletian/Maximian';
            break;
        case 'all':
            $auth = 'Allectus';
            break;
        case 'mac_ii':
            $auth = 'Macrianus Minor';
            break;
        case 'quit':
            $auth = 'Quietus';
            break;
        case 'zen':
            $auth = 'Zenobia';
            break;
        case 'vab':
            $auth = 'Vabalathus';
            break;
        case 'reg':
            $auth = 'Regalianus';
            break;
        case 'dry':
            $auth = 'Dryantilla';
            break;
        case 'aurl':
            $auth = 'Aureolus';
            break;
        case 'dom_g':
            $auth = 'Domitianus of Gaul';
            break;
        case 'sat':
            $auth = 'Saturninus';
            break;
        case 'bon':
            $auth = 'Bonosus';
            break;
        case 'jul_i':
            $auth = 'Sabinus Julianus';
            break;
        case 'ama':
            $auth = 'Amandus';
            break;
        case 'lon':
            $auth = 'Londinium';
            break;
        case 'tri':
            $auth = 'Treveri';
            break;
        case 'lug':
            $auth = 'Lugdunum';
            break;
        case 'tic':
            $auth = 'Ticinum';
            break;
        case 'aq':
            $auth = 'Aquileia';
            break;
        case 'rom':
            $auth = 'Rome';
            break;
        case 'ost':
            $auth = 'Ostia';
            break;
        case 'carth':
            $auth = 'Carthage';
            break;
        case 'sis':
            $auth = 'Siscia';
            break;
        case 'serd':
            $auth = 'Serdica';
            break;
        case 'her':
            $auth = 'Heraclea';
            break;
        case 'nic':
            $auth = 'Nicomedia';
            break;
        case 'cyz':
            $auth = 'Cyzicus';
            break;
        case 'anch':
            $auth = 'Antioch';
            break;
        case 'alex':
            $auth = 'Alexandria';
            break;
        case 'ar':
            $auth = 'Arelate';
            break;
        case 'thes':
            $auth = 'Thessalonica';
            break;
        case 'sir':
            $auth = 'Sirmium';
            break;
        case 'cnp':
            $auth = 'Constantinople';
            break;
        case 'amb':
            $auth = 'Amiens';
            break;
        case 'med':
            $auth = 'Mediolanum';
            break;
        case 'arc_e':
            $auth = 'Arcadius';
            break;
        case 'theo_ii_e':
            $auth = 'Theodosius II (East)';
            break;
        case 'marc_e':
            $auth = 'Marcian';
            break;
        case 'leo_i_e':
            $auth = 'Leo I (East)';
            break;
        case 'leo_ii_e':
            $auth = 'Leo II';
            break;
        case 'leo_ii-zen_e':
            $auth = 'Leo II and Zeno';
            break;
        case 'zeno(1)_e':
            $auth = 'Zeno';
            break;
        case 'bas_e':
            $auth = 'Basiliscus';
            break;
        case 'bas-mar_e':
            $auth = 'Basiliscus and Marcus';
            break;
        case 'zeno(2)_e':
            $auth = 'Zeno (East)';
            break;
        case 'leon_e':
            $auth = 'Leontius';
            break;
        case 'hon_w':
            $auth = 'Honorius';
            break;
        case 'pr_att_w':
            $auth = 'Priscus Attalus';
            break;
        case 'con_iii_w':
            $auth = 'Constantine III';
            break;
        case 'max_barc_w':
            $auth = 'Maximus of Barcelona';
            break;
        case 'jov_w':
            $auth = 'Jovinus';
            break;
        case 'theo_ii_w':
            $auth = 'Theodosius II (West)';
            break;
        case 'joh_w':
            $auth = 'Johannes';
            break;
        case 'valt_iii_w':
            $auth = 'Valentinian III';
            break;
        case 'pet_max_w':
            $auth = 'Petronius Maximus';
            break;
        case 'marc_w':
            $auth = 'Marcian';
            break;
        case 'av_w':
            $auth = 'Avitus';
            break;
        case 'leo_i_w':
            $auth = 'Leo I (West)';
            break;
        case 'maj_w':
            $auth = 'Majorian';
            break;
        case 'lib_sev_w':
            $auth = 'Libius Severus';
            break;
        case 'anth_w':
            $auth = 'Anthemius';
            break;
        case 'oly_w':
            $auth = 'Olybrius';
            break;
        case 'glyc_w':
            $auth = 'Glycereius';
            break;
        case 'jul_nep_w':
            $auth = 'Julius Nepos';
            break;
        case 'bas_w':
            $auth = 'Basilicus';
            break;
        case 'rom_aug_w':
            $auth = 'Romulus Augustulus';
            break;
        case 'odo_w':
            $auth = 'Odoacar';
            break;
        case 'zeno_w':
            $auth = 'Zeno (West)';
            break;
        case 'visi':
            $auth = 'Visigoths';
            break;
        case 'gallia':
            $auth = 'Burgundians or Franks';
            break;
        case 'spa':
            $auth = 'Suevi';
            break;
        case 'afr':
            $auth = 'Non-Imperial African';
            break;
    }
    
    if (strpos($pieces[3], '_') === FALSE){
        $num = $pieces[3];
    } else {
        $tokens = explode('_', $pieces[3]);
        $num = $tokens[0];
        unset($tokens[0]);
        $num .= ' (' . implode(' ', $tokens) . ')';
    }
    
    //subtypes
    $subtype = '';
    if (isset($pieces[4])){
        $subtype = ': Subtype ' . $pieces[4];
    }
    
    $title = 'RIC ' . $vol . ' ' . $auth . ' ' . $num . $subtype;
    return $title;
}

//validate Nomisma or Monogram/Symbol URI and return the label
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
		$file_headers = @get_headers($uri);
		
		//only get RDF if the ID exists
		if (strpos($file_headers[0], '200') !== FALSE){
		    $xmlDoc = new DOMDocument();
		    $xmlDoc->load($uri . '.rdf');
		    $xpath = new DOMXpath($xmlDoc);
		    $xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
		    $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
		    $xpath->registerNamespace('org', 'http://www.w3.org/ns/org#');
		    $type = $xpath->query("/rdf:RDF/*")->item(0)->nodeName;
		    $label = $xpath->query("descendant::skos:prefLabel[@xml:lang='en']")->item(0)->nodeValue;
		    
		    if (!isset($label)){
		        echo "Error with {$uri}\n";
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
		    $nomismaUris[$uri] = array('label'=>$uri,'type'=>null);
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
	    case 'nmo:Monogram':
	    case 'crm:E37_Mark':
	        $content['element'] = 'symbol';
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

function get_date_textual($year){
    $textual_date = '';
    //display start date
    if($year < 0){
        $textual_date .= abs($year) . ' BCE';
    } elseif ($year > 0) {        
        $textual_date .= $year;
        if ($year <= 600){
            $textual_date .= ' CE';
        }
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