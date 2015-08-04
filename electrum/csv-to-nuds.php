<?php
/************************
 AUTHOR: Ethan Gruber
MODIFIED: January, 2013
DESCRIPTION: Convert electrum spreadsheet to NUDS
REQUIRED LIBRARIES: php5, php5-curl, php5-cgi
************************/

//create an array with pre-defined labels and values passed from the Filemaker POST
$labels = array("region","region_uri","mint","mint_uri","material","material_uri","skip1","skip2","denomination","denomination_uri","weight","weight_standard","diameter","obverseType","reverseType","date","dob","fromDate","toDate","ref1","ref1_donum","ref2","ref2_donum","ref3","ref3_donum","ref4","ref4_donum","ref5","ref5_donum","auc1","auc1_donum","auc1_lot","auc2","auc2_donum","auc2_lot","auc3","auc3_donum","auc3_lot","auc4","auc4_donum","auc4_lot","auc5","auc5_donum","auc5_lot","repository","identifier","findspot","hoard","hoard_uri","provenance","cross_reference","notes","rnotes1_skip","obv_image","rev_image","rnotes2_skip","skip3","skip4","skip5");
$files = scandir('csv');

foreach ($files as $file){
	if (strpos($file, '.csv') > 0){
		$filename = 'csv/' . $file;
		process_file($filename, $labels);
	}
}


function process_file($filename, $labels){
	if (($handle = fopen($filename, "r")) !== FALSE) {
		echo "Processing {$filename}\n";
		$count = -1;
		while (($data = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
			//skip first (label) row
			if ($count > 0){
				$row = array();
				foreach ($labels as $key=>$label){
					//escape conflicting XML characters
					$row[$label] = $data[$key];
				}
					
				$recordId = substr(md5(rand()),0,9);
				$xml = generate_nuds($row, $recordId);
				$xmlFile = '/tmp/' . $recordId . '.xml';
					
				//load DOMDocument
				$dom = new DOMDocument('1.0', 'UTF-8');
				if ($dom->loadXML($xml) === FALSE){
					echo "{$recordId} failed to validate.\n";
				} else {
					$dom->preserveWhiteSpace = FALSE;
					$dom->formatOutput = TRUE;
					//echo $dom->saveXML() . "\n";
					$dom->save($xmlFile);
						
					if (($readFile = fopen($xmlFile, 'r')) === FALSE){
						echo "Unable to read {$recordId}.xml\n";
					} else {
					//PUT xml to eXist
						$putToExist=curl_init();
		
						//set curl opts
						curl_setopt($putToExist,CURLOPT_URL,'http://localhost:8080/exist/rest/db/electrum/objects/' . $recordId . '.xml');
						curl_setopt($putToExist,CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=utf-8"));
						curl_setopt($putToExist,CURLOPT_CONNECTTIMEOUT,2);
						curl_setopt($putToExist,CURLOPT_RETURNTRANSFER,1);
						curl_setopt($putToExist,CURLOPT_PUT,1);
						curl_setopt($putToExist,CURLOPT_INFILESIZE,filesize($xmlFile));
						curl_setopt($putToExist,CURLOPT_INFILE,$readFile);
						curl_setopt($putToExist,CURLOPT_USERPWD,"admin:");
						$response = curl_exec($putToExist);
	
						$http_code = curl_getinfo($putToExist,CURLINFO_HTTP_CODE);
	
								//error and success logging
						if (curl_error($putToExist) === FALSE){
							echo "{$recordId} failed to write to eXist.\n";
						}
						else {
							if ($http_code == '201'){
								echo "{$recordId} written.\n";
							}
						}
						//close eXist curl
						curl_close($putToExist);
	
						//close files and delete from /tmp
						fclose($readFile);
						unlink($xmlFile);
					}
				}
			}
			$count++;
		}
	}
}

/****** GENERATE NUDS ******/
function generate_nuds($row, $recordId){
	//array of cleaned labels for title elements
	$title_elements = array();
	
	//develop date
	$startdate_int = trim($row['fromDate']) * 1;
	$enddate_int = trim($row['toDate']) * 1;
	if (trim($row['fromDate']) != '' || trim($row['toDate']) != ''){
		$fromDate_textual = get_date_textual($startdate_int);
		$toDate_textual = get_date_textual($enddate_int);
		$date_textual = $fromDate_textual . (strlen($fromDate_textual) > 0 && strlen($toDate_textual) > 0 ? '-' : '' ) . $toDate_textual;
		$date = get_date($startdate_int, $enddate_int, $date_textual, $fromDate_textual, $toDate_textual);
	}

	//set objectType
	$objtype = 'Coin';
	$objtype_uri = 'http://nomisma.org/id/coin';

	//control
	$xml = '<?xml version="1.0" encoding="UTF-8"?><nuds xmlns="http://nomisma.org/nuds" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:mets="http://www.loc.gov/METS/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" recordType="physical">';
	$xml .= "<control><recordId>{$recordId}</recordId>";
	$xml .= '<publicationStatus>approved</publicationStatus>';
	$xml .= '<maintenanceAgency><agencyName>American Numismatic Society</agencyName></maintenanceAgency>';
	$xml .= '<maintenanceStatus>derived</maintenanceStatus>';
	$xml .= '<maintenanceHistory><maintenanceEvent>';
	$xml .= '<eventType>derived</eventType><eventDateTime standardDateTime="' . date(DATE_W3C) . '">' . date(DATE_W3C) . '</eventDateTime><agentType>machine</agentType><agent>PHP</agent><eventDescription>Derived from CSV</eventDescription>';
	$xml .= '</maintenanceEvent></maintenanceHistory>';
	$xml .= '<rightsStmt><copyrightHolder>American Numismatic Society</copyrightHolder></rightsStmt>';
	$xml .= "</control>";
	$xml .= '<descMeta>';

	/***** TITLE *****/
	$title = $recordId;
	/*$title .= $title_elements['objectType'];
	if (array_key_exists('location', $title_elements)){
		$title .= ', ' . $title_elements['location'];
	}
	if (strlen($date_textual) > 0){
		$title .= ', ';
		$title .= $date_textual;
	}
	$title .= '. ' . $recordId;*/
	
	$xml .= '<title xml:lang="en">' . $title . '</title>';
	
	/************ typeDesc ***************/
	$xml .= '<typeDesc>';

	//fill in other typeDesc metadata
	$xml .= '<objectType xlink:type="simple" xlink:href="' . $objtype_uri . '">' . $objtype . '</objectType>';
	
	//date
	if (strlen($date) > 0){
		$xml .= $date;
	}
	
	//denomination
	if (strlen(trim($row['denomination'])) > 0){
		$xml .= '<denomination xlink:type="simple"' . (strlen($row['denomination_uri']) > 0 ? ' xlink:href="' . trim($row['denomination_uri']) . '"' : '') . '>' . trim($row['denomination']) . '</denomination>';
	}
	
	//material
	if (trim($row['material']) == 'Electrum'){
		$xml .= '<material xlink:type="simple" xlink:href="http://nomisma.org/id/el">Electrum</material>';
	} elseif (trim($row['material']) == 'Gold') {
		$xml .= '<material xlink:type="simple" xlink:href="http://nomisma.org/id/av">Gold</material>';
	}

	/***** GEOGRAPHICAL LOCATIONS *****/
	if (strlen(trim($row['mint'])) > 0 || strlen(trim($row['region'])) > 0 ){		
		$xml .= '<geographic>';
		if (strlen(trim($row['mint'])) > 0){
			$xml .= '<geogname xlink:type="simple" xlink:role="mint"' . (strlen($row['mint_uri']) > 0 ? ' xlink:href="' . trim($row['mint_uri']) . '"' : '') . '>' . trim($row['mint']) . '</geogname>';
		}
		if (strlen(trim($row['region'])) > 0){
			$xml .= '<geogname xlink:type="simple" xlink:role="region"' . (strlen($row['region_uri']) > 0 ? ' xlink:href="' . trim($row['region_uri']) . '"' : '') . '>' . trim($row['region']) . '</geogname>';
		}
		$xml .= '</geographic>';
	}
	
	if (strlen(trim($row['obverseType'])) > 0){
		$xml .= '<obverse>';
		$xml .= '<type><description xml:lang="en">' . trim($row['obverseType']) . '</description></type>';
		$xml .= '</obverse>';
	}
	
	if (strlen(trim($row['reverseType'])) > 0){
		$xml .= '<reverse>';
		$xml .= '<type><description xml:lang="en">' . trim($row['reverseType']) . '</description></type>';
		$xml .= '</reverse>';
	}
	
	//weightStandard
	if (strlen(trim($row['weight_standard'])) > 0){
		$xml .= '<weightStandard>' . trim($row['weight_standard']) . '</weightStandard>';
	}
	
	$xml .= '</typeDesc>';

	/***** PHYSICAL DESCRIPTION *****/
	if (strlen(trim($row['dob'])) > 0 || trim($row['weight']) > 0 || trim($row['diameter']) > 0){
		$xml .= '<physDesc>';
		//dob
		if (strlen(trim($row['dob'])) > 0){
			$xml .= '<dateOnObject><date>' .  $row['dob'] . '</date></dateOnObject>';
		}
		//create measurementsSet, if applicable
		if (trim($row['weight']) > 0 || trim($row['diameter']) > 0){
			$xml .= '<measurementsSet>';
			//weight
			$weight = trim($row['weight']);
			if (is_numeric($weight) && $weight > 0){
				$xml .= '<weight units="g">' . $weight . '</weight>';
			}
			//diameter
			$diameter = trim(str_replace('mm', '', $row['diameter']));
			if (is_numeric($diameter) && $diameter > 0){
				$xml .= '<diameter units="mm">' . $diameter . '</diameter>';
			}
			$xml .= '</measurementsSet>';
		}
		$xml .= '</physDesc>';
	}
	
	/***** FINDSPOT DESCRIPTION *****/
	if (strlen(trim($row['findspot'])) > 0 || strlen(trim($row['hoard'])) > 0 ){		
		if (strlen(trim($row['hoard'])) > 0){
			$id = strtolower(str_replace(' ', '', trim($row['hoard'])));
			$hoardUri = "http://nomisma.org/id/{$id}";
			$xml .= '<findspotDesc xlink:type="simple" xlink:href="' . $hoardUri . '"/>';
		} elseif (strlen(trim($row['findspot'])) > 0) {
			$xml .= '<findspotDesc>';
			$xml .= '<findspot><geogname xlink:type="simple" xlink:role="findspot">' . trim($row['findspot']) . '</geogname></findspot>';
			$xml .= '</findspotDesc>';
		}
	}

	/***** ADMINSTRATIVE DESCRIPTION *****/
	$xml .= '<adminDesc>';
	if (strlen(trim($row['identifier'])) > 0){
		$xml .= '<identifier>' . $row['identifier'] . '</identifier>';
	}
	if (strlen(trim($row['repository'])) > 0){	
		$xml .= '<repository>' . $row['repository'] . '</repository>';
	}
	
	if (strlen(trim($row['auc1'])) > 0 || strlen(trim($row['auc2'])) > 0 || strlen(trim($row['auc3'])) > 0 || strlen(trim($row['auc4'])) > 0 || strlen(trim($row['auc5'])) > 0 || strlen(trim($row['provenance'])) > 0){
		$xml .= '<provenance><chronList>';
		//previous collection
		if (strlen(trim($row['provenance'])) > 0){
			$xml .= '<chronItem><previousColl>' . trim($row['provenance']) . '</previousColl></chronItem>';
		}			
		//auction catalogs
		$xml .= auction_to_nuds($row, 'auc1');
		$xml .= auction_to_nuds($row, 'auc2');
		$xml .= auction_to_nuds($row, 'auc3');
		$xml .= auction_to_nuds($row, 'auc4');
		$xml .= auction_to_nuds($row, 'auc5');	
		$xml .= '</chronList></provenance>';
	}	
	$xml .= '</adminDesc>';

	/***** BIBLIOGRAPHIC DESCRIPTION *****/
	if (strlen(trim($row['ref1'])) > 0 || strlen(trim($row['ref2'])) > 0 || strlen(trim($row['ref3'])) > 0 || strlen(trim($row['ref4'])) > 0 || strlen(trim($row['ref5'])) > 0){
		$xml .= '<refDesc>';
		$xml .= reference_to_nuds($row, 'ref1');
		$xml .= reference_to_nuds($row, 'ref2');
		$xml .= reference_to_nuds($row, 'ref3');
		$xml .= reference_to_nuds($row, 'ref4');
		$xml .= reference_to_nuds($row, 'ref5');
		$xml .= '</refDesc>';
	}

	
	$xml .= '</descMeta>';

	/***** IMAGES *****/
	$xml .= '<digRep><mets:fileSec>';
	$xml .= '<mets:fileGrp USE="obverse">';
	$xml .= '<mets:file USE="reference" MIMETYPE="image/jpeg"><mets:FLocat LOCTYPE="URL" xlink:href="media/reference/' . trim($row['obv_image']) . '.jpg"/></mets:file>';
	$xml .= '<mets:file USE="thumbnail" MIMETYPE="image/jpeg"><mets:FLocat LOCTYPE="URL" xlink:href="media/thumbnail/' . trim($row['obv_image']) . '.jpg"/></mets:file>';
	$xml .= '</mets:fileGrp>';
	$xml .= '<mets:fileGrp USE="reverse">';
	$xml .= '<mets:file USE="reference" MIMETYPE="image/jpeg"><mets:FLocat LOCTYPE="URL" xlink:href="media/reference/' . trim($row['rev_image']) . '.jpg"/></mets:file>';
	$xml .= '<mets:file USE="thumbnail" MIMETYPE="image/jpeg"><mets:FLocat LOCTYPE="URL" xlink:href="media/thumbnail/' . trim($row['rev_image']) . '.jpg"/></mets:file>';
	$xml .= '</mets:fileGrp>';
	$xml .= '</mets:fileSec></digRep>';
	
	//close nuds
	$xml .= '</nuds>';

	return $xml;
}

function auction_to_nuds($row, $key){
	$xml = '';
	if (strlen(trim($row[$key])) > 0){
		$donum = strlen(trim($row[$key . '_donum']) > 0) ? ' xlink:type="simple" xlink:href="http://numismatics.org/library/' . trim($row[$key . '_donum']) . '"' : '';
		
		$xml .= '<chronItem><auction>';
		$xml .= '<saleCatalog' . $donum . '>' . trim($row[$key]) . '</saleCatalog>';
		if (strlen(trim($row[$key . '_lot'])) > 0){
			$xml .= '<saleItem>' . trim($row[$key . '_lot']) . '</saleItem>';
		}
		$xml .= '</auction></chronItem>';
	}
	return $xml;
}

//these are actually citations, not references in NUDS
function reference_to_nuds($row, $key){
	$xml = '';
	if (strlen(trim($row[$key])) > 0){
		$donum = strlen(trim($row[$key . '_donum']) > 0) ? ' xlink:type="simple" xlink:href="http://numismatics.org/library/' . trim($row[$key . '_donum']) . '"' : '';
		$xml .= '<citation' . $donum . '>' . trim($row[$key]) . '</citation>';
	}
	return $xml;
}

function get_date_textual($year){
	$textual_date = '';
	//display start date
	if ($year != 0){
		if($year < 0){
			$textual_date .= abs($year) . ' BC';
		} else {
			if ($year <= 600){
				$textual_date .= 'AD ';
			}
			$textual_date .= $year;
		}
	}
	return $textual_date;
}

function get_date($startdate, $enddate, $date_textual, $fromDate_textual, $toDate_textual){

	//validate dates
	if ($startdate != 0 && is_int($startdate) && $startdate < 3000 ){
		$start_gYear = number_pad($startdate, 4);
	}
	if ($enddate != 0 && is_int($enddate) && $enddate < 3000 ){
		$end_gYear = number_pad($enddate, 4);
	}

	if ($startdate == 0 && $enddate != 0){
		$node = '<date' . (strlen($end_gYear) > 0 ? ' standardDate="' . $end_gYear . '"' : '') . '>' . $date_textual . '</date>';
	} elseif ($startdate != 0 && $enddate == 0) {
		$node = '<date' . (strlen($start_gYear) > 0 ? ' standardDate="' . $start_gYear . '"' : '') . '>' . $date_textual . '</date>';
	} elseif ($startdate == $enddate){
		$node = '<date' . (strlen($end_gYear) > 0 ? ' standardDate="' . $end_gYear . '"' : '') . '>' . $date_textual . '</date>';
	}
	elseif ($startdate != 0 && $enddate != 0){
		$node = '<dateRange><fromDate' . (strlen($start_gYear) > 0 ? ' standardDate="' . $start_gYear . '"' : '') . '>' . $fromDate_textual . '</fromDate><toDate' . (strlen($start_gYear) > 0 ? ' standardDate="' . $end_gYear . '"' : '') . '>' . $toDate_textual . '</toDate></dateRange>';
	}
	return $node;
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
	if ($number > 0){
		$gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
	} elseif ($number < 0) {
		$num = abs($number) - 1;
		$gYear = '-' . str_pad((int) $num,$n,"0",STR_PAD_LEFT);
	}
	return $gYear;
}

?>
