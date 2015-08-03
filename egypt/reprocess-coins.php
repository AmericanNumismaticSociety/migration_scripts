<?php 

$collection = generate_json('collection.csv');
$mints = generate_json('mints.csv');
$ip = generate_json('islamic-persons.csv');
$nip = generate_json('non-islamic-persons.csv');
$id = generate_json('islamic-dynasties.csv');
$nid = generate_json('non-islamic-dynasties.csv');

$fields = array('recordId','objectType','material','materialUncertainty','manufacture','authorityPerson','org','otherPerson','mint','1887 catalog number','regno','dob_ah','dob',
			'fromDate','toDate','weight','diameter','height','width','authenticity','note');

//create csv header
$csv = '';
foreach ($fields as $field){
	$csv .= '"' . $field . '"';
	if ($field != 'note'){
		$csv .= ',';
	} else {
		$csv .= "\n";
	}
}

//reprocess each object in the collection spreadsheet
foreach ($collection as $row){
	$record = array();
	$record['recordId'] = trim($row['1982 Cat. Num']);
	$record['objectType'] = trim($row['Type of object']);	
	$record['material'] = str_replace('?', '', trim($row['Material']));
	
	//handle material uncertainty and manufacture
	if (strpos($row['Material'], '?') !== FALSE){
		$record['materialUncertainty'] = 'true';
	}
	
	if (strpos(strtolower($row['Material']), 'gilt') !== FALSE){
		$record['manufacture'] = 'http://nomisma.org/id/gilded';
	}
	
	//get the Dynasty--primarily through the ruler. If not available, normalize the key
	$person1 = trim($row['Authorizing individual']);
	$record['authorityPerson'] = $person1;
	if (strlen($person1) > 0){		
		foreach ($ip as $entity){
			$key = $entity['key'];
			if ($person1 == $key){
				$record['authorityPerson'] = 'http://nomisma.org/id/' . $entity['nomisma_id'];
				
				//get the appropriate type of org/dynasty from the dynasty spreadsheet
				if (strlen($entity['dynasty']) > 0){
					$dynasty = $entity['dynasty'];
					$dynasty_id = str_replace('http://nomisma.org/id/', '', $dynasty);
					foreach ($id as $do){
						if ($do['nomisma_id'] == $dynasty_id){
							//$do['class']
							$record['org'] = $dynasty;
						}
					}
				}				
				break;
			}
		}
		foreach ($nip as $entity){
			$key = $entity['key'];
			if ($person1 == $key){
				$record['authorityPerson'] = 'http://nomisma.org/id/' . $entity['nomisma_id'];
				
				//get the appropriate type of org/dynasty from the dynasty spreadsheet
				if (strlen($entity['org']) > 0){
					$dynasty = str_replace('http://nomisma.org/id/', '', $entity['org']);
					foreach ($nid as $do){
						if ($do['nomisma_id'] == $dynasty){
							$record['org'] = $entity['org'];
						}
					}
				}
				break;
			}
		}
	} elseif (strlen(trim($row['Authorizing entity'])) > 0){
		$record['org'] = $row['Authorizing entity'];
		if (strpos(strtolower($row['Authorizing entity']), 'imitation') !== FALSE){
			$record['authenticity'] = 'imitation';
		}
		$dorg = trim($row['Authorizing entity']);
		foreach ($id as $entity){
			$key = $entity['key'];
			if ($dorg == $key){
				if (strlen($entity['nomisma_id']) > 0) {
					$record['org'] = 'http://nomisma.org/id/' . $entity['nomisma_id'];
					break;
				}
			}
		}
		foreach ($nid as $entity){
			$key = $entity['key'];
			if ($dorg == $key){
				if (strlen($entity['nomisma_id']) > 0) {
					$record['org'] = 'http://nomisma.org/id/' . $entity['nomisma_id'];
					break;
				}
			}
		}
	}
	
	//parse second person
	$person2 = trim($row['Additional names on object']);
	if (strlen($person2) > 0){
		$record['otherPerson'] = $person2;
		foreach ($ip as $entity){
			$key = $entity['key'];
			if ($person2 == $key){
				$record['otherPerson'] = 'http://nomisma.org/id/' . $entity['nomisma_id'];
				break;
			}
		}
		foreach ($nip as $entity){
			$key = $entity['key'];
			if ($person2 == $key){
				$record['otherPerson'] = 'http://nomisma.org/id/' . $entity['nomisma_id'];
				break;
			}
		}
	}
	
	//mint
	$mint = trim($row['Mint']);
	if (strlen($mint) > 0){
		foreach ($mints as $entity){
			$key = $entity['key'];
			if ($mint == $key){
				$record['mint'] = 'http://nomisma.org/id/' . $entity['nomisma_id'];
				break;
			}
		}
	}
	
	$record['1887 catalog number'] = trim($row['1887 catalog number']);
	$record['regno'] = trim($row['Registration number']);
	
	if (strlen(trim($row['AH date'])) > 0){
		$record['dob_ah'] = trim($row['AH date']);
	}
	if (strlen(trim($row['Non-AH date'])) > 0){
		$record['dob'] = trim($row['Non-AH date']);
	}
	
	if (is_numeric($row['From CE date']) && is_numeric($row['To CE date'])){
		$record['fromDate'] = trim($row['From CE date']);
		$record['toDate'] = trim($row['To CE date']);
	}
	
	//weight
	$record['weight'] = $row['Weight in gr.'];
	
	//process diameter
	$diameter = trim($row['Diameter in mm.']);
	if (strlen($diameter) > 0){
		if (is_numeric($diameter)){
			$record['diameter'] = $diameter;
		} elseif (strpos($diameter, ' x ') !== FALSE){
			$pieces = explode(' x ', $diameter);
			$record['height'] = $pieces[0];
			$record['width'] = $pieces[1];
		} elseif (strpos($diameter, '-') !== FALSE){
			$pieces = explode('-', $diameter);
			//go with the larger integer
			$record['diameter'] = trim($pieces[1]);
		}
	}
	
	$record['note'] = trim($row['Additional comments']);
	
	foreach ($fields as $field){
		if (isset($record[$field])){
			$csv .= '"' . $record[$field] . '"';
		} else {
			$csv .= '""';
		}
		
		//insert comma if the $field is not 'note', otherwise end line
		if ($field != 'note'){
			$csv .= ',';
		} else {
			$csv .= "\n";
		}
	}
	//var_dump($record);
}

//write csv
file_put_contents('collection-processed.csv', $csv);

//functions
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