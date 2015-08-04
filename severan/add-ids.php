<?php 

$data = generate_json('severan.csv');
$mints = array();
$regions = array();
$materials = array();

$csv = '';
foreach ($data as $row){
	$mint = $row['City'];
	switch($mint) {
		case 'Smyrna':
			$mint_uri='http://nomisma.org/id/smyrna';
			break;
		case 'Ephesus':
			$mint_uri='http://nomisma.org/id/ephesus';
			break;
		case 'Stratonicea':
			$mint_uri='http://nomisma.org/id/stratoniceia';
			break;
		case 'Alexandria':
			$mint_uri='http://nomisma.org/id/alexandria';
			break;
		case '':
			$mint_uri='';
			break;
		case 'Aphrodisias':
			$mint_uri='http://nomisma.org/id/aphrodisias_caria';
			break;
		case 'Samos':
			$mint_uri='http://nomisma.org/id/samos';
			break;
		case 'Αphrodisias':
			$mint_uri='http://nomisma.org/id/aphrodisias_caria';
			break;
		case 'Anazarbus':
			$mint_uri='http://nomisma.org/id/anazarbus';
			break;
		case 'Tarsus':
			$mint_uri='http://nomisma.org/id/tarsus';
			break;
		case 'Αnazarbus':
			$mint_uri='http://nomisma.org/id/anazarbus';
			break;
		case 'Anazarbus/Caesarea':
			$mint_uri='http://nomisma.org/id/anazarbus';
			break;
		case 'Tomis':
			$mint_uri='http://nomisma.org/id/tomis';
			break;
		case 'Laodicaea ad Mare':
			$mint_uri='http://nomisma.org/id/laodiceia_ad_mare';
			break;
		case 'Εmesa':
			$mint_uri='http://nomisma.org/id/emisa';
			break;
		case 'Cremna':
			$mint_uri='http://nomisma.org/id/cremna';
			break;
		case 'Odessos':
			$mint_uri='http://nomisma.org/id/odessus';
			break;
		case 'Pergamum':
			$mint_uri='http://nomisma.org/id/pergamum';
			break;
		case 'Mytilene':
			$mint_uri='http://nomisma.org/id/mytilene';
			break;
		case 'Emesa':
			$mint_uri='http://nomisma.org/id/emisa';
			break;
		case 'Emisa':
			$mint_uri='http://nomisma.org/id/emisa';
			break;
		case 'Rome':
			$mint_uri='http://nomisma.org/id/rome';
			break;
		case 'Hierapolis':
			$mint_uri='http://nomisma.org/id/hierapolis_phrygia';
			break;
		case 'Laodicaea ad mare':
			$mint_uri='http://nomisma.org/id/laodiceia_ad_mare';
			break;
		case 'Laodicaea':
			$mint_uri='http://nomisma.org/id/laodiceia_ad_mare';
			break;
		case 'Epehesus':
			$mint_uri='http://nomisma.org/id/ephesus';
			break;
		case 'Cos':
			$mint_uri='http://nomisma.org/id/cos';
			break;
		case 'Εphesus':
			$mint_uri='http://nomisma.org/id/ephesus';
			break;
		case 'Ephesis':
			$mint_uri='http://nomisma.org/id/ephesus';
			break;
		case 'Loodicaea ad Mare':
			$mint_uri='http://nomisma.org/id/laodiceia_ad_mare';
			break;
		case 'Epehsus':
			$mint_uri='http://nomisma.org/id/ephesus';
			break;
		case 'Alexandre':
			$mint_uri='http://nomisma.org/id/alexandria';
			break;
		case 'Alexander':
			$mint_uri='http://nomisma.org/id/alexandria';
			break;
		case 'Kremna':
			$mint_uri='http://nomisma.org/id/cremna';
			break;
	}
	
	$region = $row['Province'];
	
	
	switch($region) {
		case 'Ionia':
			$region_uri='http://nomisma.org/id/ionia';
			break;
		case 'Caria':
			$region_uri='http://nomisma.org/id/caria';
			break;
		case 'Egypt':
			$region_uri='http://nomisma.org/id/egypt';
			break;
		case '':
			$region_uri='';
			break;
		case 'Cilicia':
			$region_uri='http://nomisma.org/id/cilicia';
			break;
		case 'Moesia Inferior':
			$region_uri='http://nomisma.org/id/moesia';
			break;
		case 'Syria':
			$region_uri='http://nomisma.org/id/syria';
			break;
		case 'Pisidia':
			$region_uri='http://nomisma.org/id/pisidia';
			break;
		case 'Mysia':
			$region_uri='http://nomisma.org/id/mysia';
			break;
		case 'Lesbos':
			$region_uri='http://nomisma.org/id/lesbos';
			break;
		case 'Phrygia':
			$region_uri='http://nomisma.org/id/phrygia';
			break;
		case 'Pergamum':
			$region_uri='';
			break;
		case 'Lydia':
			$region_uri='http://nomisma.org/id/lydia';
			break;
		case 'Egypy':
			$region_uri='http://nomisma.org/id/egypt';
			break;
		case 'Thrace':
			$region_uri='http://nomisma.org/id/thrace';
			break;
		case 'Ιοnia':
			$region_uri='http://nomisma.org/id/ionia';
			break;
		case 'Egytp':
			$region_uri='http://nomisma.org/id/egypt';
			break;
		}
	$material = $row['Metal'];
	
	
	switch($material) {
		case 'AE':
			$material_uri='http://nomisma.org/id/ae';
			break;
		case '':
			$material_uri='';
			break;
		case 'AR':
			$material_uri='http://nomisma.org/id/ar';
			break;
		case 'AV':
			$material_uri='http://nomisma.org/id/av';
			break;
		case 'BI':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'ΑΕ':
			$material_uri='http://nomisma.org/id/ae';
			break;
		case 'billione':
			$material_uri='';
			break;
		case '(billione)':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'Billione':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'Billion':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'billion':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'bilione':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'bronze':
			$material_uri='http://nomisma.org/id/ae';
			break;
		case 'Bronze':
			$material_uri='http://nomisma.org/id/ae';
			break;
		case 'BILLION':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'BIL':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'ΒΙL':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'BR':
			$material_uri='http://nomisma.org/id/ae';
			break;
		case 'Billon':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'bilion':
			$material_uri='http://nomisma.org/id/billon';
			break;
		case 'ae':
			$material_uri='http://nomisma.org/id/ae';
			break;
		}
	//first populate mints and regions
	/*if (array_search($mint, $mints) === FALSE){
		$mints[] = $mint;
	}
	if (array_search($region, $regions) === FALSE){
		$regions[] = $region;
	}
	if (array_search($material, $materials) === FALSE){
		$materials[] = $material;
	}*/
		foreach ($row as $k=>$v){
			$csv .= '"' . $v . '",';
		}
		$csv .= '"' . $mint_uri . '","' . $region_uri . '","' . $material_uri . '"' . "\n";
}

$handle = fopen('new.csv', 'w');
fwrite($handle, $csv);
fclose($handle);

/*foreach ($materials as $material){
		echo "case '{$material}':\n\t\$material_uri='';\n\tbreak;\n";
}*/


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