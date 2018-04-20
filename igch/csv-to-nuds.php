<?php 
  /**** 
  * Author: Ethan Gruber
  * Date: April 2018
  * Function: Transform CSV for IGCH Hellenistic Hoards into NUDS-Hoard XML 
  ****/

$contents = generate_json('contents.csv');
$counts = generate_json('counts.csv');
$findspots = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQeIbUxRU-CpMfzSM6eU22Mn4VyhdRmNtMNUEfkHegVAQLEkblX0OFJlUNyouZBDao_clG1c9xS15Y1/pub?output=csv');
$depositDates = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTI1-N57bT5PxIen4dafjV3MoSQa-4gV5ZVQNstLB4FeTkIuT8CcRfe9f8o9MmkQoE4iM1izCtbpDDw/pub?output=csv');
$refNotes = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vSye6cSV45CuOpVDvYDaUrABoP7W7CesN_lTlZo9G4ydfBh_kbEUuaWXq3ZiBkEPkojyAyI0B46zYPW/pub?output=csv');
$hoards = array();

//associative array of Nomisma IDs and preferred labels
$nomisma = array();

foreach($counts as $hoard){
	if (strlen($hoard['id']) > 0){
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
		if (strlen($hoard['notes']) > 0){
			$record['notes'] = $hoard['notes'];
		}
		
		//extract deposit dates
		foreach ($depositDates as $date){
			if ($date['id'] == $id){
				$record['deposit']['fromDate'] = (int) $date['fromDate'];
				$record['deposit']['toDate'] = (int) $date['toDate'];
			}
		}
		
		//extract discovery and find information
		foreach ($findspots as $findspot){
			if ($findspot['id'] == $id){
				//findspots
				$record['findspot']['desc'] = $findspot['value'];
				if (strlen($findspot['geonames_place']) > 0){
					$record['findspot']['label'] = $findspot['geonames_place'];
				}
				if (strlen($findspot['geonames_uri']) > 0){
					$record['findspot']['uri'] = $findspot['geonames_uri'];
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
						$record['discovery']['notAfter'] = $findspot['fromDate'];
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
		$record['notes'] = array();
		$record['refs'] = array();
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
				
				$content['count'] = $row['count'];
				//insert approximation
				
				//typeDesc
				if (strlen($row['nomisma id (region)']) > 0){
					$content['region_uri'] = trim($row['nomisma id (region)']);
				}
				if (strlen($row['nomisma id (mint)']) > 0){
					$content['mint_uri'] = trim($row['nomisma id (mint)']);
				}
				if (strlen($row['nomisma id (denomination)']) > 0){
					$content['denomination_uri'] = trim($row['nomisma id (denomination)']);
				}
				if (strlen($row['nomisma id (authority)']) > 0){
					$content['authority_uri'] = trim($row['nomisma id (authority)']);
				}
				if (strlen($row['nomisma id (dynasty)']) > 0){
					$content['dynasty_uri'] = trim($row['nomisma id (dynasty)']);
				}
				if (strlen($row['general type desc.']) > 0){
					$content['desc'] = trim($row['general type desc.']);
				}
				if (strlen($row['obv_type']) > 0){
					$content['obverse']['type'] = trim($row['obv_type']);
				}
				if (strlen($row['rev_type']) > 0){
					$content['reverse']['type'] = trim($row['rev_type']);
				}
				if (strlen($row['rev_legend']) > 0){
					$content['reverse']['legend'] = trim($row['rev_legend']);
				}
				if (is_numeric($row['from_date']) && is_numeric($row['to_date'])){
					$content['fromDate'] = (int) $row['from_date'];
					$content['toDate'] = (int) $row['to_date'];
				}
				
				//refs
				if (strlen($row['ref1']) > 0){
					$content['refs']['ref1'] = $row['ref1'];
				}
				if (strlen($row['ref2']) > 0){
					$content['refs']['ref2'] = $row['ref2'];
				}
				if (strlen($row['ref3']) > 0){
					$content['refs']['ref3'] = $row['ref3'];
				}
				if (strlen($row['ref4']) > 0){
					$content['refs']['ref4'] = $row['ref4'];
				}
				if (strlen($row['ref5']) > 0){
					$content['refs']['ref5'] = $row['ref5'];
				}
				if (strlen($row['nomisma id (ref1)']) > 0){
					$content['refs']['uri'] = $row['nomisma id (ref1)'];
				}
				
				//read findspot
				/*foreach ($findspots as $findspot){
					if ($findspot['id'] == $id){
						
					}
				}*/
				
				$record['contents'][] = $content;
			}
		}
		
		//add hoard record into master hoards array
		$hoards[$id] = $record;
	}
}

//var_dump($hoards);

$count = 0;
foreach ($hoards as $k=>$v){
	if ($count < 11){
		//var_dump($v);
		generate_nuds($k, $v);		
	}
	$count++;
}


/***** FUNCTIONS *****/
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
			if (count($hoard['notes']) > 0){
				foreach ($hoard['notes'] as $note){
					$writer->startElement('note');
					
					$writer->endElement();
				}
			}
			
			//hoardDesc = nuds:findspotDesc
			$writer->startElement('hoardDesc');			
				//findspot
				$writer->startElement('findspot');
					$writer->startElement('description');
						$writer->writeAttribute('xml:lang', 'en');
						$writer->text($hoard['findspot']['desc']);
					$writer->endElement();
					if (array_key_exists('label', $hoard['findspot']) && array_key_exists('uri', $hoard['findspot'])){
						$writer->startElement('geogname');
							$writer->writeAttribute('xlink:type', 'simple');
							$writer->writeAttribute('xlink:role', 'findspot');
							$writer->writeAttribute('xlink:href', $hoard['findspot']['uri']);
							$writer->text($hoard['findspot']['label']);
						$writer->endElement();
					}						
				$writer->endElement();
				
				//deposit
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
								$writer->text('before' . get_date_textual($hoard['discovery']['notAfter']));
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
			if (count($hoard['contents']) > 0){
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
					
					foreach ($hoard['contents'] as $content){
						//coin or coinGrp
						if ((int)$content['count'] == 1){
							$writer->startElement('coin');
							generate_content($writer, $content);
							$writer->endElement();
						} else {
							$writer->startElement('coinGrp');
								$writer->writeAttribute('count', $content['count']);
								generate_content($writer, $content);
							$writer->endElement();							
						}
					}
					$writer->endElement();
				$writer->endElement();
			}
			
			//refDesc
			if (count($hoard['refs']) > 0){
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

//generate the content element from metadata in the content row (typeDesc and refDesc
function generate_content($writer, $content){
	//begin with typeDesc
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
	
		if (array_key_exists('denomination_uri', $content)){
			$label = get_label($content['denomination_uri']);
			if (isset($label)){
				$writer->startElement('nuds:denomination');
					$writer->writeAttribute('xlink:type', 'simple');
					$writer->writeAttribute('xlink:href', $content['denomination_uri']);
					$writer->text($label);
				$writer->endElement();
			}
		}
		if (array_key_exists('material_uri', $content)){
			$label = get_label($content['material_uri']);
			if (isset($label)){
				$writer->startElement('nuds:material');
				$writer->writeAttribute('xlink:type', 'simple');
				$writer->writeAttribute('xlink:href', $content['material_uri']);
				$writer->text($label);
				$writer->endElement();
			}
		}
		
		//authority
		if (array_key_exists('authority_uri', $content) || array_key_exists('dynasty_uri', $content)){
			$writer->startElement('nuds:authority');
			if (array_key_exists('authority_uri', $content)){
				$label = get_label($content['authority_uri']);
				if (isset($label)){
					$writer->startElement('nuds:persname');
						$writer->writeAttribute('xlink:type', 'simple');
						$writer->writeAttribute('xlink:role', 'authority');
						$writer->writeAttribute('xlink:href', $content['authority_uri']);
						$writer->text($label);
					$writer->endElement();
				}
			}
			if (array_key_exists('dynasty_uri', $content)){
				$label = get_label($content['dynasty_uri']);
				if (isset($label)){
					$writer->startElement('nuds:famname');
						$writer->writeAttribute('xlink:type', 'simple');
						$writer->writeAttribute('xlink:role', 'dynasty');
						$writer->writeAttribute('xlink:href', $content['dynasty_uri']);
						$writer->text($label);
					$writer->endElement();
				}
			}
			$writer->endElement();
		}
		
		//geographic
		if (array_key_exists('mint_uri', $content) || array_key_exists('region_uri', $content)){
			$writer->startElement('nuds:geographic');
				if (array_key_exists('mint_uri', $content)){
					$pieces = explode('|', $content['mint_uri']);
					foreach ($pieces as $uri){
						$uri = trim($uri);
						$label = get_label($uri);
						if (isset($label)){
							$writer->startElement('nuds:geogname');
								$writer->writeAttribute('xlink:type', 'simple');
								$writer->writeAttribute('xlink:role', 'mint');
								$writer->writeAttribute('xlink:href', $uri);
								$writer->text($label);
							$writer->endElement();
						}		
					}
								
				}
				if (array_key_exists('region_uri', $content)){
					$label = get_label($content['region_uri']);					
					if (isset($label)){
						$writer->startElement('nuds:geogname');
							$writer->writeAttribute('xlink:type', 'simple');
							$writer->writeAttribute('xlink:role', 'region');
							$writer->writeAttribute('xlink:href', $content['region_uri']);
							$writer->text($label);
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
	
	//refDesc
	if (array_key_exists('refs', $content)){
		if (count($content['refs']) > 0){
			$writer->startElement('nuds:refDesc');
				foreach($content['refs'] as $k=>$v){					
					if ($k == 'uri'){
						$label = get_label($v);	
						
						$writer->startElement('nuds:reference');
							$writer->writeAttribute('xlink:arcrole', 'nmo:hasTypeSeriesItem');
							$writer->writeAttribute('xlink:href', $v);
							$writer->text($label);
						$writer->endElement();
					} else {
						//if there's a URI and the key is ref1, then skip the reference
						if (array_key_exists('uri', $content['refs']) && $k == 'ref1'){
							
						} else {
							$writer->writeElement('nuds:reference', $v);
						}
					}
				}
			$writer->endElement();
		}	
	}
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

//get label from Nomisma JSON API
function get_label($uri){
	GLOBAL $nomisma;
	
	if (array_key_exists($uri, $nomisma)){
		return $nomisma[$uri];
	} else {
		//get label from Nomisma API
		$json = file_get_contents('http://nomisma.org/apis/getLabel?uri=' . $uri . '&format=json');
		$obj = json_decode($json);
		
		if (strlen($obj->label) > 0){
			$nomisma[$uri] = $obj->label;
			return $obj->label;
		} else {
			echo "Error: {$uri} is invalid\n";
			return null;
		}		
	}
}

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