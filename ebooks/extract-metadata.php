<?php 

$directories = glob('/home/komet/ans_migration/ebooks/*' , GLOB_ONLYDIR);

$csv = 'filename,title,author1,author2,author3';

foreach ($directories as $folder){
	$files = scandir($folder);
	//echo $folder . "\n";
	//var_dump($files);
	
	foreach ($files as $filename){
		if (strpos($filename, '.xml') !== FALSE){
			$file = "{$folder}/{$filename}";
			
			$dom = new DOMDocument('1.0', 'UTF-8');
			if ($dom->load($file) === FALSE){
				echo "{$file} failed to load.\n";
			} else {
				$xpath = new DOMXpath($dom);
				$xpath->registerNamespace("tei", "http://www.tei-c.org/ns/1.0");
				$title = $xpath->query("descendant::tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:title")->item(0)->nodeValue;
				
				$csv .= '"' . $filename . '","' . $title . '",';
				
				$authors = $xpath->query("descendant::tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:author");
				
				foreach ($authors as $author){
					$csv .= '"' . $author->nodeValue . '",';
				}
				
				$csv .= "\n";
			}
		}
	}
}


file_put_contents('metadata.csv', $csv);
?>