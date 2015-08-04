<?php 

//create an array with pre-defined labels and values passed from the Filemaker POST
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
		"OrigIntenUse","Authenticity","PostManAlt","privateinfo");

$types = array();

if (($handle = fopen("cleaned.csv", "r")) !== FALSE) {
	while (($data = fgetcsv($handle, 2500, ',', '"')) !== FALSE) {
		$row = array();
		foreach ($labels as $key=>$label){
			$row[$label] = preg_replace('/\s+/', ' ', $data[$key]);
		}

		$citations = array_filter(explode('|', $row['published']));	
		if (isset($citations[0])){
			$id = $citations[0];
		}
		
		//first create a new type in the array, if it doesn't already exist
		if (!array_key_exists($id, $types)){
			$types[$id] = array();
		}		
		//then insert the size/material/manufacture combination
		$variant = process_medal($row);
			
		$types[$id][implode('|', $variant)] = $variant;
	}
}

ksort($types);

$csv = '"type","variant","material","manufacture","diameter","match_string"' . "\n";
foreach ($types as $id=>$type){
	ksort($type);
	//generate spreadsheet only for types with variants
	if (count($type) > 1){
		foreach ($type as $m=>$variant){
			$csv .= '"' . $id . '","","' . $variant['material'] . '","' . $variant['manufacture'] . '","' . (int) $variant['diameter'] . '","' . $m . '"' . "\n";
		}
	}
	
	
}

file_put_contents('variants.csv', $csv);
//echo $csv;
//var_dump($types);

function process_medal($row){
	$materials = array_filter(explode('|', $row['material']));
	$manufactures = array_filter(explode('|', $row['manufacture']));
	
	$manufacture_array = array();
	$material_array = array();
	
	if (count($manufactures) > 0){
		foreach ($manufactures as $manufacture){
			$manufacture_array[] = normalize_manufacture(trim($manufacture));
		}
	}
	
	if (count($materials) > 0){
		foreach ($materials as $material){
			$material_array[] = normalize_material(trim($material));
		}
	}
	
	if (is_numeric(trim($row['measurements']))){
		$diameter = trim($row['measurements']);
	} else {
		$diameter = '';
	}
	
	return array('material'=>implode('|', $material_array), 'manufacture'=>implode('|', $manufacture_array), 'diameter'=>number_pad($diameter, 4));
}

function normalize_material($material){
	$array = array();
	switch (trim(strtoupper(str_replace('?', '', $material)))) {
		case 'AE':
		case 'B':
		case 'BRONZE':
			$array['label'] = 'Bronze';
			$array['uri'] = 'http://nomisma.org/id/ae';
			break;
		case 'AL':
		case 'ALUMINUM':
			$array['label'] = 'Aluminum';
			$array['uri'] = 'http://nomisma.org/id/al';
			break;
		case 'AV':
		case 'AU':
		case 'GOLD':
			$array['label'] = 'Gold';
			$array['uri'] = 'http://nomisma.org/id/av';
			break;
		case 'AR':
		case 'SILVER':
			$array['label'] = 'Silver';
			$array['uri'] = 'http://nomisma.org/id/ar';
			break;
		case 'BI':
		case 'BIL':
		case 'BILLON':
			$array['label'] = 'Billon';
			$array['uri'] = 'http://nomisma.org/id/billon';
			break;
		case 'BRASS':
			$array['label'] = 'Brass';
			$array['uri'] = 'http://nomisma.org/id/brass';
			break;
		case 'CU':
		case 'COPPER':
			$array['label'] = 'Copper';
			$array['uri'] = 'http://nomisma.org/id/cu';
			break;
		case 'EL':
		case 'ELECTRUM':
			$array['label'] = 'Electrum';
			$array['uri'] = 'http://nomisma.org/id/electrum';
			break;
		case 'ENAMEL':
			$array['label'] = 'Enamel';
			$array['uri'] = 'http://nomisma.org/id/enamel';
			break;
		case 'F':
			$array['label'] = 'Fiber';
			$array['uri'] = 'http://nomisma.org/id/fiber';
			break;
		case 'FE':
		case 'IRON':
			$array['label'] = 'Iron';
			$array['uri'] = 'http://nomisma.org/id/fe';
			break;
		case 'GS':
			$array['label'] = 'Unknown value';
			$array['uri'] = 'http://nomisma.org/id/unknown_value';
			break;
		case 'GLASS':
			$array['label'] = 'Glass';
			$array['uri'] = 'http://nomisma.org/id/glass';
			break;
		case 'NI':
		case 'NICKEL':
			$array['label'] = 'Nickel';
			$array['uri'] = 'http://nomisma.org/id/ni';
			break;
		case 'ORICHALCUM':
			$array['label'] = 'Orichalcum';
			$array['uri'] = 'http://nomisma.org/id/orichalcum';
			break;
		case 'PB':
		case 'LEAD':
			$array['label'] = 'Lead';
			$array['uri'] = 'http://nomisma.org/id/pb';
			break;
		case 'STEEL':
			$array['label'] = 'Steel';
			$array['uri'] = 'http://nomisma.org/id/steel';
			break;
		case 'SN':
		case 'TIN':
			$array['label'] = 'Tin';
			$array['uri'] = 'http://nomisma.org/id/sn';
			break;
		case 'ZN':
		case 'Z':
		case 'ZINC':
			$array['label'] = 'Zinc';
			$array['uri'] = 'http://nomisma.org/id/zn';
			break;
		case 'M':
		case 'MIXED':
		case 'UNKNOWN':
			$array['label'] = 'Unknown value';
			$array['uri'] = 'http://nomisma.org/id/unknown_value';
			break;
		case 'WHITE MEDAL':
		case 'WHITE METAL':
		case 'WHITE METAL?':
			$array['label'] = 'Unknown value';
			$array['uri'] = 'http://nomisma.org/id/unknown_value';
		default:
			$array['label'] = ucfirst(strtolower($material));
			$array['uri'] = '';
	}
	return $array['uri'];
}

function normalize_manufacture($manufacture){
	$array = array();
	switch (trim(strtoupper(str_replace('?', '', $manufacture)))) {
		case 'STRUCK':
			$array['label'] = 'Struck';
			$array['uri'] = 'http://nomisma.org/id/struck';
			break;
		case 'CC':
			$array['label'] = 'Pressed';
			$array['uri'] = 'http://nomisma.org/id/cast';
			break;
		case 'CAST':
			$array['label'] = 'Cast';
			$array['uri'] = 'http://nomisma.org/id/cast';
			break;
		case 'ELECTROTYPE':
			$array['label'] = 'Electrotyped';
			$array['uri'] = 'http://nomisma.org/id/electrotyped';
			break;
		case 'GALVANO':
			$array['label'] = 'Plated';
			$array['uri'] = 'http://nomisma.org/id/plated';
			break;
		case 'GILT':
			$array['label'] = 'Gilded';
			$array['uri'] = 'http://nomisma.org/id/gilded';
			break;
		default:
			$array['label'] = ucfirst(strtolower($material));
			$array['uri'] = '';
	}
	return $array['uri'];
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
	if ($number > 0){
		$gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
	} elseif ($number < 0) {
		$bcNum = (int)abs($number) - 1;
		$gYear = '-' . str_pad($bcNum,$n,"0",STR_PAD_LEFT);
	}
	return $gYear;
}

?>