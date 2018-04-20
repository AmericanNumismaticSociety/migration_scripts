<?php 

$mint_array = array();
$den_array = array();
$dis_array = array();
$findspot_array = array();

if ($handle = opendir('/usr/local/projects/igch/data')) {
	while (false !== ($entry = readdir($handle))) {
		if (strstr($entry, '.xml')){
			$id = strstr($entry, '.xml', true);
			$doc = new DOMDocument();
			$doc->load('/usr/local/projects/igch/data/' . $entry);
			$xpath = new DOMXPath($doc);
			$xpath->registerNamespace('xhtml', "http://www.w3.org/1999/xhtml");
			
			//pre-processing
			//$dis_array[$id] = process_refs($xpath);
			$findspot_array[$id] = process_findspot($xpath);
			/*$links = $xpath->query('//xhtml:pre/xhtml:span[@resource]');
			process_mints($mints);
			extract_denominations($links);*/
		}
	}
}

ksort($findspot_array);
$csv = '"id","val"' . "\n";
foreach ($findspot_array as $k=>$v){
	$csv .= '"' . $k . '","' . str_replace('"', '""', $v) . '"' . "\n";
}
file_put_contents('findspot-list.csv', $csv);
//var_dump($findspot_array);
//create_mint_csv($mint_array);
//create_den_csv($den_array);
//create_dis_csv($dis_array);
//var_dump($findspot_array);
//create_findspot_csv($findspot_array);

/******* INITIAL PROCESSING FUNCTION *******/
function extract_denominations($links){
	GLOBAL $den_array;
	foreach ($links as $link){
		$val = trim($link->nodeValue);
		$den = preg_match('/[0-9]+\s([^\s]+)/', $val, $matches);
		foreach ($matches as $k=>$v){
			if ($k > 0){
				if (!strstr($v, '(') && !strstr($v, ')') && !strstr($v, '[') && !strstr($v, ']') && !strstr($v, ';')){
					if (!in_array($v, $den_array)){
						$den_array[] = $v;
					}
				}				
			}
		}
		
		//echo "{$val}\n";
	}
}

function create_den_csv($den_array){
	asort($den_array);
	$csv = '"val","uri","type"' . "\n";
	foreach ($den_array as $val){
		$csv .= '"' . str_replace('"', '%22', $val) . '","","denomination"' . "\n";
	}
	file_put_contents('denominations.csv', $csv);
}

function process_mints($links){	
	GLOBAL $mint_array;
	foreach ($links as $link){
		$mint_array[] = $link->getAttribute('resource');
	}
}

function create_mint_csv($mint_array){
	$mintF = array_unique($mint_array);
	asort($mintF);
	$csv = '"value","uri","type"' . "\n";
	foreach ($mintF as $id){
		//echo "{$id}\n";
		$uri = '';
		$type = '';
		$dom = new DOMDocument('1.0', 'UTF-8');
		if ($dom->load('/usr/local/projects/nomisma-ids/id/' . $id . '.txt') === FALSE){
			echo "{$id} does not exist.\n";
		} else {
			$uri = 'http://nomisma.org/id/' . $id;
			$type = $dom->documentElement->getAttribute('typeof');
		}
	
		$csv .= '"' . $id . '","' . $uri . '","' . $type . '"' . "\n";
	}
	
	file_put_contents('mints.csv', $csv);
}

function create_dis_csv($dis_array){
	$csv = '"id","value","type"' . "\n";
	foreach ($dis_array as $k=>$v){
		foreach ($v as $key=>$text){
			$text = trim(str_replace('†', '', str_replace('"', '&#x022;', $text)));
			$frags = explode(',', $text);
			if ($key == 0){
				$type = 'disposition';
			} else if (strlen($frags[0]) < 20){
				$type = 'ref';
			} else {
				$type = 'note';
			}
			$csv .= '"' . $k . '","' . $text . '","' . $type . '"' . "\n";
		}
	}
	file_put_contents('disposition-refs.csv', $csv);
}

function create_findspot_csv($findspot_array){
	$csv = '"id","value","place","geonames_uri","geonames_place"' . "\n";
	$count = 0;
	foreach ($findspot_array as $k=>$text){
		echo "{$count}: {$k}\n";
		$text = str_replace('- ', '', $text);
		$frags = explode(',', $text);
		$place = trim(preg_replace('/\(.*\)/s', '', str_replace('?', '', $frags[0])));
		if (strlen($place) > 0){
			$geonames = query_geonames($place);
			$geonameId = $geonames[0];
			if (strlen($geonameId) > 0){
				$geonameId = 'http://www.geonames.org/' . $geonameId;
				$geonames_place = $geonames[1];
			} else {
				$geonameId = '';
				$geonames_place = '';
			}
		} else {
			$geonameId = '';
			$geonames_place = '';
		}
		$csv .= '"' . $k . '","' . $text . '","' . $place . '","' . $geonameId . '","' . $geonames_place . '"' . "\n";
		$count++;
	}
	file_put_contents('findspots.csv', $csv);
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

function process_findspot ($xpath){
	$pre = $xpath->query('//xhtml:pre')->item(0)->nodeValue;
	$pre = preg_replace('/((\r?\n)|(\r\n?))/', '', preg_replace('/[0-9]+\.[0-9]+/', '', $pre));
	$buffer = preg_match('/(.*)Burial:/s', $pre, $matches);
	preg_match('/^[0-9]+\s(.*)/s', preg_replace('/\([0-9]+\)/', '', $matches[1]), $matches);
	$text = $matches[1];
	
	$content = array();
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $text) as $v){
		//ignore blank lines (usually at the end)
		if (strlen($v) > 0){
			$content[] = $v;
		}
	}
	//$content = reverse_iteration($content, 0);
	$findspot = trim(preg_replace('!\s+!', ' ', $content[0]));
	return $findspot;
}

//this function attempts to process disposition and refs
function process_refs ($xpath) {
	$pre = $xpath->query('//xhtml:pre')->item(0)->nodeValue;
	$buffer = preg_match('/Disposition:\ (.*)/s', $pre, $matches);
	$text = $matches[1];
	
	//process each line
	$content = array();
	foreach(preg_split("/((\r?\n)|(\r\n?))/", $text) as $v){
		//ignore blank lines (usually at the end)
		if (strlen($v) > 0){
			$content[] = $v;
		}
	}
	$content = reverse_iteration($content, 5);
	return $content;
}

function reverse_iteration($content, $len){
	for($i = count($content) - 1; $i >= 0; $i--) {
		if ($i > 0){
			preg_match('/^[\s]+/', $content[$i], $m);
				
			if (strlen($m[0]) > $len){
				//if there are more than 5 leading whitespaces, assume this line belongs to the previous
				$frag = preg_replace('!\s+!', ' ', $content[$i]);
				$content[$i - 1] = $content[$i - 1] . $frag;
				unset($content[$i]);
			} else {
				//otherwise strip multiple whitespaces
				$content[$i] = preg_replace('/^[\s]+/', '', $content[$i]);
			}
			//echo $content[$i] . "\n";
		}
	}
	return $content;
}

?>