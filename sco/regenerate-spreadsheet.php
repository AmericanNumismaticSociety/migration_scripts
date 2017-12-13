<?php 
/***
 * Date: November 2017
 * Function: Reprocess the SCO Part 1 version 1 spreadsheet from Oliver into a template that is more compliant
 * with Nomisma-oriented semantics with URIs and correct properties
 ***/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTbTLFJIR5IRNJEAP8t-U9rh4a54hYUL49ZfT_I8YCuTM9zBFpF2VNM7E8ezfWVXKJ1ZFTxE9H1JuBF/pub?output=csv');
$mints = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTGSGNVsQntMzGBx-wcdjVL5ms9OoLsPwwwV5KhaGLAziiMCTzekp1B2T9TNAtAYV4wsqzC5606_8i7/pub?output=csv');
$people = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vR7KBemh_HVyjEgSNdtArcgY0FyHAh3LtFGbVyKMcL4WafBrRfxpY_ur9B1mxNzkxDpOK2JHHcZmHB7/pub?output=csv');
$denominations = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQ8OIT64tKd65I_5UqFGLsrckQCd0QA3TJ4GH-GlMagrgmHf3JaQiUmbmpANxCzuj9BPs7cQc4gyKI9/pub?output=csv');
$stylesheet = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQRCPbdTLG7EIXIt7jo4rz-Ruy565RQS9x9aj2vBwvsbZdC_NBXZweyJVunU3WylvvbcoOAzBJI0OFS/pub?output=csv');



//reprocess $stylesheet into a key->value array
$descriptions = array();
foreach ($stylesheet as $desc){
	$key = $desc['Abbreviation'];
	if (!array_key_exists($key, $descriptions)){
		$descriptions[$key] = $desc['en'];
	} else {
		echo "Error: {$key} appears multiple times\n";
	}
}

//aggregate all records
$records = array();

//missing codes in stylesheet
$missing_codes = array();

//generate the CSV stylesheet for multilingual obverse and reverse type descriptions
//$stylesheet = array();

foreach ($data as $row){
	$record = array();
	$authorities = array();
	$statedAuthorities = array();
	$issuers = array();
	
	//iterate through each column
	foreach ($row as $k=>$v){
		$v = trim($v);
		
		
		if ($k == 'ID'){
			$record['ID'] = 'sc.2.' . str_replace('SCO.', '', $v);
		} elseif ($k == 'SC no.'){
			$record['SC no.'] = 'sc.1.' . $v;
		} elseif ($k == 'Ruler'){
			foreach ($people as $ruler){
				if ($ruler['label'] == $v){
					//if there's an "Under", then the "Under" is Authority
					if (strpos($row['Coregent'], 'Under') === FALSE){
						if (strlen($ruler['authority_uri']) > 0){
							$pieces = explode('|', $ruler['authority_uri']);
							foreach ($pieces as $piece){
								$authorities[] = $piece;
							}
						}
					} else {
						$statedAuthorities[] = $ruler['authority_uri'];						
					}
				}
			}
		} elseif ($k == 'Coregent'){
			foreach ($people as $ruler){
				if ($ruler['label'] == $v){
					if (strlen($ruler['authority_uri']) > 0){
						$pieces = explode('|', $ruler['authority_uri']);
						foreach ($pieces as $piece){
							$authorities[] = $piece;
						}
					} elseif (strlen($ruler['issuer_uri']) > 0){
						$pieces = explode('|', $ruler['issuer_uri']);
						foreach ($pieces as $piece){
							$authorities[] = $piece;
						}
					}
				}
			}
		} elseif ($k == 'Magistrate') {
			foreach ($people as $ruler){
				if ($ruler['label'] == $v){
					if (strlen($ruler['authority_uri']) > 0){
						$pieces = explode('|', $ruler['authority_uri']);
						foreach ($pieces as $piece){
							$issuers[] = $piece;
						}
					} elseif (strlen($ruler['issuer_uri']) > 0){
						$pieces = explode('|', $ruler['issuer_uri']);
						foreach ($pieces as $piece){
							$issuers[] = $piece;
						}
					}
				}
			}
		} elseif ($k == 'Material'){			
			switch ($v){
				case 'AU':
					$record['Material URI'] = 'http://nomisma.org/id/av';
					break;
				case 'AR':
					$record['Material URI'] = 'http://nomisma.org/id/ar';
					break;
				case 'AE':
					$record['Material URI'] = 'http://nomisma.org/id/ae';
			}
		} elseif ($k == 'Denomination'){
			foreach ($denominations as $den){
				if ($den['label'] == $v){
					if (isset($den['uri'])){
						$record['Denomination URI'] = $den['uri'];
					}
				}
			}
		} elseif ($k == 'Mint'){
			$record['Mint Original'] = $v;
			
			foreach ($mints as $mint){
				if ($mint['label (DO NOT EDIT)'] == $v){
					if (strlen($mint['mint_uri']) > 0){
						$record['Mint URI'] = $mint['mint_uri'];
					}
					
					if (strlen($mint['region_uri']) > 0){
						$record['Region URI'] = $mint['region_uri'];
					}
					
					if (strlen($mint['statedAuthority_uri']) > 0){
						$pieces = explode('|', $mint['statedAuthority_uri']);
						foreach ($pieces as $piece){
							$statedAuthorities[] = $piece;
						}
					}
					
					if (strlen($mint['note']) > 0){
						$record['Mint Note'] = $mint['note'];
					}
				}
			}
		} elseif ($k == 'O' || $k == 'R'){
			if (strlen($v) > 0){
				$code = $v;
				
				if (array_key_exists($code, $descriptions)){
					$record[$k . ' (en)'] = str_replace('l.', 'left', str_replace('r.', 'right', $descriptions[$code]));
				} else {
					//the key in the spreadsheet doesn't exist
					echo "Error: Key {$code} non-existent in stylesheet\n";
					if (!in_array($code, $missing_codes)){
						$missing_codes[] = $code;
					}
				}
				
				/*foreach ($descriptions as $desc){
					//pull official description from stylesheet spreadsheet
					if ($code == $desc['Abbreviation']){						
						$record[$k . ' (en)'] = str_replace('l.', 'left', str_replace('r.', 'right', $desc['en']));
					}
				}*/
				
				$record[$k] = $v;
			} else {
				//error if there's no code
				echo "Error: {$record['ID']} has no description code\n.";
				
			}
		}
		elseif ($k == 'O (en)' || $k == 'R (en)'){
			//suppress output (extracted from stylesheet spreadsheet, above)
			
			//replace r., l.
			//$desc = str_replace('l.', 'left', str_replace('r.', 'right', $v));
			//$record[$k] = $desc;
		}	
		//for everything else
		else {
			if (strlen($v) > 0){
				$record[$k] = $v;
			}
		}
	}	
	
	//add authority arrays
	if (count($authorities) > 0){
		$record['Authority URI'] = $authorities;
	}
	
	if (count($statedAuthorities) > 0){	
		$record['Stated Authority URI'] = $statedAuthorities;		
	}
	
	if (count($issuers) > 0){
		$record['Issuer URI'] = $issuers;
		
	}
	
	
	$records[] = $record;
	unset($statedAuthorities);
}

asort($missing_codes);
foreach ($missing_codes as $code){
	echo "{$code}\n";
}

write_csv($records);

//var_dump($stylesheet);

//write_stylesheet($stylesheet);

//output CSV
function write_csv ($records){
	$header = array('SC seq.','ID','SC no.','Authority URI','Stated Authority URI','Mint URI','Mint Note','Region URI','Issuer URI','SE Date','Aradian Era date','Start Date','End Date','OBV','R: leftField','R: rightField','R: outerLeftField','R: outerRightField','R: innerLeftField','R: betweenSeleucusIIAndNike','R: betweenApolloAndTripod','R: betweenNikeAndTrophy','R: innerRightField','R: onThrone','R: underThrone','R: leftWing','R: rightWing','R: above','R: below','R: exergue','R: betweenPilei','R: betweenLegs','R: onProw','R: onOmphalos','R: onHaunch','Material URI','Denomination URI','Mint Original','Series','O','R','O (en)','R (en)','R Legend','R Script');
	$csv = implode(',', $header) . "\n";
	
	//iterate through each record and evaluate whether the array key is set
	foreach ($records as $record){
		foreach ($header as $index=>$key){
			if (array_key_exists($key, $record)){
				if (is_array($record[$key])){
					$csv .= '"' . implode('|', $record[$key]) . '"';
				} else {
					$val = str_replace('"', '""', $record[$key]);
					$csv .= '"' . $val . '"';
				}
			} else {
				$csv .= '""';
			}
			//add comma if it's not the last column
			if ($index < (count($header) - 1)){
				$csv .= ',';
			}
		}
		$csv .= "\n";
	}
	
	//echo $csv;
	//write file to disk
	file_put_contents('sco.csv', $csv);
}

function write_stylesheet($stylesheet){
	ksort($stylesheet);
	$csv = "Abbreviation,en\n";
	
	foreach ($stylesheet as $k=>$array){
		//iterate through each value of the array for each assigned code
		foreach ($array as $v){
			$csv .= '"' . $k . '","' . $v . '"' . "\n";
		}		
	}
	
	file_put_contents('stylesheet.csv', $csv);
}

/***** CSV FUNCTIONS *****/
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

