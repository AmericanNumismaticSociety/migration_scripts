<?php 

$distinctNames = array();

if ($handle = opendir('guides')) {
	//$csv = '"id","value","should_be","uri","prefLabel"' . "\n";
	$csv = '"id","value","type","creator"' . "\n";
	while (false !== ($entry = readdir($handle))) {
		if (strstr($entry, '.xml')){
			$id = strstr($entry, '.xml', true);
			$doc = new DOMDocument();
			$doc->load('guides/' . $entry);
			$xpath = new DOMXPath($doc);
			$xpath->registerNamespace('ead', "urn:isbn:1-931666-22-9");
			$xpath->registerNamespace('mods', "http://www.loc.gov/mods/v3");
			
			$names = $xpath->query('descendant::ead:persname|descendant::ead:corpname|descendant::ead:origination|descendant::mods:topic');
			//generate distinct list
			/*foreach ($names as $name){
				$val = preg_replace('!\s+!', ' ', trim($name->nodeValue));
				if (!in_array($val, $distinctNames)){
					$distinctNames[] = $val;
				}
			}*/
			
			//generate total list
			foreach ($names as $name){
				echo "Processing {$name->nodeValue}\n";
				if (strpos($name->nodeValue, 'Audubon') > -1){
					$type = 'geographic';
					$creator = '';
				} else {
					switch ($name->localName){
						case 'persname':
							$type = 'personal';
							break;
						case 'corpname':
							$type = 'corporate';
							break;
						default:
							$type = '';
					}
					if ($name->localName == 'origination'){
						$creator = 'true';
					} else {
						$creator = '';
					}
				}
				
				
				$csv .= '"' . $id . '","' . preg_replace('!\s+!', ' ', trim($name->nodeValue)) . '","' . $type . '","' . $creator . '"' . "\n";  
			}
		}
	}
	file_put_contents('all-names.csv', $csv);
	//echo $csv;
	
	//generate distinct names CSV
	/*sort($distinctNames);
	foreach($distinctNames as $k=>$name){
		$query = 'http://viaf.org/viaf/search?query=local.names+all+%22' . urlencode($name) . '%22&sortKeys=holdingscount&http:accept=application/rss%2bxml';
		$atom = new DOMDocument();
		$atom->load($query);
		$xpath = new DOMXPath($atom);
		$items = $xpath->query("descendant::item[1]");
		if ($items->length > 0){			
			foreach ($items as $item){
				$uri = $item->getElementsByTagName('link')->item(0)->nodeValue;
				$prefLabel = $item->getElementsByTagName('title')->item(0)->nodeValue;
			}
		} else {
			$uri = '';
			$prefLabel = '';
		}
		echo "Processing {$name}\n";
		$csv .= '"' . $k . '","' . $name . '","","' . $uri . '","' . $prefLabel . '"' . "\n";
	}
	file_put_contents('distinct-names.csv', $csv);
	//echo $csv;*/
}

?>