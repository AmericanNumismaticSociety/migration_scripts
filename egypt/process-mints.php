<?php 

$data = generate_json('mints-original.csv');
$processed = array();

//var_dump($data);
foreach ($data as $row){
	$content = array();
	$id = str_replace(')', '', str_replace(',', '', str_replace('(', '', str_replace('.', '', str_replace(' ', '_', strtolower(trim($row['primary name'])))))));
	
	//generate nomisma ID
	$content['nomisma_id'] = $id;
	$content['key'] = $row['key or city as liste in database'];
	$content['prefLabel_en'] = trim($row['primary name']);
	$content['prefLabel_ar'] = trim($row['primary Arabic name']);
	$content['altLabel_en'] = trim($row['second name']);
	$content['altLabel_ar'] = trim($row['second Arabic name']);
	$content['wikipedia'] = trim($row['Wikipedia reference']);
	$content['definition'] = trim($row['Definition']);
	
	//handle geonames
	if (strlen(trim($row['geonum'])) > 0){
		$geonameId = trim($row['geonum']);
		$content['geonames'] = 'http://www.geonames.org/' . $geonameId;
		
		//get the latitude and longitude from Geonames
		$xmlDoc = new DOMDocument();
		$xmlDoc->load('http://api.geonames.org/get?geonameId=' . $geonameId . '&username=anscoins&style=full');
		$xpath = new DOMXpath($xmlDoc);
		
		$lat = $xpath->query('//lat')->item(0)->nodeValue;
		$long = $xpath->query('//lng')->item(0)->nodeValue;
		
		if (is_numeric($lat) && is_numeric($long)){
			$content['lat'] = $lat;
			$content['long'] = $long;
		} else {
			$content['lat'] = '';
			$content['long'] = '';
		}
	} else {
		$content['geonames'] = '';
		$content['lat'] = '';
		$content['long'] = '';
	}
	
	//var_dump($content);
	echo "Processing {$id}\n";
	$processed[] = $content;
}

$fp= fopen('islamic_mints_processed.csv', 'w');
$header = array('nomisma_id','key', 'prefLabel_en', 'prefLabel_ar','altLabel_en','altLabel_ar','wikipedia','definition','geonames','lat','long');
fputcsv($fp, $header);
foreach ($processed as $fields)
{
	fputcsv($fp, $fields);
}
fclose($fp);

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