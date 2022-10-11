<?php 

/*****
 * Author: Ethan Gruber
 * Date: October 2022
 * Function: Transform the Shubin collection spreadsheet into TEI documents with IIIF image references
 */

$collections = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vRGoDJlDraepfvM_NSxbNkOatn6JR6KakrivUDrB0bksuM2fWmXHR94qB19P_Ch90kGVGwLdemFtgKo/pub?gid=0&single=true&output=csv');

foreach ($collections as $collection){
	$id = $collection['ID'];
	
	//get sheet URL for current box
	switch($id){
		case 'shubin.0001':
			$box = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRGoDJlDraepfvM_NSxbNkOatn6JR6KakrivUDrB0bksuM2fWmXHR94qB19P_Ch90kGVGwLdemFtgKo/pub?gid=1892207840&single=true&output=csv';
			break;
		case 'shubin.0002':
			$box = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRGoDJlDraepfvM_NSxbNkOatn6JR6KakrivUDrB0bksuM2fWmXHR94qB19P_Ch90kGVGwLdemFtgKo/pub?gid=135063248&single=true&output=csv';
			break;
		case 'shubin.0003':
			$box = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRGoDJlDraepfvM_NSxbNkOatn6JR6KakrivUDrB0bksuM2fWmXHR94qB19P_Ch90kGVGwLdemFtgKo/pub?gid=1091923398&single=true&output=csv';
			break;
		case 'shubin.0004':
			$box = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRGoDJlDraepfvM_NSxbNkOatn6JR6KakrivUDrB0bksuM2fWmXHR94qB19P_Ch90kGVGwLdemFtgKo/pub?gid=207363081&single=true&output=csv';
			break;
		case 'shubin.0005':
			$box = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRGoDJlDraepfvM_NSxbNkOatn6JR6KakrivUDrB0bksuM2fWmXHR94qB19P_Ch90kGVGwLdemFtgKo/pub?gid=1164197764&single=true&output=csv';
			break;
	}
	
	$data = generate_json($box);
	
	$doc = new XMLWriter();
	
	$doc->openUri('php://output');
	//$doc->openUri("tei/{$id}.xml");
	$doc->setIndent(true);
	//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
	$doc->setIndentString("    ");
	
	$doc->startDocument('1.0','UTF-8');
	
	//start TEI file
	$doc->startElement('TEI');
		$doc->writeAttribute('xmlns', 'http://www.tei-c.org/ns/1.0');
		$doc->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$doc->writeAttribute('xsi:schemaLocation', 'http://www.tei-c.org/ns/1.0 http://www.tei-c.org/release/xml/tei/custom/schema/xsd/tei_all.xsd');
		$doc->writeAttribute('xml:id', $id);
		
		//TEI Header
		$doc->startElement('teiHeader');
			$doc->startElement('fileDesc');
				$doc->startElement('titleStmt');
					$doc->writeElement('title', $collection['Title']);
					$doc->startElement('author');
						$doc->startElement('persName');
							$doc->writeAttribute('ref', 'http://numismatics.org/authority/shubin_michael');
							$doc->text('Shubin, Michael');
						$doc->endElement();
					$doc->endElement();
				$doc->endElement();
				
				//these items probably shouldn't have a publisher
				$doc->startElement('publicationStmt');
					$doc->startElement('publisher');
						$doc->writeElement('name', 'American Numismatic Society');
						$doc->startElement('idno');
							$doc->writeAttribute('type', 'URI');
							$doc->text('http://numismatics.org/authority/american_numismatic_society');
						$doc->endElement();
					$doc->endElement();
					$doc->writeElement('pubPlace', 'New York (N.Y.)');
				$doc->endElement();
				
				//sourceDesc
				$doc->startElement('sourceDesc');
					$doc->startElement('biblStruct');
						$doc->startElement('monogr');
							$doc->writeElement('title', $collection['Title']);
							$doc->startElement('author');
								$doc->startElement('persName');
									$doc->writeAttribute('ref', 'http://numismatics.org/authority/shubin_michael');
									$doc->text('Shubin, Michael');
								$doc->endElement();
							$doc->endElement();
							$doc->startElement('imprint');
								$doc->startElement('date');
									$doc->writeAttribute('min', '1970');
									$doc->writeAttribute('max', '2000');
									$doc->text('1970-2000');
								$doc->endElement();
							$doc->endElement();
						$doc->endElement();
					$doc->endElement();
				//add extent later
				$doc->endElement();
			
			//end fileDesc
			$doc->endElement();
			
			//profileDesc (general document metadata)
			$doc->startElement('profileDesc');
				$doc->startElement('langUsage');
					$doc->startElement('language');
						$doc->writeAttribute('ident', 'en');
						$doc->text('English');
					$doc->endElement();
				$doc->endElement();
			
				$doc->startElement('textClass');
					$doc->startElement('classCode');
						$doc->writeAttribute('scheme', 'http://vocab.getty.edu/aat/');
						$doc->text('300264354');
					$doc->endElement();
				$doc->endElement();
			//end profileDesc
			$doc->endElement();
			
			$doc->startElement('revisionDesc');
				$doc->startElement('change');
					$doc->writeAttribute('when', substr(date(DATE_W3C), 0, 10));
					$doc->text('Generated TEI document from spreadsheet created by ANS Librarian, David Hill.');
				$doc->endElement();
			$doc->endElement();
	
		//end TEI header
		$doc->endElement();
		
		//facsimiles
		$num = 0;
		foreach ($data as $page){
			$filename = $page['Filename'];
			
			$doc->startElement('facsimile');
				$doc->writeAttribute('xml:id', str_replace('.jpg', '', $filename));
				
				//designate first file as depiction
				if ($num == 0){
					$doc->writeAttribute('style', 'depiction');
				}
				
				$doc->startElement('media');
					$doc->writeAttribute('url', "https://images.numismatics.org/archivesimages%2Farchive%2F{$filename}");
					$doc->writeAttribute('n', $page['Note/Description']);
					$doc->writeAttribute('mimeType', 'image/jpeg');
					$doc->writeAttribute('type', 'IIIFService');
					//$doc->writeAttribute('height', "{$page['height']}px");
					//$doc->writeAttribute('width', "{$page['width']}px");
				$doc->endElement();
			$doc->endElement();
		}
	
	//end TEI file
	$doc->endElement();
	
	//close file
	$doc->endDocument();
	$doc->flush();
}

//write CSV into an array
function generate_json($doc){
	$keys = array();
	$array = array();
	
	$csv = csvToArray($doc, ',');
	
	// Set number of elements (minus 1 because we shift off the first row)
	$count = count($csv) - 1;
	
	//Use first row for names
	$labels = array_shift($csv);
	
	foreach ($labels as $label) {
		$keys[] = $label;
	}
	
	// Bring it all together
	for ($j = 0; $j < $count; $j++) {
		$d = array_combine($keys, $csv[$j]);
		$array[$j] = $d;
	}
	return $array;
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