<?php
error_reporting(0);
$data = generate_json('bm-price.csv', false);

$csv = '"uri","coinType","regno","price"' . "\n";

//generate concordance list
$count = 0;
foreach ($data as $row){
	$coinType = parse_price($row['price'], $row['regno'], $count);
	
	$csv .= '"' . $row['uri'] . '","' . $coinType . '","' . $row['regno'] . '","' . $row['price'] . '"' . "\n";
	$count++;
}



//var_dump($rrc_array);
file_put_contents('bm-concordances.csv', $csv);

function parse_price($ref, $regno, $count){
	$num = str_replace('GC30.', '', $ref);
	
	//check to see if the letter exists, else slice it off
	if (ctype_alpha(substr($num, -1))){
		//if the letter is uppercase, try the Price URI in upper case first, otherwise slice the letter off
		if (ctype_upper(substr($num, -1))){
			$url = 'http://numismatics.org/pella/id/price.' . $num;
			$file_headers = @get_headers($url);
			if ($file_headers[0] == 'HTTP/1.1 200 OK'){
				echo "{$count}: {$regno} - {$url}\n";
				return $url;
			} else {
				$url = 'http://numismatics.org/pella/id/price.' . substr($num, 0, -1);
				$file_headers = @get_headers($url);
				if ($file_headers[0] == 'HTTP/1.1 200 OK'){
					echo "{$count}: {$regno} - {$url}\n";
					return $url;
				} else {
					return '';
				}
			}
		} elseif (ctype_lower(substr($num, -1))){
			$url = 'http://numismatics.org/pella/id/price.' . substr($num, 0, -1);
			$file_headers = @get_headers($url);
			if ($file_headers[0] == 'HTTP/1.1 200 OK'){
				echo "{$count}: {$regno} - {$url}\n";
				return $url;
			} else {
				return '';
			}
		}
	} else {
		$url = 'http://numismatics.org/pella/id/price.' . $num;
		$file_headers = @get_headers($url);
		if ($file_headers[0] == 'HTTP/1.1 200 OK'){
			echo "{$count}: {$regno} - {$url}\n";
			return $url;
		} else {
			return '';
		}
	}	
}

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
