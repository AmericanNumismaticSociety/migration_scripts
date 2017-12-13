<?php 
 /*****
 * Author: Ethan Gruber
 * Date: December 2017
 * Function: Process the Seleucid Coins Online spreadsheet from Google Drive into two sets of NUDS/XML documents:
 * 1. SCO version 2 
 * 2. SC version 1 that points to the new URI
 *****/

$data = generate_json('sco.csv');
$deities = generate_json('https://docs.google.com/spreadsheet/pub?hl=en_US&hl=en_US&key=0Avp6BVZhfwHAdHk2ZXBuX0RYMEZzUlNJUkZOLXRUTmc&single=true&gid=0&output=csv');

$nomismaUris = array();
//$records = array();

foreach($data as $row){
	//call generate_nuds twice to generate two sets of NUDS records
	generate_nuds($row, $recordIdKey='ID', $mode='new');
}

//functions
function generate_nuds($row, $recordIdKey, $mode){
	GLOBAL $deities;
	
	$recordId = trim($row[$recordIdKey]);
	
	if (strlen($recordId) > 0){
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
				if ($mode == 'new'){
					if (strlen($row['SC no.']) > 0){
						$doc->startElement('otherRecordId');
							$doc->writeAttribute('semantic', 'dcterms:replaces');
							$doc->text($row['SC no.']);
						$doc->endElement();
					}
					$doc->writeElement('publicationStatus', 'approved');
					$doc->writeElement('maintenanceStatus', 'derived');
					$doc->startElement('maintenanceAgency');
						$doc->writeElement('agencyName', 'American Numismatic Society');
					$doc->endElement();
				} else {
					if (strlen($row['SC no.']) > 0){
						$doc->startElement('otherRecordId');						
							$doc->writeAttribute('semantic', 'dcterms:isReplacedBy');
							$doc->text($row['ID']);
						$doc->endElement();
					}
					$doc->writeElement('publicationStatus', 'inProcess');					
					$doc->writeElement('maintenanceStatus', 'cancelledReplaced');
					$doc->startElement('maintenanceAgency');
						$doc->writeElement('agencyName', 'American Numismatic Society');
					$doc->endElement();
				}
				
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
						$doc->writeElement('eventDescription', 'Generated from CSV fro Google Drive.');
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
			//end control
			$doc->endElement();
		
			//start descMeta
			$doc->startElement('descMeta');
		
			//title
			if ($mode == 'new'){
				$doc->startElement('title');
					$doc->writeAttribute('xml:lang', 'en');
					$doc->text('Seleucid Coins v2 '. str_replace('sc.2.', '', $recordId));
				$doc->endElement();
			} else {
				$doc->startElement('title');
					$doc->writeAttribute('xml:lang', 'en');
					$doc->text('Seleucid Coins (part 1) '. str_replace('sc.1.', '', $recordId));
				$doc->endElement();
			}
			
			/***** NOTES *****/
			if (strlen(trim($row['Mint Note'])) > 0){
				$doc->startElement('noteSet');
					$doc->startElement('note');
						$doc->writeAttribute('xml:lang', 'en');
						$doc->text(trim($row['Mint Note']));
					$doc->endElement();
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
						//ascertain whether or not the date is a range
						if (strpos(trim($row['Start Date']), '/') !== FALSE){
							$pieces = explode('/', trim($row['Start Date']));
							$fromDate = intval($pieces[0]) * -1;
							if (strlen($pieces[1]) == 3){
								//full three-digit year
								$toDate = intval($pieces[1]) * -1;
							} elseif (strlen($pieces[1]) == 2){
								$toDate = intval(substr($pieces[0], 0, 1) . $pieces[1]) * -1;
							} elseif (strlen($pieces[1]) == 1){
								$toDate = intval(substr($pieces[0], 0, 2) . $pieces[1]) * -1;
							}
							
							//only write date if both are integers
							if (is_int($fromDate) && is_int($toDate)){
								$doc->startElement('dateRange');
									$doc->startElement('fromDate');
										$doc->writeAttribute('standardDate', number_pad($fromDate, 4));
										$doc->text(abs($fromDate) . ' B.C.');
									$doc->endElement();
									$doc->startElement('toDate');
										$doc->writeAttribute('standardDate', number_pad($toDate, 4));
										$doc->text(abs($toDate) . ' B.C.');
									$doc->endElement();
								$doc->endElement();
							}							
						} elseif (is_numeric(trim($row['Start Date']))){
							$fromDate = intval(trim($row['Start Date'])) * -1;
							
							$doc->startElement('date');
								$doc->writeAttribute('standardDate', number_pad($fromDate, 4));
								$doc->text(trim($row['Start Date']) . ' B.C.');
							$doc->endElement();
						}
					} else {
						//ascertain whether or not the date is a range
						if (strpos(trim($row['Start Date']), '/') !== FALSE){
							$fromDate = intval(substr(trim($row['Start Date']), 0, strpos(trim($row['Start Date']), '/'))) * -1;
						} elseif (is_numeric(trim($row['Start Date']))){
							$fromDate = intval(trim($row['Start Date'])) * -1;
						}
						
						//ascertain whether or not the date is a range
						if (strpos(trim($row['End Date']), '/') !== FALSE){
							$pieces = explode('/', trim($row['End Date']));
							
							if (strlen($pieces[1]) == 3){
								//full three-digit year
								$toDate = intval($pieces[1]) * -1;
							} elseif (strlen($pieces[1]) == 2){
								$toDate = intval(substr($pieces[0], 0, 1) . $pieces[1]) * -1;
							} elseif (strlen($pieces[1]) == 1){
								$toDate = intval(substr($pieces[0], 0, 2) . $pieces[1]) * -1;
							}
						} elseif (is_numeric(trim($row['End Date']))){
							$toDate= intval(trim($row['End Date'])) * -1;
						}
						
						//only write date if both are integers
						if (is_int($fromDate) && is_int($toDate)){
							$doc->startElement('dateRange');
								$doc->startElement('fromDate');
									$doc->writeAttribute('standardDate', number_pad($fromDate, 4));
									$doc->text(trim($row['Start Date']) . ' B.C.');
								$doc->endElement();
								$doc->startElement('toDate');
									$doc->writeAttribute('standardDate', number_pad($toDate, 4));
									$doc->text(trim($row['End Date']) . ' B.C.');
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
				
				//manufacture
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
				if (strlen($row['O (en)']) > 0){
					//$key = trim($row['O']);
					//$type = '';
					$type = trim($row['O (en)']);
					
					$doc->startElement('obverse');
						/*$doc->startElement('type');
						foreach ($stylesheet as $desc){
							if ($desc['Abbreviation'] == $key){
								$type = $desc['en'];
								foreach ($desc as $k=>$v){
									if ($k != 'Abbreviation'){
										$doc->startElement('description');
										$doc->writeAttribute('xml:lang', $k);
										$doc->text(trim($v));
										$doc->endElement();
									}
								}
								break;
							}
						}
						$doc->endElement();*/
						$doc->startElement('type');
							$doc->startElement('description');
								$doc->writeAttribute('xml:lang', 'en');
								$doc->text(trim($row['O (en)']));
							$doc->endElement();
						$doc->endElement();
						
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
						
						//symbols
						if (strlen($row['OBV']) > 0){
							$doc->writeElement('symbol', trim($row['OBV']));
						}
					
					//end obverse
					$doc->endElement();
				}
				
				//reverse
				if (strlen($row['R (en)']) > 0){
					//$key = trim($row['O']);
					//$type = '';
					$type = trim($row['R (en)']);
					
					$doc->startElement('reverse');
					/*$doc->startElement('type');
					 foreach ($stylesheet as $desc){
					 if ($desc['Abbreviation'] == $key){
					 $type = $desc['en'];
					 foreach ($desc as $k=>$v){
					 if ($k != 'Abbreviation'){
					 $doc->startElement('description');
					 $doc->writeAttribute('xml:lang', $k);
					 $doc->text(trim($v));
					 $doc->endElement();
					 }
					 }
					 break;
					 }
					 }
					 $doc->endElement();*/
					
						//legend
						if (strlen(trim($row['R Legend'])) > 0){
							$legend = trim($row['R Legend']);
							
							
							$doc->startElement('legend');
							//get Script Code
							if (strlen($row['R Script']) > 0){
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
						
						$doc->startElement('type');
							$doc->startElement('description');
								$doc->writeAttribute('xml:lang', 'en');
								$doc->text($type);
							$doc->endElement();
						$doc->endElement();
						
						//symbols
						foreach ($row as $k=>$v){
							//reverse symbols are preceded with R:
							if (substr($k, 0, 2) == 'R:'){
								if (strlen(trim($v)) > 0){
									$position = str_replace('R: ', '', $k);
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
					if ($mode == 'new'){						
						$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/seleucid_coins_online');
						$doc->text('Seleucid Coins Online');
					} else {
						$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/seleucid_coins');
						$doc->text('Seleucid Coins');
					}
				$doc->endElement();
				
				//end typeDesc
				$doc->endElement();
			//end descMeta
			$doc->endElement();		
		//close NUDS
		$doc->endElement();
		
		//close file
		$writer->endDocument();
		$writer->flush();
	}	
}


 /***** FUNCTIONS *****/
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