<?php 

$data = generate_json('collection-final.csv');
$nomismaUris = array();

foreach ($data as $row){
	generate_nuds($row);
	
	//format XML output
	
	/*$dom = new DOMDocument('1.0', 'UTF-8');
	$dom->preserveWhiteSpace = FALSE;
	$dom->formatOutput = TRUE;
	$dom->loadXML($xml);
	echo $dom->saveXML();*/
}


//functions
function generate_nuds($row){
	$recordId = $row['recordId'];
	
	$doc = new XMLWriter();
	$doc->openUri('php://output');
	$doc->setIndent(true);
	
	$doc->startDocument('1.0','UTF-8');
	
	$doc->startElement('nuds');
		$doc->writeAttribute('xmlns', 'http://nomisma.org/nuds');
		$doc->writeAttribute('xs', 'http://www.w3.org/2001/XMLSchema');
		$doc->writeAttribute('xlink', 'http://www.w3.org/1999/xlink');
		$doc->writeAttribute('recordType', 'physical');
		
		//control
		$doc->startElement('control');
			$doc->writeElement('recordId', $recordId);
			$doc->startElement('maintenanceAgency');
				$doc->writeElement('agencyName', 'American Numismatic Society');
			$doc->endElement();
			$doc->writeElement('maintenanceStatus', 'derived');
			
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
					$doc->writeElement('eventDescription', 'Generated from CSV, after iterative cleanup process (including Open Refine)');
				$doc->endElement();
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
		
		/***** TYPEDESC *****/
			$doc->startElement('typeDesc');
			
			//objectType
			if (strlen($row['objectType']) > 0){
				$vals = explode('|', $row['objectType']);
				foreach ($vals as $val){
					if (substr($val, -1) == '?'){
						$uri = substr($val, 0, -1);
						$uncertainty = true;
						$content = processUri($uri);
					} else {
						$uri = $val;
						$uncertainty = false;
						$content = processUri($uri);
					}
					
					$doc->startElement($content['element']);
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', $uri);
						if($uncertainty == true){
							$doc->writeAttribute('certainty', 'uncertain');
						}
						$doc->text($content['label']);
					$doc->endElement();
				}
			}
			
			if (strlen($row['material']) > 0){
				$vals = explode('|', $row['material']);
				foreach ($vals as $val){
					if (substr($val, -1) == '?'){
						$uri = substr($val, 0, -1);
						$uncertainty = true;
						$content = processUri($uri);
					} else {
						$uri = $val;
						$uncertainty = false;
						$content = processUri($uri);
					}
						
					$doc->startElement($content['element']);
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', $uri);
					if($uncertainty == true){
						$doc->writeAttribute('certainty', 'uncertain');
					}
					$doc->text($content['label']);
					$doc->endElement();
				}
			}
			
			if (strlen($row['manufacture']) > 0){
				$vals = explode('|', $row['manufacture']);
				foreach ($vals as $val){
					if (substr($val, -1) == '?'){
						$uri = substr($val, 0, -1);
						$uncertainty = true;
						$content = processUri($uri);
					} else {
						$uri = $val;
						$uncertainty = false;
						$content = processUri($uri);
					}
			
					$doc->startElement($content['element']);
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', $uri);
					if($uncertainty == true){
						$doc->writeAttribute('certainty', 'uncertain');
					}
					$doc->text($content['label']);
					$doc->endElement();
				}
			}
			
			if (is_numeric($row['fromDate']) && is_numeric($row['toDate'])){
				if ($row['fromDate'] == $row['toDate']){
					$doc->startElement('date');
						$doc->writeAttribute('standardDate', number_pad($row['fromDate'], 4));
						$doc->text($row['fromDate']);
					$doc->endElement();
				} else {
					$doc->startElement('dateRange');
						$doc->startElement('fromDate');
							$doc->writeAttribute('standardDate', number_pad($row['fromDate'], 4));
							$doc->text($row['fromDate']);
						$doc->endElement();
						$doc->startElement('toDate');
							$doc->writeAttribute('standardDate', number_pad($row['toDate'], 4));
							$doc->text($row['toDate']);
						$doc->endElement();
					$doc->endElement();
				}
			}
			
			//date on object
			if (strlen($row['dob_ah']) > 0){
				$doc->startElement('dateOnObject');
					$doc->writeAttribute('calendar', 'ah');
					$doc->text($row['dob_ah']);
				$doc->endElement();
			}
			if (strlen($row['dob']) > 0){
				$doc->writeElement('dateOnObject', $row['dob']);
			}
			//authority
			if (strlen($row['authorityPerson']) > 0 || strlen($row['org']) > 0 || strlen($row['otherPerson']) > 0){
				$doc->startElement('authority');
				if (strlen($row['org']) > 0 && (strlen($row['authorityPerson']) == 0 && strlen($row['otherPerson']) == 0)){
					$vals = explode('|', $row['org']);
					foreach ($vals as $val){
						if (substr($val, -1) == '?'){
							$uri = substr($val, 0, -1);
							$uncertainty = true;
							$content = processUri($uri);
						} else {
							$uri = $val;
							$uncertainty = false;
							$content = processUri($uri);
						}
							
						$doc->startElement($content['element']);
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', $uri);
						if($uncertainty == true){
							$doc->writeAttribute('certainty', 'uncertain');
						}
						$doc->text($content['label']);
						$doc->endElement();
					}
				}
				if (strlen($row['authorityPerson']) > 0){
					$vals = explode('|', $row['authorityPerson']);
					foreach ($vals as $val){
						if (substr($val, -1) == '?'){
							$uri = substr($val, 0, -1);
							$uncertainty = true;
							$content = processUri($uri);
						} else {
							$uri = $val;
							$uncertainty = false;
							$content = processUri($uri);
						}
							
						$doc->startElement($content['element']);
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', $uri);
						if($uncertainty == true){
							$doc->writeAttribute('certainty', 'uncertain');
						}
						$doc->text($content['label']);
						$doc->endElement();
					}
				}
				$doc->endElement();
			}
			
			//geography
			//mint
			if (strlen($row['mint']) > 0){
				$doc->startElement('geographic');
				$vals = explode('|', $row['mint']);
				foreach ($vals as $val){
					if (substr($val, -1) == '?'){
						$uri = substr($val, 0, -1);
						$uncertainty = true;
						$content = processUri($uri);
					} else {
						$uri = $val;
						$uncertainty = false;
						$content = processUri($uri);
					}
						
					$doc->startElement($content['element']);
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', $uri);
					if($uncertainty == true){
						$doc->writeAttribute('certainty', 'uncertain');
					}
					$doc->text($content['label']);
					$doc->endElement();
				}
				$doc->endElement();
			}
			
			//end typeDesc
			$doc->endElement();
			
			/***** PHYSDESC *****/
			$doc->startElement('physDesc');
			
			//measurementsSet
			if (strlen($row['weight']) > 0 || strlen($row['diameter']) > 0 || (strlen($row['height']) > 0 && strlen($row['width']) > 0)){
				if (strlen($row['weight']) > 0 && is_numeric($row['weight'])){
					$doc->startElement('weight');
						$doc->writeAttribute('units', 'g');
						$doc->text($row['weight']);
					$doc->endElement();
				}
				if (strlen($row['diameter']) > 0 && is_numeric($row['diameter'])){
					$doc->startElement('diameter');
						$doc->writeAttribute('units', 'mm');
						$doc->text($row['diameter']);
					$doc->endElement();
				}
				if (strlen($row['height']) > 0 && is_numeric($row['height']) && strlen($row['width']) > 0 && is_numeric($row['width'])){
					$doc->startElement('height');
						$doc->writeAttribute('units', 'mm');
						$doc->text($row['height']);
					$doc->endElement();
					$doc->startElement('width');
						$doc->writeAttribute('units', 'mm');
						$doc->text($row['width']);
					$doc->endElement();
				}
			}
			
			if (strlen($row['authenticity']) > 0){
				$doc->writeElement('authenticity', $row['authenticity']);
			}
			
			
			//end physDesc
			$doc->endElement();
			
			/***** ADMINDESC *****/
			$doc->startElement('adminDesc');
				$doc->startElement('identifier');
					$doc->writeAttribute('localType', 'catnum');
					$doc->text($recordId);
				$doc->endElement();
				if (strlen($row['regno']) > 0){
					$doc->startElement('identifier');
						$doc->writeAttribute('localType', 'regno');
						$doc->text($row['regno']);
					$doc->endElement();
				}
				if (strlen($row['1887 catalog number']) > 0){
					$doc->startElement('identifier');
						$doc->writeAttribute('localType', '1887catnum');
						$doc->text($row['1887 catalog number']);
					$doc->endElement();
				}
				$doc->startElement('collection');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/egyptian_national_library');
					$doc->text('Egyptian National Library');
				$doc->endElement();
			
			//end adminDesc
			$doc->endElement();
			
			//note
			if (strlen($row['note']) > 0){
				$doc->startElement('noteSet');
					$doc->writeElement('note', $row['note']);
				$doc->endElement();
			}
			
		//end descMeta
		$doc->endElement();		
	$doc->endElement();
	$doc->endDocument();
}

function processUri($uri){
	GLOBAL $nomismaUris;
	$content = array();
	$uri = trim($uri);
	$type = '';
	$label = '';
	$node = '';
	
	//if the key exists, then formulate the XML response
	if (array_key_exists($uri, $nomismaUris)){
		$type = $nomismaUris[$uri]['type'];
		$label = $nomismaUris[$uri]['label'];
	} else {
		//if the key does not exist, look the URI up in Nomisma
		$pieces = explode('/', $uri);
		$id = $pieces[4];
		if (strlen($id) > 0){
		
			$xmlDoc = new DOMDocument();
			$xmlDoc->load('http://nomisma.org/id/' . $id . '.rdf');
			$xpath = new DOMXpath($xmlDoc);
			$xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
			$xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
			$type = $xpath->query("/rdf:RDF/*")->item(0)->nodeName;
			$label = $xpath->query("descendant::skos:prefLabel[@xml:lang='en']")->item(0)->nodeValue;
			$nomismaUris[$uri] = array('label'=>$label,'type'=>$type);
		}
	}
	switch($type){
		case 'nmo:Mint':
			$content['element'] = 'geogname';
			$content['label'] = $label;
			break;
		case 'nmo:Material':
			$content['element'] = 'material';
			$content['label'] = $label;
			break;
		case 'nmo:Manufacture':
			$content['element'] = 'manufacture';
			$content['label'] = $label;
			break;
		case 'nmo:ObjectType':
			$content['element'] = 'objectType';
			$content['label'] = $label;
			break;
		case 'rdac:Family':
			$content['element'] = 'famname';
			$content['label'] = $label;
			break;
		case 'foaf:Organization':
		case 'foaf:Group':
			$content['element'] = 'corpname';
			$content['label'] = $label;
			break;
		case 'foaf:Person':
			$content['element'] = 'persname';
			$content['label'] = $label;
			break;
		case 'crm:E4_Period':
			$content['element'] = 'periodname';
			$content['label'] = $label;
			break;
	}
	return $content;
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
	if ($number > 0){
		$gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
	} elseif ($number < 0) {
		$gYear = '-' . str_pad((int) abs($number),$n,"0",STR_PAD_LEFT);
	}
	return $gYear;
}

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