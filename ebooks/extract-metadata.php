<?php 

$directories = glob('/home/komet/ans_migration/ebooks/2017_books/*' , GLOB_ONLYDIR);

$csv = "filename,title,author,editor\n";

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
				
				$csv .= '"' . $filename . '","' . ucwords(strtolower($title)) . '",';
				
				$authors = $xpath->query("descendant::tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:author");
				$editors = $xpath->query("descendant::tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:editor");
				
				$author_array = array();
				foreach ($authors as $author){
					$author_array[] = ucwords(strtolower($author->nodeValue));
				}
				
				$editor_array = array();
				foreach ($editors as $editor){
					$editor_array[] = ucwords(strtolower($editor->nodeValue));
				}
				
				$csv .= '"' . implode('|', $author_array) . '",';
				$csv .= '"' . implode('|', $editor_array) . '"';
				
				$csv .= "\n";
			}
		}
	}
}


file_put_contents('metadata.csv', $csv);
?>