<?php 
 /*****
 * Author: Ethan Gruber
 * Date: January 2018
 * Function: extract every person, corporate body, and place from all TEI files and write each category
 * of entity to a CSV for further reconciliation in OpenRefine
 *****/

$people = array();
$places = array();

$files = scandir('tei');

foreach ($files as $file){
	if (strpos($file, '.xml') !== FALSE){
		echo "{$file}\n";
		
		$dom = new DOMDocument('1.0', 'UTF-8');
		if ($dom->load('tei/' . $file) === FALSE){
			echo "{$file} failed to load.\n";
		} else {	
			$xpath = new DOMXpath($dom);
			$xpath->registerNamespace("tei", "http://www.tei-c.org/ns/1.0");
			$title = ucwords(strtolower($xpath->query("descendant::tei:teiHeader/tei:fileDesc/tei:titleStmt/tei:title")->item(0)->nodeValue));
			
			$peopleArray = array('file'=>$file, 'title'=>$title, 'entities'=>array());
			$placesArray = array('file'=>$file, 'title'=>$title, 'entities'=>array());
			
			$persNames = $xpath->query("descendant::tei:body/descendant::tei:name[@type='pname'][not(ancestor::tei:note[@place='foot'])]");
			$placeNames = $xpath->query("descendant::tei:body/descendant::tei:name[@type='place'][not(ancestor::tei:note[@place='foot'])]");
			
			foreach ($persNames as $name) {
				$val= preg_replace(array('/\r/', '/\n/'), '', trim($name->nodeValue));
				
				if (!in_array($val, $peopleArray['entities'])){
					$peopleArray['entities'][] = $val;
				}
			}
			foreach ($placeNames as $name) {
				$val= preg_replace(array('/\r/', '/\n/'), '', trim($name->nodeValue));
				
				if (!in_array($val, $placesArray['entities'])){
					$placesArray['entities'][] = $val;
				}
			}
			
			//add local to global array
			$people[] = $peopleArray;
			$places[] = $placesArray;
		}
	}
}


//write CSV
$csv = "file,title,name,type\n";
foreach ($people as $doc){
	$file = $doc['file'];
	$title = $doc['title'];
	foreach ($doc['entities'] as $entity){
		$entity = str_replace('"', '""', $entity);
		$csv .= $file . ',' . '"' . $title . '","' . $entity . '","person"' . "\n"; 
	}
}
file_put_contents('people.csv', $csv);

$csv = "file,title,name,type\n";
foreach ($places as $doc){
	$file = $doc['file'];
	$title = $doc['title'];
	foreach ($doc['entities'] as $entity){
		$entity = str_replace('"', '""', $entity);
		$csv .= $file . ',' . '"' . $title . '","' . $entity . '","place"' . "\n";
	}
}
file_put_contents('places.csv', $csv);

?>