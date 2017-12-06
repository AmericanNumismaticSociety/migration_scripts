<?php 
 /*****
 * Author: Ethan Gruber
 * Date: December 2017
 * Function: Process the Seleucid Coins Online spreadsheet from Google Drive into two sets of NUDS/XML documents:
 * 1. SCO version 2 
 * 2. SC version 1 that points to the new URI
 *****/

$data = generate_json('sco.csv');
$deities = generate_json('https://docs.google.com/spreadsheet/pub?hl=en_US&hl=en_US&key=0Avp6BVZhfwHAdHk2ZXBuX0RYMEZzUlNJUkZOLXRUTmc&single=true&gid=0&output=csv');

$records = array();

foreach($data as $row){
	//call generate_nuds twice to generate two sets of NUDS records
	generate_nuds($row, $recordIdKey='ID', $mode='new');
}

//functions
function generate_nuds($row, $recordIdKey, $mode){
	GLOBAL $deities;
	
	$recordId = $row[$recordIdKey];
	
	if (strlen($recordId) > 0){
		$doc = new XMLWriter();
		
		$doc->openUri('php://output');
		//$doc->openUri('nuds/' . $recordId . '.xml');
		$doc->setIndent(true);
		//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
		$doc->setIndentString("    ");
		
		$doc->startDocument('1.0','UTF-8');
		
		$doc->startElement('nuds');
			$doc->writeAttribute('xmlns', 'http://nomisma.org/nuds');
				$doc->writeAttribute('xmlns:xs', 'http://www.w3.org/2001/XMLSchema');
				$doc->writeAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
				$doc->writeAttribute('recordType', 'conceptual');
			
			//control
			$doc->startElement('control');
				$doc->writeElement('recordId', $recordId);
				
				//handle semantic relation with other record
				if ($mode == 'new'){
					if (strlen($row['SC no.']) > 0){
						$doc->startElement('otherRecordId');
							$doc->writeAttribute('semantic', 'dcterms:replaces');
							$doc->text($row['SC no.']);
						$doc->endElement();
					}
					$doc->writeElement('publicationStatus', 'approved');	
					$doc->startElement('maintenanceAgency');
						$doc->writeElement('agencyName', 'American Numismatic Society');
					$doc->endElement();
					$doc->writeElement('maintenanceStatus', 'derived');
				} else {
					if (strlen($row['SC no.']) > 0){
						$doc->startElement('otherRecordId');						
							$doc->writeAttribute('semantic', 'dcterms:isReplacedBy');
							$doc->text($row['ID']);
						$doc->endElement();
					}
					$doc->writeElement('publicationStatus', 'inProcess');	
					$doc->startElement('maintenanceAgency');
						$doc->writeElement('agencyName', 'American Numismatic Society');
					$doc->endElement();
					$doc->writeElement('maintenanceStatus', 'cancelledReplaced');
				}
				
				//maintenanceHistory
				$doc->startElement('maintenanceHistory');
					$doc->startElement('maintenanceEvent');
						$doc->writeElement('eventType', 'derived');
						$doc->startElement('eventDateTime');
							$doc->writeAttribute('standardDateTime', date(DATE_W3C));
							$doc->text(date(DATE_RFC2822));
						$doc->endElement();
						$doc->writeElement('agentType', 'machine');
						$doc->writeElement('agent', 'PHP');
						$doc->writeElement('eventDescription', 'Generated from CSV fro Google Drive.');
					$doc->endElement();
				$doc->endElement();
			
				//semanticDeclaration
				$doc->startElement('semanticDeclaration');
					$doc->writeElement('prefix', 'dcterms');
					$doc->writeElement('namespace', 'http://purl.org/dc/terms/');
				$doc->endElement();
				
				$doc->startElement('semanticDeclaration');
					$doc->writeElement('prefix', 'nmo');
					$doc->writeElement('namespace', 'http://nomisma.org/ontology#');
				$doc->endElement();
			
				//rightsStmt
				$doc->startElement('rightsStmt');
					$doc->writeElement('copyrightHolder', 'American Numismatic Society');
					$doc->startElement('license');
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', 'http://opendatacommons.org/licenses/odbl/');
					$doc->endElement();
				$doc->endElement();
			$doc->endElement();
		
			//descMeta
			$doc->startElement('descMeta');
		
			$doc->endElement();		
		//close NUDS
		$doc->endElement();
	}	
}


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