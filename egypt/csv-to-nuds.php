<?php 

$data = generate_json('collection-final.csv');
$files = scandir('/e/egypt-images/media/reference');
$nomismaUris = array();

foreach ($data as $row){
	generate_nuds($row, $files);
	
	//format XML output
	
	/*$dom = new DOMDocument('1.0', 'UTF-8');
	$dom->preserveWhiteSpace = FALSE;
	$dom->formatOutput = TRUE;
	$dom->loadXML($xml);
	echo $dom->saveXML();*/
}


//functions
function generate_nuds($row, $files){
	$recordId = $row['recordId'];
	$orgs = array();
	
	//parse references
	if (strlen($row['reference']) > 0){
		//strip obverse and reverse descriptions out if possible
		preg_match('/(Obverse:?.*)Reverse/', $row['reference'], $obverse);
		preg_match('/(Reverse:?.*)$/', $row['reference'], $reverse);
		if (isset($obverse[1]) || isset($reverse[1])){
			$refText = trim(str_replace($reverse[1], '', str_replace($obverse[1], '', $row['reference'])));
		} else {
			$refText = trim($row['reference']);
		}
		
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
		$doc->writeAttribute('xmlns:mets', 'http://www.loc.gov/METS/');
		$doc->writeAttribute('recordType', 'physical');
		
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
					$doc->writeElement('eventDescription', 'Generated from CSV, after iterative cleanup process (including Open Refine)');
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
		$objectType =  processUri($row['objectType']);
		$auth = array();
		if (strlen($row['authorityPerson']) > 0){
			$auth[] = processUri($row['authorityPerson']);
			$auth[0]['certain'] = true;
		} else if (strlen($row['org']) > 0){
			$vals = explode('|', $row['org']);
			$i = 0;
			foreach ($vals as $val){
				if (substr($val, -1) == '?'){
					$uri = substr($val, 0, -1);
					$auth[] = processUri($uri);
					$auth[$i]['certain'] = false;
				} else {
					$uri = $val;
					$auth[] = processUri($uri);
					$auth[$i]['certain'] = true;
				}
				$i++;
			}
		}
		//english
		$title = $objectType['label'] . ' - ';
		$authFragment = array();
		foreach ($auth as $v){
			$authFragment[] = $v['label'] . ($v['certain'] == false ? '?' : '');
		}
		$title .= implode('/', $authFragment) . ', ';
		if (strlen($row['dob_ah']) > 0){
			$title .= 'AH ' . $row['dob_ah'] . ' / ';
		}
		if (strlen($row['fromDate']) > 0 && strlen($row['toDate']) > 0){
			$title .= 'CE ';
			if ($row['fromDate'] == $row['toDate']){
				$title .= $row['fromDate'];
			} else {
				$title .=  $row['fromDate'] . '-' . $row['toDate'];
			}
		}
		if (strlen($row['dob_ah']) > 0 || (strlen($row['fromDate']) > 0 && strlen($row['toDate']) > 0)){
			$title .= '. ';
		}
		
		$title .= $recordId;
		$doc->startElement('title');
			$doc->writeAttribute('xml:lang', 'en');
			$doc->text($title);	
		$doc->endElement();
		
		//arabic		
		$title = $recordId;
		if (strlen($row['dob_ah']) > 0 || (strlen($row['fromDate']) > 0 && strlen($row['toDate']) > 0)){
			$title .= ' .';
		}
		if (strlen($row['fromDate']) > 0 && strlen($row['toDate']) > 0){			
			if ($row['fromDate'] == $row['toDate']){
				$title .= $row['fromDate'];
			} else {
				$title .=  $row['toDate'] . '-' . $row['fromDate'];
			}
			$title .= ' CE';
		}
		if (strlen($row['dob_ah']) > 0){
			$title .= ' \ ' . $row['dob_ah'];
		}
		$authFragment = array();
		foreach ($auth as $v){
			$authFragment[] = ($v['certain'] == false ? '؟' : '') . $v['ar'];
		}
		$title .= ' ،' . implode('/', $authFragment);
		$title .= ' - ' . $objectType['ar'];
		
		
		$doc->startElement('title');
			$doc->writeAttribute('xml:lang', 'ar');
			$doc->text($title);
		$doc->endElement();
		
		/***** TYPEDESC *****/
			$doc->startElement('typeDesc');
			
			//objectType
			if (strlen($row['objectType']) > 0){
				$vals = explode('|', $row['objectType']);
				foreach ($vals as $val){
					if (substr($val, -1) == '?'){
						$uri = substr($val, 0, -1);
						$uncertainty = true;
						$content = processUri($uri);
					} else {
						$uri = $val;
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
			
			if (strlen($row['material']) > 0){
				$vals = explode('|', $row['material']);
				foreach ($vals as $val){
					if (substr($val, -1) == '?'){
						$uri = substr($val, 0, -1);
						$uncertainty = true;
						$content = processUri($uri);
					} else {
						$uri = $val;
						$uncertainty = false;
						$content = processUri($uri);
					}
						
					$doc->startElement($content['element']);
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', $uri);
					if($uncertainty == true || $row['materialUncertainty'] == 'true'){
						$doc->writeAttribute('certainty', 'uncertain');
					}
					$doc->text($content['label']);
					$doc->endElement();
				}
			}
			
			if (strlen($row['manufacture']) > 0){
				$vals = explode('|', $row['manufacture']);
				foreach ($vals as $val){
					if (substr($val, -1) == '?'){
						$uri = substr($val, 0, -1);
						$uncertainty = true;
						$content = processUri($uri);
					} else {
						$uri = $val;
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
			
			if (is_numeric($row['fromDate']) && is_numeric($row['toDate'])){
				if ($row['fromDate'] == $row['toDate']){
					$doc->startElement('date');
						$doc->writeAttribute('standardDate', number_pad($row['fromDate'], 4));
						$doc->text($row['fromDate']);
					$doc->endElement();
				} else {
					$doc->startElement('dateRange');
						$doc->startElement('fromDate');
							$doc->writeAttribute('standardDate', number_pad($row['fromDate'], 4));
							$doc->text($row['fromDate']);
						$doc->endElement();
						$doc->startElement('toDate');
							$doc->writeAttribute('standardDate', number_pad($row['toDate'], 4));
							$doc->text($row['toDate']);
						$doc->endElement();
					$doc->endElement();
				}
			}
			
			//date on object
			if (strlen($row['dob_ah']) > 0){
				$doc->startElement('dateOnObject');
					$doc->writeAttribute('calendar', 'ah');
					$doc->text($row['dob_ah']);
				$doc->endElement();
			}
			if (strlen($row['dob']) > 0){
				$doc->writeElement('dateOnObject', $row['dob']);
			}
			//authority
			if (strlen($row['authorityPerson']) > 0 || strlen($row['org']) > 0 || strlen($row['otherPerson']) > 0){
				$doc->startElement('authority');
				if (strlen($row['org']) > 0 && (strlen($row['authorityPerson']) == 0 && strlen($row['otherPerson']) == 0)){
					$vals = explode('|', $row['org']);
					foreach ($vals as $val){
						if (substr($val, -1) == '?'){
							$uri = substr($val, 0, -1);
							$uncertainty = true;
							$content = processUri($uri);
						} else {
							$uri = $val;
							$uncertainty = false;
							$content = processUri($uri);
						}
						$role = 'authorizingEntity';
						
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
				if (strlen($row['authorityPerson']) > 0){
					$vals = explode('|', $row['authorityPerson']);
					foreach ($vals as $val){
						if (substr($val, -1) == '?'){
							$uri = substr($val, 0, -1);
							$uncertainty = true;
							$content = processUri($uri);
						} else {
							$uri = $val;
							$uncertainty = false;
							$content = processUri($uri);
						}
							
						$doc->startElement($content['element']);
							$doc->writeAttribute('xlink:type', 'simple');							
							$doc->writeAttribute('xlink:role', 'authority');
							$doc->writeAttribute('xlink:href', $uri);
							if($uncertainty == true){
								$doc->writeAttribute('certainty', 'uncertain');
							}
							$doc->text($content['label']);
						$doc->endElement();
						
						//if there is a dynasty or org
						if (isset($content['parent'])){
							$orgs[] = $content['parent'];
							$parentArray = processUri($content['parent']);
							$role = 'authorizingEntity';
							$doc->startElement($parentArray['element']);
								$doc->writeAttribute('xlink:type', 'simple');								
								$doc->writeAttribute('xlink:role', $role);
								$doc->writeAttribute('xlink:href', $content['parent']);
								$doc->text($parentArray['label']);
							$doc->endElement();
						}
					}
				}
				if (strlen($row['otherPerson']) > 0){
					$vals = explode('|', $row['otherPerson']);
					foreach ($vals as $val){
						if (substr($val, -1) == '?'){
							$uri = substr($val, 0, -1);
							$uncertainty = true;
							$content = processUri($uri);
						} else {
							$uri = $val;
							$uncertainty = false;
							$content = processUri($uri);
						}
							
						$doc->startElement($content['element']);
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', $uri);
						$doc->writeAttribute('xlink:role', 'authority');
						if($uncertainty == true){
							$doc->writeAttribute('certainty', 'uncertain');
						}
						$doc->text($content['label']);
						$doc->endElement();
				
						//if there is a dynasty or org; only insert if there isn't already one
						if (isset($content['parent'])){
							if (!in_array($content['parent'], $orgs)){
								$parentArray = processUri($content['parent']);
								$role = 'authorizingEntity';
								$doc->startElement($parentArray['element']);
									$doc->writeAttribute('xlink:type', 'simple');
									$doc->writeAttribute('xlink:role', $role);
									$doc->writeAttribute('xlink:href', $content['parent']);
									$doc->text($parentArray['label']);
								$doc->endElement();
							}
						}
					}
				}
				$doc->endElement();
			}
			
			//geography
			//mint
			if (strlen($row['mint']) > 0){
				$doc->startElement('geographic');
				$vals = explode('|', $row['mint']);
				foreach ($vals as $val){
					if (substr($val, -1) == '?'){
						$uri = substr($val, 0, -1);
						$uncertainty = true;
						$content = processUri($uri);
					} else {
						$uri = $val;
						$uncertainty = false;
						$content = processUri($uri);
					}
						
					$doc->startElement($content['element']);
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:role', 'mint');
					$doc->writeAttribute('xlink:href', $uri);
					if($uncertainty == true){
						$doc->writeAttribute('certainty', 'uncertain');
					}
					$doc->text($content['label']);
					$doc->endElement();
				}
				$doc->endElement();
			}
			
			//obverse
			if (isset($obverse[1])){
				$doc->startElement('obverse');
					$doc->startElement('type');
						$doc->startElement('description');
							$doc->writeAttribute('xml:lang', 'en');
							$doc->text(trim(str_replace('Obverse:', '', $obverse[1])));
						$doc->endElement();
					$doc->endElement();
				$doc->endElement();
			}
			
			//reverse
			if (isset($reverse[1])){
				$doc->startElement('reverse');
					$doc->startElement('type');
						$doc->startElement('description');
							$doc->writeAttribute('xml:lang', 'en');
							$doc->text(trim(str_replace('Reverse:', '', $reverse[1])));
						$doc->endElement();
					$doc->endElement();
				$doc->endElement();
			}
			
			//end typeDesc
			$doc->endElement();
			
			/***** PHYSDESC *****/
			$doc->startElement('physDesc');
			
			//measurementsSet
			if (is_numeric($row['weight']) || is_numeric($row['diameter']) || (is_numeric($row['height']) && is_numeric($row['width']))){
				$doc->startElement('measurementsSet');
				if (strlen($row['weight']) > 0 && is_numeric($row['weight'])){
					$doc->startElement('weight');
						$doc->writeAttribute('units', 'g');
						$doc->text($row['weight']);
					$doc->endElement();
				}
				if (strlen($row['diameter']) > 0 && is_numeric($row['diameter'])){
					$doc->startElement('diameter');
						$doc->writeAttribute('units', 'mm');
						$doc->text($row['diameter']);
					$doc->endElement();
				}
				if (strlen($row['height']) > 0 && is_numeric($row['height']) && strlen($row['width']) > 0 && is_numeric($row['width'])){
					$doc->startElement('height');
						$doc->writeAttribute('units', 'mm');
						$doc->text($row['height']);
					$doc->endElement();
					$doc->startElement('width');
						$doc->writeAttribute('units', 'mm');
						$doc->text($row['width']);
					$doc->endElement();
				}
				$doc->endElement();
			}
			
			if (strlen($row['authenticity']) > 0){
				$doc->writeElement('authenticity', $row['authenticity']);
			}
			
			
			//end physDesc
			$doc->endElement();
			
			/***** REFDESC *****/
			if (isset($refText)){
				if (strlen($refText) > 0){
					$references = explode(';', $refText);
					
					$doc->startElement('refDesc');
					foreach ($references as $reference){
						$doc->writeElement('reference', trim($reference));
					}
					$doc->endElement();
				}
			}
			
			/***** ADMINDESC *****/
			$doc->startElement('adminDesc');
				$doc->startElement('identifier');
					$doc->writeAttribute('localType', 'catnum');
					$doc->text($recordId);
				$doc->endElement();
				if (strlen($row['regno']) > 0){
					$doc->startElement('identifier');
						$doc->writeAttribute('localType', 'regno');
						$doc->text($row['regno']);
					$doc->endElement();
				}
				if (strlen($row['1887 catalog number']) > 0){
					$doc->startElement('identifier');
						$doc->writeAttribute('localType', '1887catnum');
						$doc->text($row['1887 catalog number']);
					$doc->endElement();
				}
				$doc->startElement('collection');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/egyptian_national_library');
					$doc->text('Egyptian National Library');
				$doc->endElement();
			
			//end adminDesc
			$doc->endElement();
			
			//note
			if (strlen($row['note']) > 0){
				$doc->startElement('noteSet');
					$doc->writeElement('note', $row['note']);
				$doc->endElement();
			}
			
		//end descMeta
		$doc->endElement();
		
		/***** IMAGES *****/
		$doc->startElement('digRep');
			$doc->startElement('mets:fileSec');
				foreach ($files as $file){
					if (strpos($file, '.jpg') !== FALSE){
						$arr = explode('_', str_replace('.jpg', '', $file));
						$id = $arr[0];
						$type = $arr[1];
							
						if ($id == $recordId){
							switch ($type){
								case 'obv':
									$use = 'obverse';
									break;
								case 'rev':
									$use = 'reverse';
									break;
								default:
									$use = $type;
							}
							
							$doc->startElement('mets:fileGrp');
								$doc->writeAttribute('USE', $use);
								
								//reference image
								$doc->startElement('mets:file');
									$doc->writeAttribute('USE', 'reference');
									$doc->writeAttribute('MIMETYPE', 'image/jpeg');
									$doc->startElement('mets:FLocat');
										$doc->writeAttribute('LOCTYPE', 'URL');
										$doc->writeAttribute('xlink:href', 'media/reference/' . $file);
									$doc->endElement();
								$doc->endElement();
								
								//display thumbnail for obverse and reverse images only
								if ($use == 'obverse' || $use == 'reverse'){
									$doc->startElement('mets:file');
										$doc->writeAttribute('USE', 'thumbnail');
										$doc->writeAttribute('MIMETYPE', 'image/jpeg');
										$doc->startElement('mets:FLocat');
											$doc->writeAttribute('LOCTYPE', 'URL');
											$doc->writeAttribute('xlink:href', 'media/thumbnail/' . $file);
										$doc->endElement();
									$doc->endElement();
								}
							//end mets:fileGrp	
							$doc->endElement();
						}
					}					
				}
			//end mets:fileSec
			$doc->endElement();
		//end digRep
		$doc->endElement();		
	//end nuds
	$doc->endElement();
	$doc->endDocument();
	
	echo "Writing {$recordId}\n";
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
		$ar = $nomismaUris[$uri]['ar'];
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
			$ar = $xpath->query("descendant::skos:prefLabel[@xml:lang='ar']")->item(0)->nodeValue;
			
			//get the parent, if applicable
			$parents = $xpath->query("descendant::org:organization");
			if ($parents->length > 0){
				$nomismaUris[$uri] = array('label'=>$label,'type'=>$type, 'ar'=>$ar, 'parent'=>$parents->item(0)->getAttribute('rdf:resource'));
				$parent = $parents->item(0)->getAttribute('rdf:resource');
			} else {
				$nomismaUris[$uri] = array('label'=>$label,'type'=>$type, 'ar'=>$ar);
			}
		}
	}
	switch($type){
		case 'nmo:Mint':
		case 'nmo:Region':
			$content['element'] = 'geogname';
			$content['label'] = $label;
			$content['ar'] = $ar;	
			break;
		case 'nmo:Material':
			$content['element'] = 'material';
			$content['label'] = $label;
			$content['ar'] = $ar;
			break;
		case 'nmo:Manufacture':
			$content['element'] = 'manufacture';
			$content['label'] = $label;
			$content['ar'] = $ar;
			break;
		case 'nmo:ObjectType':
			$content['element'] = 'objectType';
			$content['label'] = $label;
			$content['ar'] = $ar;
			break;
		case 'rdac:Family':
		case 'nmo:Ethnic':
			$content['element'] = 'famname';
			$content['label'] = $label;
			$content['ar'] = $ar;
			break;
		case 'foaf:Organization':
		case 'foaf:Group':
			$content['element'] = 'corpname';
			$content['label'] = $label;
			$content['ar'] = $ar;
			break;
		case 'foaf:Person':
			$content['element'] = 'persname';
			$content['label'] = $label;
			$content['ar'] = $ar;
			if (isset($parent)){
				$content['parent'] = $parent;
			}
			break;
		case 'crm:E4_Period':
			$content['element'] = 'periodname';
			$content['label'] = $label;
			$content['ar'] = $ar;
			break;
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