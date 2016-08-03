<?php 

$dynasties = array();

$labels = array("accnum","department","objtype","material","manufacture",
		"shape","weight","measurements","axis","denomination","era","dob",
		"startdate","enddate","refs","published","info","prevcoll","region",
		"locality","series","dynasty","mint","mintabbr","person","issuer",
		"magistrate","maker","artist","sernum","subjevent","subjperson",
		"subjissuer","subjplace","decoration","degree","findspot",
		"obverselegend","obversetype","reverselegend","reversetype","color",
		"edge","undertype","counterstamp","conservation","symbol",
		"obversesymbol","reversesymbol","signature","watermark",
		"imageavailable","acknowledgment","category","imagesponsor",
		"OrigIntenUse","Authenticity","PostManAlt","diameter","height","width","depth","privateinfo");

//clean file first
$file = file_get_contents('islamic.csv');
$cleaned = implode(',', $labels) . "\n";
$cleaned .= preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', preg_replace("[\x1D]", "|", str_replace('>', '&gt;', str_replace('<', '&lt;', str_replace('&', 'and', preg_replace("[\x0D]", "\n", $file))))));
file_put_contents('islamic-clean.csv', $cleaned);

$data = generate_json('islamic-clean.csv');

foreach ($data as $row){
	$dynasty = trim($row['dynasty']);
	
	if (!in_array($dynasty, $dynasties)){
		$dynasties[] = $dynasty;
	}
}

sort($dynasties);

$table = array();
foreach ($dynasties as $dynasty){
	$table[] = array($dynasty, null, null, null);
}

$fp = fopen('dynasties.csv', 'w');
$headings = array('key','type','nomisma_id','notes');
fputcsv($fp, $headings);

foreach ($table as $fields) {
	fputcsv($fp, $fields);
}

fclose($fp);
//var_dump($dynasties);

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