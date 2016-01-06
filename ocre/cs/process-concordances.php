<?php 

$data = generate_json('ricConcordance.csv');
/*****
 * $unAttrMax: maximum number of 'No match can be made' values per object to continue
 * $matchMax: minimum number of the same RIC ID per sample of 5 to be considered a successful match
 * $certaintyMin: The average certainty must be greater than this amount for RIC ID
 *****/

$unAttrMax = 3;
$matchMin = 4;
$certaintyMin = 6;

$uniqueObjects = array();
foreach ($data as $row){
		if (!in_array($row['object'], $uniqueObjects)){
			$uniqueObjects[] = $row['object'];
		}
}

//iterate through all rows for each unique object URI to generate a list of attributable objects with 4 ($matchMin) or more identical RIC numbers
foreach ($uniqueObjects as $object){
	$unattributable = 0;
	$ids = array();
	
	foreach ($data as $row){
		if ($row['object'] == $object){
			$match = strlen($row['possibleRicID']) > 0 ? $row['possibleRicID'] : 'NULL';
			
			//generate count of unattributable rows
			if (strlen($row['unattributable']) > 0){
				$unattributable++;
			}
			
			//generate array of primary matches and associated counts
			if (!array_key_exists($match, $ids)){
				$ids[$match] = 1;
			} else {
				$ids[$match] = $ids[$match] + 1;
			}
			
		}
	}
	
	foreach ($ids as $k=>$v){
		if ($k != 'NULL' && $v >= $matchMin && $unattributable < $unAttrMax){
			$matches[] = array('object'=>$object, 'id'=>$k);
		}
	}
}

//determine the average certainty value, include other type descriptions
foreach ($matches as $k=>$match){
	$count = 0;
	$value = 0;
	
	$matches[$k]['average'] = 0;
	foreach($data as $row){
		if ($match['object'] == $row['object'] && $match['id'] == $row['possibleRicID']){
			$count++;
			$value += $row['certainty'];
			
			//add in legend/type description
			$matches[$k]['obverseDescription'] = trim($row['obverseDescription']);
			$matches[$k]['obverseInscription'] = trim($row['obverseInscription']);
			$matches[$k]['reverseDescription'] = trim($row['reverseDescription']);
			$matches[$k]['reverseInscription'] = trim($row['reverseInscription']);
		}		
	}
	$avg = $value / $count;
	$matches[$k]['average'] = $avg; 
}

//output to CSV
$file = fopen("matches.csv","w");
fputcsv($file, explode(',', 'object,id,certainty,obverseDescription,obverseInscription,reverseDescription,reverseInscription'));
foreach ($matches as $match)
{
	fputcsv($file, $match);
}

fclose($file);


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