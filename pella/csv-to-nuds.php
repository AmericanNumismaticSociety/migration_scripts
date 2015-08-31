<?php 

//CSV arrays
$data = generate_json('pella.csv');
$stylesheet = generate_json('stylesheet.csv');
$deities = generate_json('https://docs.google.com/spreadsheet/pub?hl=en_US&hl=en_US&key=0Avp6BVZhfwHAdHk2ZXBuX0RYMEZzUlNJUkZOLXRUTmc&single=true&gid=0&output=csv');


$nomismaUris = array();

foreach ($data as $row){
	generate_nuds($row);
	
	//format XML output
	
	/*$dom = new DOMDocument('1.0', 'UTF-8');
	$dom->preserveWhiteSpace = FALSE;
	$dom->formatOutput = TRUE;
	$dom->loadXML($xml);
	echo $dom->saveXML();*/
}


//functions
function generate_nuds($row){
	GLOBAL $stylesheet;
	GLOBAL $deities;
	
	$recordId = $row['Price no.'];

	if ($row['Material'] != 'vacat'){
		
	
	
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
		$doc->writeAttribute('recordType', 'conceptual');
		
		//control
		$doc->startElement('control');
			$doc->writeElement('recordId', $recordId);
			$doc->writeElement('publicationStatus', 'approved');
			$doc->startElement('maintenanceAgency');
				$doc->writeElement('agencyName', 'American Numismatic Society');
			$doc->endElement();
			$doc->writeElement('maintenanceStatus', 'derived');
			
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
		$doc->endElement();
	
		//descMeta
		$doc->startElement('descMeta');
		
		//generate title
		$title = "Price {$recordId}";
		
		
		$doc->startElement('title');
			$doc->writeAttribute('xml:lang', 'en');
			$doc->text($title);
		$doc->endElement();
		
		/***** TYPEDESC *****/
			$doc->startElement('typeDesc');
			
			//objectType
			$doc->startElement('objectType');
				$doc->writeAttribute('xlink:type', 'simple');
				$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/coin');
				$doc->text('Coin');
			$doc->endElement();
			
			//manufacture
			$doc->startElement('manufacture');
				$doc->writeAttribute('xlink:type', 'simple');
				$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/struck');
				$doc->text('Struck');
			$doc->endElement();
			
			if (strlen($row['Material']) > 0){
				$vals = explode('|', $row['Material']);				
				foreach ($vals as $val){
					if (substr($val, -1) == '?'){
						$uri = 'http://nomisma.org/id/' . substr($val, 0, -1);
						$uncertainty = true;
						$content = processUri($uri);
					} else {
						$uri =  'http://nomisma.org/id/' . $val;
						$uncertainty = false;
						$content = processUri($uri);
					}
						
					$doc->startElement($content['element']);
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', $uri);
						$doc->text($content['label']);
					$doc->endElement();
				}
			}
			
			if (strlen($row['Denomination']) > 0){
				$vals = explode('|', $row['Denomination']);
				foreach ($vals as $val){
					$uri = 'http://nomisma.org/id/' . $val;
					$uncertainty = false;
					$content = processUri($uri);
			
					$doc->startElement($content['element']);
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', $uri);
					$doc->text($content['label']);
					$doc->endElement();
				}
			}
			
			if (is_numeric($row['From Date']) && is_numeric($row['To Date'])){
				if ($row['From Date'] == $row['To Date']){
					$doc->startElement('date');
						$doc->writeAttribute('standardDate', number_pad($row['From Date'], 4));
						$doc->text($row['From Date']);
					$doc->endElement();
				} else {
					$doc->startElement('dateRange');
						$doc->startElement('fromDate');
							$doc->writeAttribute('standardDate', number_pad($row['From Date'], 4));
							$doc->text($row['From Date']);
						$doc->endElement();
						$doc->startElement('toDate');
							$doc->writeAttribute('standardDate', number_pad($row['To Date'], 4));
							$doc->text($row['To Date']);
						$doc->endElement();
					$doc->endElement();
				}
			}
			
			//authority
			if (strlen($row['Authority']) > 0 || strlen($row['Stated authority']) > 0 || strlen($row['Magistrate ID 1']) > 0 || strlen($row['Magistrate ID 2']) > 0){
				$doc->startElement('authority');
				if (strlen($row['Authority']) > 0){
					$vals = explode('|', $row['Authority']);
					foreach ($vals as $val){
						$uri = 'http://nomisma.org/id/' . $val;
						$uncertainty = false;
						$content = processUri($uri);
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
				} elseif (strlen($row['Mint ID']) > 0){
					$vals = explode('|', $row['Mint ID']);
					foreach ($vals as $val){
						$uri = 'http://nomisma.org/id/' . $val;
						$uncertainty = (strtolower(trim($row['Mint Uncertain'])) == 'true' ? true : false);
						$content = processUri($uri);
					
						$doc->startElement('corpname');
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:role', 'authority');
						$doc->writeAttribute('xlink:href', $uri);
						if($uncertainty == true){
							$doc->writeAttribute('certainty', 'uncertain');
						}
						$doc->text($content['label']);
						$doc->endElement();
					} 
				}
				
				//magistrates
				if (strlen($row['Magistrate ID 1']) > 0){
					$vals = explode('|', $row['Magistrate ID 1']);
					foreach ($vals as $val){
						$uri = 'http://nomisma.org/id/' . $val;
						$uncertainty = false;
						$content = processUri($uri);
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
				if (strlen($row['Magistrate ID 2']) > 0){
					$vals = explode('|', $row['Magistrate ID 2']);
					foreach ($vals as $val){
						$uri = 'http://nomisma.org/id/' . $val;
						$uncertainty = false;
						$content = processUri($uri);
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
				$doc->endElement();
			}
			
			//geography
			//mint
			if (strlen($row['Mint ID']) > 0 || strlen($row['Region']) > 0){
				$doc->startElement('geographic');
				if (strlen($row['Mint ID']) > 0){
					$vals = explode('|', $row['Mint ID']);
					foreach ($vals as $val){
						$uri = 'http://nomisma.org/id/' . $val;
						$uncertainty = (strtolower(trim($row['Mint Uncertain'])) == 'true' ? true : false);
						$content = processUri($uri);
				
						$doc->startElement('geogname');
							$doc->writeAttribute('xlink:type', 'simple');
							$doc->writeAttribute('xlink:role', 'mint');
							$doc->writeAttribute('xlink:href', $uri);
							if($uncertainty == true){
								$doc->writeAttribute('certainty', 'uncertain');
							}
							$doc->text($content['label']);
						$doc->endElement();
					}
					
					//regions extracted at the point of indexing in Numishare
					/*if (isset($content['parent'])){
						$orgs[] = $content['parent'];
						$parentArray = processUri($content['parent']);
						$role = 'region';
						$doc->startElement($parentArray['element']);
							$doc->writeAttribute('xlink:type', 'simple');
							$doc->writeAttribute('xlink:role', $role);
							$doc->writeAttribute('xlink:href', $content['parent']);
							$doc->text($parentArray['label']);
						$doc->endElement();
					}*/
				}
				if (strlen($row['Region']) > 0){
					$vals = explode('|', $row['Region']);
					foreach ($vals as $val){
						$uri = 'http://nomisma.org/id/' . $val;
						$uncertainty = (strtolower(trim($row['Region Uncertain'])) == 'true' ? true : false);
						$content = processUri($uri);
				
						$doc->startElement($content['element']);
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:role', 'region');
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
			
			//obverse
			if (strlen($row['O']) > 0){
				$doc->startElement('obverse');
				$key = trim($row['O']);
				$doc->startElement('type');
				foreach ($stylesheet as $desc){
					if ($desc['Abbreviation'] == $key){
						foreach ($desc as $k=>$v){
							if ($k != 'Abbreviation'){
								$doc->startElement('description');
									$doc->writeAttribute('xml:lang', $k);
									$doc->text(trim($v));
								$doc->endElement();
							}
						}
						
						//deity
						foreach($deities as $deity){
							if (strstr($deity['name'], ' ') !== FALSE){
								//haystack is string when the deity is multiple words
								$haystack = strtolower(trim($desc['en']));
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
								$string = preg_replace('/[^a-z]+/i', ' ', trim($desc['en']));
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
						
						break;
					}
				}
				$doc->endElement();
			}
			
			//reverse
			if (strlen($row['R']) > 0 || strlen($row['R Legend']) > 0){
				$doc->startElement('reverse');
				$key = trim($row['R']);
				$doc->startElement('type');
				foreach ($stylesheet as $desc){
					if ($desc['Abbreviation'] == $key){
						foreach ($desc as $k=>$v){
							if ($k != 'Abbreviation'){
								$doc->startElement('description');
									$doc->writeAttribute('xml:lang', $k);
									$doc->text(trim($v));
								$doc->endElement();
							}
						}
						
						//deity
						foreach($deities as $deity){
							if (strstr($deity['name'], ' ') !== FALSE){
								//haystack is string when the deity is multiple words
								$haystack = strtolower(trim($desc['en']));
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
								$string = preg_replace('/[^a-z]+/i', ' ', trim($desc['en']));
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
						
						break;
					}
				}
				
				//legend
				if (strlen($row['R Legend']) > 0) {
					$doc->startElement('legend');
						$doc->writeAttribute('scriptCode', 'Grek');
						$doc->text(trim($row['R Legend']));
					$doc->endElement();
				}
				
				
				$doc->endElement();
			}
			
			//end typeDesc
			$doc->endElement();	

			//refDesc
			$doc->startElement('refDesc');
				$doc->startElement('reference');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/price1911');
					$doc->writeAttribute('semantic', 'dcterms:source');
					$doc->text('Price (1991)');
				$doc->endElement();
			$doc->endElement();
			
		//end descMeta
		$doc->endElement();
			
	//end nuds
	$doc->endElement();
	$doc->endDocument();
	
	echo "Writing {$recordId}\n";
	} else {
		"Ignoring vacat: {$recordId}\n";
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
		case 'nmo:Ethnic':
			$content['element'] = 'famname';
			$content['label'] = $label;
			break;
		case 'foaf:Organization':
		case 'foaf:Group':
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
	$array = array();
	$csv = csvToArray($doc, ',');
	// Set number of elements (minus 1 because we shift off the first row)
	$count = count($csv) - 1;
	//Use first row for names
	$labels = array_shift($csv);
	foreach ($labels as $label) {
		$keys[] = $label;
	}
	// Bring it all together
	for ($j = 0; $j < $count; $j++) {
		$d = array_combine($keys, $csv[$j]);
		$array[$j] = $d;
	}
	return $array;
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