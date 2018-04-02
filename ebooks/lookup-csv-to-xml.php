<?php 

 /****
 * Convert lookup.csv file to XML to use in XSLT for processing TEI to include authority URIs
 *****/

$books = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vR0JjBIFxLIpF-84MlHIw0DelsbIPPJ1eHV39SmOoRylG1JQ2RKvo1MdwyoxwNp0XfLMwEjq2MSEqdL/pub?gid=0&single=true&output=csv');
$data = generate_json('lookup.csv');
$tree = array();

foreach ($data as $row){
	$tree[$row['id']][] = array('key'=>$row['key'], 'type'=>$row['type'], 'uri'=>$row['uri'], 'label'=>$row['label']);
}

//write XML from restructured tree

$writer = new XMLWriter();
$writer->openURI('lookups.xml');
//$writer->openURI('php://output');
$writer->startDocument('1.0','UTF-8');
$writer->setIndent(true);
//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
$writer->setIndentString("    ");

$writer->startElement('lookups');

foreach ($tree as $k=>$names){
	//get the Donum ID
	
	foreach ($books as $book){
		if ($book['filename'] == $k){
			$id = "nnan{$book['donum']}";
		}
	}	
	$writer->startElement($id);
		foreach ($names as $name){
			$writer->startElement('name');
				if (strlen($name['label']) > 0){
					$writer->writeAttribute('label', $name['label']);
				}
				if (strlen($name['uri']) > 0){
					$writer->writeAttribute('uri', $name['uri']);
				}
				$writer->writeAttribute('type', $name['type']);
				$writer->text($name['key']);
			$writer->endElement();
		}
	$writer->endElement();
}

$writer->endElement();
$writer->flush();


/***** FUNCTIONS *****/
function generate_json($doc){
	$keys = array();
	$geoData = array();
	
	$data = csvToArray($doc, ',');
	
	// Set number of elements (minus 1 because we shift off the first row)
	$count = count($data) - 1;
	
	//Use first row for names
	$labels = array_shift($data);
	
	foreach ($labels as $label) {
		$keys[] = $label;
	}
	
	// Bring it all together
	for ($j = 0; $j < $count; $j++) {
		$d = array_combine($keys, $data[$j]);
		$geoData[$j] = $d;
	}
	return $geoData;
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