<?php 
/*** 
 * Date: November 2017
 * Function: extract SCO mints and associated descriptions from master mint concordance spreadsheet 
 * in order to generate a new spreadsheet that should be imported into Nomisma
 ***/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTGSGNVsQntMzGBx-wcdjVL5ms9OoLsPwwwV5KhaGLAziiMCTzekp1B2T9TNAtAYV4wsqzC5606_8i7/pub?output=csv');
$mints = array();

foreach ($data as $row){
	$uris = explode('|', $row['mint_uri']);
	$def = $row['label (DO NOT EDIT)'];
	
	foreach ($uris as $uri){
		$key = str_replace('?', '', $uri);
		
		if (strpos($key, '_sco') !== FALSE){
			//if the key already exists, then insert the new definition into the array
			if (array_key_exists($key, $mints)){
				array_push($mints[$key], $def);
			} else {
				//create a new array under the $key
				$mints[$key] = array($def);
			}
		}
	}
}

//output the new mint/definition object into CSV

$fp = fopen('new_mints.csv', 'w');
fputcsv($fp, array('id', 'definition'));
foreach ($mints as $k=>$v) {
	$fields = array($k, implode('|', $v));
	fputcsv($fp, $fields);
}

fclose($fp);


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