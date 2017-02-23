<?php 

$full = array();

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
		"OrigIntenUse","Authenticity","PostManAlt","diameter","height","width","depth","privateinfo");

$file = file_get_contents('islamic-fmexport.csv');
//escape conflicting XML characters
$cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', preg_replace("[\x1D]", "|", str_replace('>', '&gt;', str_replace('<', '&lt;', str_replace('&', 'and', preg_replace("[\x0D]", "\n", $file))))));
file_put_contents('clean.csv', $cleaned);

//parse each row to extract out all possible mint-region-locality combinations
if (($cleanHandle = fopen('clean.csv', "r")) !== FALSE) {
	$count = 0;
	while (($data = fgetcsv($cleanHandle, 2500, ',', '"')) !== FALSE) {
		$row = array();
		foreach ($labels as $key=>$label){
			$row[$label] = preg_replace('/\s+/', ' ', $data[$key]);
		}
		$mints = array_filter(explode('|', $row['mint']));
		$regions = array_filter(explode('|', $row['region']));
		$localities = array_filter(explode('|', $row['locality']));
		
		foreach ($mints as $mint){
			$mint = trim($mint);
				
			if (count($regions) == 0){
				if (count($localities) == 0){
					$full[] = array($mint, '', '');
				} else {
					foreach ($localities as $locality){
						$locality = trim($locality);
						$full[] = array($mint, '', $locality);
					}
				}
		
			} else {
				foreach ($regions as $region){
					$region = trim($region);
					if (count($localities) == 0){
						$full[] = array($mint, $region, '');
					} else {
						foreach ($localities as $locality){
							$locality = trim($locality);
							$full[] = array($mint, $region, $locality);
						}
					}
				}
			}
		}
		$count++;
	}	
}

//restructure all possible mints, regions, and localities into an associative array of unique values
$places = parse_mints();

ksort($places);
//output $places as a CSV
$tsv = "department\tmint\tregion\tlocality\n";
foreach ($places as $mint=>$regions){
	ksort($regions);
	
	foreach ($regions as $region=>$localities){
		asort($localities);
		
		foreach ($localities as $locality){
			$tsv .= "^Islamic^\t^{$mint}^\t^{$region}^\t^{$locality}^\n";
		}		
	}
}

//echo $tsv;

//write CSV file to disk
file_put_contents('islamic_mints.tsv', $tsv);

/******************** FUNCTIONS **********************/
function parse_mints(){
	GLOBAL $full;

	$places = array();
	
	foreach ($full as $arr){
		$mint = $arr[0];
		if (!array_key_exists($mint, $places)){
			$places[$mint] = parse_region($mint);
		}
	}
	
	return $places;
}

function parse_region($mint){
	GLOBAL $full;
	
	$regions = array();
	
	foreach ($full as $arr){
		if ($arr[0] == $mint){
			$region = $arr[1];
			if (!array_key_exists($region, $regions)){
				$regions[$region] = parse_locality($mint, $region);
			}			
		}		
	}
	return $regions;
}

function parse_locality($mint, $region){
	GLOBAL $full;
	
	$localities = array();
	
	foreach ($full as $arr){
		if ($arr[0] == $mint && $arr[1] == $region){
			$locality = $arr[2];
			if (!in_array($locality, $localities)){
				echo "{$mint}: {$region}: {$locality}\n";
				$localities[] = $locality;
			}
		}
	}
	
	return $localities;
}

//var_dump($places);

?>