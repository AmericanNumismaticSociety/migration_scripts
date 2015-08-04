<?php 

$all = generate_json('all-names.csv');
$distinct = generate_json('distinct-names.csv');
$creators = generate_json('distinct-creators.csv');

$writer = new XMLWriter();
//$writer->openURI('php://output');
$writer->openURI("distinct-creators.xml");
$writer->startDocument('1.0','UTF-8');
$writer->setIndent(4);
$writer->startElement('xml');
foreach($creators as $row){
	$writer->startElement('entry');
	foreach($row as $k=>$v){
		$writer->writeElement($k, $v);
	}
	$writer->endElement();
}
$writer->endElement();
$writer->flush();

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