<?php 

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

$makers = array();
$artists = array();
$places = array();
$states = array();

if (($handle = fopen("cleaned.csv", "r")) !== FALSE) {
	while (($data = fgetcsv($handle, 2500, ',', '"')) !== FALSE) {
		$row = array();
		foreach ($labels as $key=>$label){
			$row[$label] = preg_replace('/\s+/', ' ', $data[$key]);
		}
		
		if (strlen(trim($row['artist'])) > 0){
			$array = array_filter(explode('|', $row['artist']));
			foreach ($array as $val){
				if (!in_array(trim($val), $artists)){
					$artists[] = trim($val);
				}
			}
		}
		if (strlen(trim($row['maker'])) > 0){
			if (!in_array($row['maker'], $makers)){
				$makers[] = $row['maker'];
			}
		}
		if (strlen(trim($row['region'])) > 0){
			if (!in_array($row['region'], $states)){
				$states[] = $row['region'];
			}
		}
		if (strlen(trim($row['mint'])) > 0){
			if (!in_array($row['mint'], $places)){
				$places[] = $row['mint'];
			}
		}
		if (strlen(trim($row['subjplace'])) > 0){
			$array = array_filter(explode('|', $row['subjplace']));
			foreach ($array as $val){
				if (!in_array(trim($val), $places)){
					$places[] = trim($val);
				}
			}
		}
	}
}

//generate csv
generate_csv($makers, 'makers');
generate_csv($artists, 'artists');
generate_csv($states, 'states');
generate_csv($places, 'places');

function generate_csv($array, $type) {
	if ($type == 'places'){
		$csv = '"value","uri","placename"' . "\n";
	} else {
		$csv = '"value","uri"' . "\n";
	}
	
	foreach ($array as $value){
		$csv .= '"' . $value . '",';
		if ($type == 'places'){
			$geonames = query_geonames($value);
			$geonameId = $geonames[0];
			if (strlen($geonameId) > 0){
				$geonames_uri = 'http://www.geonames.org/' . $geonameId;
				$geonames_place = $geonames[1];
			} else {
				$geonames_uri = '';
				$geonames_place = '';
			}
			$csv .= '"' . $geonames_uri . '","' . $geonames_place . '"';
		} else {
			$csv .= '""';
		}
		$csv .= "\n";
	}
	file_put_contents($type . '.csv', $csv);
}

function query_geonames($place){
	$xmlDoc = new DOMDocument();
	$xmlDoc->load('http://api.geonames.org/search?q=' . $place . '&maxRows=10&username=anscoins&style=full');
	$xpath = new DOMXpath($xmlDoc);
	$geonameId = $xpath->query('descendant::geonameId')->item(0)->nodeValue;
	$name = $xpath->query('descendant::name')->item(0)->nodeValue . ' (' . $xpath->query('descendant::countryName')->item(0)->nodeValue . ')';
	$geonames = array($geonameId, $name);
	return $geonames;
}

?>