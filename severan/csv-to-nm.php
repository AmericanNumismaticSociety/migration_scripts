<?php 

$usf = generate_json('coins.csv', true);
$data = generate_json('ric_severan.csv', false);
$deityMatchArray = get_deity_array();

$csv = "Nomisma.org id,Emperor/Authority,Authority URI,Mint,Mint URI,Online Example,Group,Obverse Type,Obverse Legend,Obverse notes,Reverse Type,Reverse Legend,Reverse notes,From Date,To Date,Denomination,Denomination URI,Material,Material URI,Obverse Portrait,Obverse Portrait URI,Reverse Portrait,Reverse Portrait URI,Obverse Deity,Reverse Deity,Issuer,Magistrate,Region,Region URI,Locality,Locality uri,New Region,New Region URI,Object Type URI,Source\n";
foreach ($data as $row){
	$id = $row['Nomisma.org id'];	
	$auth_uri = $row['Nomisma URI for Authority'];
	$array = $usf[$id];
	$fromDate = '';
	$toDate = '';
	
	$dates = explode('-', $array['sdate']);
	if (strlen($dates[0]) > 0){
		$fromDate = number_pad($dates[0], 4);
	}
	if (strlen($dates[1]) > 0){
		$toDate = number_pad($dates[1], 4);
	}
	
	//get mint uri
	switch ($row['Mint']){
		case 'Rome':
			$mint_uri = 'http://nomisma.org/id/rome';
			$region_uri = 'http://nomisma.org/id/latium';
			break;
		case 'Alexandria':
			$mint_uri = 'http://nomisma.org/id/alexandreia_egypt';
			$region_uri = 'http://nomisma.org/id/egypt';
			break;
		case 'Emesa':
			$mint_uri = 'http://nomisma.org/id/emisa';
			$region_uri = 'http://nomisma.org/id/syria';
			break;
		case 'Laodicea':
			$mint_uri = 'http://nomisma.org/id/laodiceia_ad_mare';
			$region_uri = 'http://nomisma.org/id/syria';
			break;
		case 'Asia(?)':
			$mint_uri = '';
			$region_uri = 'http://nomisma.org/id/asia';			
			break;
		case 'Loadicea ad Mare':
			$mint_uri = 'http://nomisma.org/id/laodiceia_ad_mare';
			$region_uri = 'http://nomisma.org/id/syria';
			break;
		case 'Pannonia(?)':
			$mint_uri = '';
			$region_uri = 'http://nomisma.org/id/pannonia';
			break;
		case 'Laodicea ad Mare':
			$mint_uri = 'http://nomisma.org/id/laodiceia_ad_mare';
			$region_uri = 'http://nomisma.org/id/syria';
			break;
		case 'Antioch':
			$mint_uri = 'http://nomisma.org/id/antiocheia_syria';
			$region_uri = 'http://nomisma.org/id/syria';
			break;
		default:
			$mint_uri = '';
			$region_uri = '';
		}
	
	foreach ($row as $k=>$v){
		if ($k == 'Obverse Type'){
			$csv .= '"' . $array['OT'] . '",';
		}
		elseif ($k == 'Reverse Type'){
			$csv .= '"' . $array['RT'] . '",';
		}
		elseif ($k == 'Obverse Legend'){
			$csv .= '"' . $array['OL'] . '",';
		}
		elseif ($k == 'Reverse Legend'){
			$csv .= '"' . $array['RL'] . '",';
		} 
		elseif ($k == 'From Date'){
			$csv .= '"' . $fromDate . '",';
		}
		elseif ($k == 'To Date'){
			$csv .= '"' . $toDate . '",';
		}
		elseif ($k == 'Nomisma URI for Mint'){
			$csv .= '"' . $mint_uri . '",';
		}		
		elseif ($k == 'New Region uri' || $k == 'Region uri'){
			$csv .= '"' . $region_uri . '",';
		}
		elseif ($k == 'Object Type URI'){
			$csv .= '"http://nomisma.org/id/coin",';
		}
		elseif ($k == 'Obverse Portrait URI'){
			if ($row['Obverse Portrait'] == $row['Emperor/Authority']){
				$csv .= '"' . $auth_uri . '",';
			}
		}
		elseif ($k == 'Reverse Deity'){
			$deity = '';
			if (strlen($array['RT']) > 0){				
				$haystack = strtolower($array['RT']);				
				foreach($deityMatchArray as $match=>$name){					
					if ($name != 'Hera' && $name != 'Sol' && strlen(strstr($haystack,strtolower($match)))>0) {
						$deity .= $name . '|';
					}
					//Hera and Sol need special cases because they are commonly part of other works, eg Herakles, soldiers
					elseif ($name == 'Hera' && strlen(strstr($haystack,strtolower($match . ' ')))>0){
						$deity .= $name . '|';
					}
					elseif ($name == 'Sol' && strlen(strstr($haystack,strtolower($match . ' ')))>0){
						$deity .= $name . '|';
					}
				}				
			}
			if (strlen($deity) > 0){
				$deity = substr($deity, 0, -1);
			}
			$csv .= '"' . $deity . '",';
		}
		else {
			$csv .= '"' . $v . '",';
		}
	}
	$csv .= '"' . (strlen($array['OT']) > 0 ? 'USF' : '') . '"';
	$csv .= "\n";
}

//echo $csv;

$handle = fopen('severan_types.csv', 'w');
fwrite($handle, $csv);
fclose($handle);

//functions
function generate_json($doc, $bool){
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
		$nid = '';
		
		if ($bool == true){
			$id = $d['RIC.'];
			$pieces = explode(' ', $id);
			
			switch ($pieces[0]){
				case 'C':
					$auth = 'crl';
					break;
				case 'SA':
					$auth = 'sa';
					break;
				case 'SS':
					$auth = 'ss';
					break;
				case 'E':
					$auth = 'el';
					break;
				case 'G':
					$auth = 'ge';
					break;
			}
			$nid = 'ric.4.' . $auth . '.' . strtolower($pieces[2]);
		}
		
		if (strlen($nid) > 0){
			$x = $nid;
		} else {
			$x = $j;
		}
		
		$array[$x] = $d;
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

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
	if ($number > 0){
		$gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
	} elseif ($number < 0) {
		$gYear = '-' . str_pad((int) abs($number),$n,"0",STR_PAD_LEFT);
	}
	return $gYear;
}

function get_deity_array(){
	//load deities DOM document from Google Docs
	$deityUrl = 'https://spreadsheets.google.com/feeds/list/0Avp6BVZhfwHAdHk2ZXBuX0RYMEZzUlNJUkZOLXRUTmc/od6/public/values';
	$deityDoc = new DOMDocument();
	$deityDoc->load($deityUrl);
	$deityMatches = $deityDoc->getElementsByTagNameNS('http://schemas.google.com/spreadsheets/2006/extended', 'matches');
	$deityNames = $deityDoc->getElementsByTagNameNS('http://schemas.google.com/spreadsheets/2006/extended', 'name');
	$matchArray = Array();
	$nameArray = Array();
	$deityMatchArray = Array();
	foreach($deityMatches as $match){
		$matchArray[] = $match->nodeValue;
	}
	foreach($deityNames as $name){
		$nameArray[] = $name->nodeValue;
	}
	//associate the arrays
	foreach($matchArray as $key=>$value){
		$deityMatchArray[$value] = $nameArray[$key];
	}

	return $deityMatchArray;
}

?>