<?php 

$data = generate_json('sco1.csv');
$denominations = array();
$authorities = array();
$issuers = array();
$mints = array();

foreach ($data as $row){
	
	//authorities
	if (!in_array($row['Ruler'], $authorities)){
		$authorities[] = trim($row['Ruler']);
	}
	if (!in_array($row['Coregent'], $authorities)){
		$authorities[] = trim($row['Coregent']);
	}
	
	//magistrates
	if (!in_array($row['Magistrate'], $issuers)){
		$issuers[] = trim($row['Magistrate']);
	}
	
	//denominations
	if (!in_array($row['Denomination'], $denominations)){
		$denominations[] = trim($row['Denomination']);
	}
	
	//mints
	if (!in_array($row['Mint'], $mints)){
		$mints[] = trim($row['Mint']);
	}
}

write_csv($authorities, 'authorities');
write_csv($denominations, 'denominations');
write_csv($mints, 'mints');

/***** FUNCTIONS *****/
function write_csv($csv, $type){
	asort($csv);
	
	$heading = array('label');
	
	$file = fopen($type . '.csv', 'w');
	
	fputcsv($file, $heading);
	foreach ($csv as $label) {
		$array = array($label);
		fputcsv($file, $array);
	}
	
	fclose($file);
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