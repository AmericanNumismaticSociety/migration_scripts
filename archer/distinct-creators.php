<?php 

$data = generate_json('all-creators.csv');
$dc = array();

foreach ($data as $row){
	if (!in_array($row['preferredForm'], $dc)){
		$dc[] = $row['preferredForm'];
	}
}

$csv = '"id","preferredForm","guides","viaf","entityType","fromDate","toDate","fromNotBefore","fromNotAfter"' . "\n";
sort($dc);
foreach ($dc as $name){
	$guides = array();
	foreach ($data as $row){
		if ($row['preferredForm'] == $name){
			$guides[] = $row['eadid'];
			$viaf = $row['viaf'];
			$entityType = $row['entityType'];
		}
	}
	$csv .= '"","' . $name . '","' . implode('|', $guides) . '","' . $viaf . '","' . $entityType . '","","","",""' . "\n";
}
file_put_contents('distinct-creators.csv', $csv);
//echo $csv; 

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