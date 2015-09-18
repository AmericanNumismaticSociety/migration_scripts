<?php 

//libxml timeout
$options = ['http' => ['method' => 'GET','timeout' => '10']];
$context = stream_context_create($options);
libxml_set_streams_context($context);

//Berlin's list of LIDO XML files
$list = file_get_contents('http://ww2.smb.museum/mk_edit/coin_export/17/content.txt');
$files = explode(PHP_EOL, $list);

$csv = '"object_number","URI","ref"' . "\n";

$count = 1;
foreach ($files as $file){
	if (strlen($file) > 0){
		$fileArray = explode('/', $file);
		$objectNumber = str_replace('.xml', '', $fileArray[count($fileArray) - 1]);
		$dom = new DOMDocument('1.0', 'UTF-8');
		if ($dom->load($file) === FALSE){
			echo "{$file} failed to load.\n";
		} else {
			$xpath = new DOMXpath($dom);
			$xpath->registerNamespace("lido", "http://www.lido-schema.org");
			$refNodes = $xpath->query("descendant::lido:relatedWorkSet[lido:relatedWorkRelType/lido:term='reference']/lido:relatedWork/lido:object/lido:objectNote");
		
			if ($refNodes->length > 0){
				$ref = $refNodes->item(0)->nodeValue;
				if (strstr($ref, 'Price, Alexander') !== FALSE){
					$pieces = explode(',', $ref);
					
					$num = trim($pieces[2]);
					if (ctype_alpha(substr($num, 0, 1))){
						$letter = substr($num, 0, 1);
						$num = $letter . ltrim(substr($num, 1), '0');
					} else {
						$num = ltrim($num, '0');
					}
					
					$id = 'price.' . $num;
					//test which number matches in OCRE
					$csv .= '"' . $objectNumber . '","';
					
					$url = 'http://numismatics.org/pella/id/' . $id . '.xml';
					$file_headers = @get_headers($url);
					if ($file_headers[0] == 'HTTP/1.1 200 OK'){
						//create line in CSV
						$uri = 'http://numismatics.org/pella/id/' . $id;
						$csv .= $uri;
						echo "{$count}: {$objectNumber} - {$uri}\n";
					} else {
						echo "{$count}: {$objectNumber} - no match for {$ref}.\n";
					}
					$csv .= '","' . $ref .'"' . "\n";
				}
				
			}			
		}		
	}
	$count++;
}

//write csv file
file_put_contents('berlin-concordances.csv', $csv);

?>