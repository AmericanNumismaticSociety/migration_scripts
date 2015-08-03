<?php 

$dynasties = generate_json('non-islamic-dynasties.csv');
$collection = generate_json('collection.csv');
$data = generate_json('non-islamic-names.csv');
$processed = array();

foreach ($data as $row){
	$content = array();
	
	preg_match('/(.*)\ \([0-9]/', $row['authorizing authority'], $matches);
	if (isset($matches[1])){
		$label = trim($matches[1]);
		if (substr($label, -1) == ','){
			$label = substr($label, 0, strlen($label) - 1);
		}
		$content['nomisma_id'] = str_replace(',', '', str_replace('.', '', str_replace(' ', '_', strtolower($label))));
	} else {
		$content['nomisma_id'] = str_replace(',', '', str_replace('.', '', str_replace(' ', '_', strtolower($row['authorizing authority']))));
	}
	
	
	$content['key'] = $row['authorizing authority'];
	
	$content['en'] = trim($row['authorizing authority']);
	$content['ar'] = $row['authorizing authority in Arabic'];
	$content['definition'] = trim($row['definition']);
	//$content['role'] = $row['title in English'];
	
	//calculate dynasty
	$id = '';
	foreach ($collection as $record){
		if ($record['Additional names on object'] == $row['authorizing authority'] || $record['Authorizing individual'] == $row['authorizing authority']){
			if (strlen($record['Authorizing entity']) > 0){
				$dvalue = $record['Authorizing entity'];
				foreach ($dynasties as $dynasty){
					if ($dynasty['key'] == $dvalue){
						if (strlen($dynasty['nomisma_id']) > 0){
							$id = $dynasty['nomisma_id'];
						}
					}
				}
			}
		}
	}
	if (strlen($id) > 0){
		$content['dynasty'] = 'http://nomisma.org/id/' . $id;
	} else {
		$content['dynasty'] = $dvalue;
	}
	
	//$content['cat'] = $row['1982 cat. num.'];
	if (strpos($row['wikipedia'], 'wikipedia.org') > 0){
		$content['wikipedia'] = $row['wikipedia'];
	} else {
		$row['wikipedia'] = '';
	}
	
	//generate clean VIAF URI	
	if (strpos($row['Full VIAF url'], 'viaf.org') > 0){
		$pieces = explode('/', $row['Full VIAF url']);
		$content['viaf'] = 'http://viaf.org/viaf/' . $pieces[4];
	} else {
		$content['viaf'] = '';
	}
	
	unset($id);
	$processed[] = $content;
	//var_dump($content);
}

//var_dump($processed);
$fp= fopen('non_islamic_names_processed.csv', 'w');
$header = array('nomisma_id','key', 'prefLabel_en', 'prefLabel_ar','definition_en','dynasty','wikipedia','viaf');
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