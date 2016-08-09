<?php 

$data = generate_json('https://docs.google.com/spreadsheets/d/1mvZu1JUw9mwusMAsDOaZGbDGEmBpfb9xhbeyqn2-Uk4/pub?gid=0&single=true&output=csv');


$directories = glob('/home/komet/ans_migration/ebooks/*' , GLOB_ONLYDIR);

foreach ($directories as $folder){
	$pieces = explode('/', $folder);
	$id = $pieces[5];
	
	foreach ($data as $row){
		if ($row['filename'] == $id){
			switch ($id){
				case 'Hispanic1-Part1':
					$new = 'nnan' . $row['donum'] . '_1';
					break;
				case 'Hispanic1-Part2':
					$new = 'nnan' . $row['donum'] . '_2';
					break;
				default:
					$new = 'nnan' . $row['donum'];
			}
			rename($id, $new);
		}		
	}
}

//var_dump($directories);


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