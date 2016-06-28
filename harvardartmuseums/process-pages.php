<?php 

$data = generate_json('pages.csv');

$text = '';
$volume = 5;
foreach ($data as $row){
	$start = $row['Start Page'];
	$end = $row['End Page'];	
	$auth = rtrim($row['Section URI'], '.');
	
	$pieces = explode('.', $auth);
	
	$text .= ((int)$pieces[1] > $volume ? 'if' : 'elseif') . ' ($p >= ' . $start . ' && $p <= ' . $end . '){' . "\n";
	$text .= "\t" . '$authority = ' . "'" . $auth . "';\n";
	$text .= "}\n";
	
	if ((int)$pieces[1] > $volume){
		$volume++;
	}
}

file_put_contents('conditionals.txt', $text);
//echo $text;

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