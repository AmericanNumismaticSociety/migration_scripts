<?php 
 /*****
 * Extract position translations from Google Sheets and generate XML output for Numishare config
 *****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQzbB17TYBmMsKA5Btcwr_cWfFbqsZrIHPNWrXIttdiCeKkXUGjtDVgWK8QvQd86l-hrKkmP5bsYs-9/pub?output=csv');

$doc = new XMLWriter();

//$doc->openUri('php://output');
$doc->openUri('positions.xml');
$doc->setIndent(true);
//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
$doc->setIndentString("    ");
$doc->startDocument('1.0','UTF-8');
	$doc->startElement('positions');
		foreach ($data as $row){			
			$doc->startElement('position');
				$doc->writeAttribute('value', $row['code']);
				$doc->writeAttribute('side', $row['side']);
				
				//create translation for each column with a value
				foreach ($row as $k=>$v){
					if ($k != 'code' && $k != 'side'){
						if (strlen($v) > 0){
							$doc->startElement('label');
								$doc->writeAttribute('lang', $k);
								$doc->text(trim($v));
							$doc->endElement();
						}
					}
				}
			$doc->endElement();
		}
	$doc->endElement();
//close file
$doc->endDocument();
$doc->flush();

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