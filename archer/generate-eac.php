<?php 

$all = generate_json('all-names.csv');
$creators = generate_json('distinct-creators.csv');

foreach ($creators as $row){
	$xml = generate_eac($row);
}

function generate_eac($row){
	//set EAC-CPF recordId
	$recordId = $row['id'];
	echo "Processing {$recordId}\n";
	
	$writer = new XMLWriter();
	//$writer->openURI('php://output');
	$writer->openURI("eac/{$recordId}.xml");
	$writer->startDocument('1.0','UTF-8');
	$writer->setIndent(4);
	$writer->startElement('eac-cpf');
		$writer->writeAttribute('xmlns', 'urn:isbn:1-931666-33-4');
		$writer->writeAttribute('xmlns:xlink', "http://www.w3.org/1999/xlink");
		//begin control
		$writer->startElement('control');
			$writer->writeElement('recordId', $recordId);
			if (strlen($row['viaf']) > 0){
				$writer->startElement('otherRecordId');
					$writer->writeAttribute('localType', 'owl:sameAs');
					$writer->text($row['viaf']);
				$writer->endElement();
				//get owl:sameAs links
				$doc = new DOMDocument();
				$doc->load($row['viaf'] . '/rdf');
				$xpath = new DOMXPath($doc);
				$xpath->registerNamespace('rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
				$xpath->registerNamespace('owl', "http://www.w3.org/2002/07/owl#");
				$links = $xpath->query('descendant::rdf:Description[@rdf:about="' . $row['viaf'] . '"]/owl:sameAs');
				foreach ($links as $link){
					$writer->startElement('otherRecordId');
						$writer->writeAttribute('localType', 'owl:sameAs');
						$writer->text($link->getAttribute('rdf:resource'));
					$writer->endElement();
				}
			}
			$writer->writeElement('maintenanceStatus', 'new');
			$writer->writeElement('publicationStatus', 'approved');
			$writer->startElement('maintenanceAgency');
				$writer->writeElement('agencyName', 'American Numismatic Society');
				$writer->writeElement('agencyCode', 'US-nnan');
			$writer->endElement();
			$writer->startElement('maintenanceHistory');
				$writer->startElement('maintenanceEvent');
					$writer->writeElement('eventType', 'derived');
					$writer->startElement('eventDateTime');
						$writer->writeAttribute('standardDateTime', date(DATE_W3C));
						$writer->text(date(DATE_RFC2822));
					$writer->endElement();
					$writer->writeElement('agentType', 'human');
					$writer->writeElement('agent', 'Ethan Gruber');
					$writer->writeElement('eventDescription', 'Generated EAC-CPF from EAD finding aids with an interation of PHP scripts.');
				$writer->endElement();
			$writer->endElement();
			$writer->startElement('conventionDeclaration');
				$writer->writeElement('abbreviation', 'ANS');
				$writer->writeElement('citation', 'American Numismatic Society');
			$writer->endElement();
			//rel: relationship ontology
			$writer->startElement('localTypeDeclaration');
				$writer->writeElement('abbreviation', 'rel');
				$writer->startElement('citation');					
					$writer->writeAttribute('xlink:type', "simple");					
					$writer->writeAttribute('xlink:role', "semantic");
					$writer->writeAttribute('xlink:href', "http://purl.org/vocab/relationship/");
					$writer->text('http://purl.org/vocab/relationship/');
				$writer->endElement();
			$writer->endElement();
			//xeac:life
			$writer->startElement('localTypeDeclaration');
				$writer->writeElement('abbreviation', 'xeac:life');
				$writer->startElement('citation');
					$writer->writeAttribute('xlink:type', "simple");
					$writer->writeAttribute('xlink:role', "semantic");
					$writer->writeAttribute('xlink:href', "https://github.com/ewg118/xEAC");					
					$writer->text('xEAC System Local Types');
				$writer->endElement();
				$writer->startElement('descriptiveNote');
					$writer->writeElement('p', 'xEAC system local type assigned to eac:existDates for expressing the birth and death dates of an entity. Contrast with xeac:floruit.');
				$writer->endElement();
			$writer->endElement();
			//owl:sameAs
			$writer->startElement('localTypeDeclaration');
				$writer->writeElement('abbreviation', 'owl:sameAs');
					$writer->startElement('citation');
					$writer->writeAttribute('xlink:type', "simple");
					$writer->writeAttribute('xlink:role', "semantic");
					$writer->writeAttribute('xlink:href', "http://www.w3.org/2002/07/owl#sameAs");
				$writer->text('http://www.w3.org/2002/07/owl#sameAs');
				$writer->endElement();
			$writer->endElement();
		//end control
		$writer->endElement();
		//start cpfDescription
		$writer->startElement('cpfDescription');
			//start identity
			$writer->startElement('identity');
				$writer->writeElement('entityType', ($row['entityType'] == 'personal' ? 'person' : 'corporateBody'));
				$writer->startElement('nameEntry');
					$writer->writeElement('part', $row['preferredForm']);
					$writer->writeElement('preferredForm', 'ANS');
				$writer->endElement();
			$writer->endElement();
			//end identity
			//start description
			$writer->startElement('description');
			//existDates
			if (strlen($row['fromDate']) > 0 || strlen($row['toDate']) > 0 || strlen($row['fromNotBefore']) > 0 || strlen($row['fromNotAfter']) > 0){
				$writer->startElement('existDates');
					$writer->writeAttribute('localType', 'xeac:life');
					$writer->startElement('dateRange');
						$writer->startElement('fromDate');						
							if (is_numeric($row['fromNotBefore']) && is_numeric($row['fromNotAfter'])){
								$writer->writeAttribute('notBefore', $row['fromNotBefore']);
								$writer->writeAttribute('notAfter', $row['fromNotAfter']);
								$writer->text($row['fromNotBefore'] . '/' . $row['fromNotAfter']);
							}
							if (is_numeric($row['fromDate'])){
								$writer->writeAttribute('standardDate', $row['fromDate']);
								$writer->text($row['fromDate']);
							} else {
								$writer->text('Uncertain');
							}
						$writer->endElement();
						$writer->startElement('toDate');
							if (is_numeric($row['toDate'])){							
								$writer->writeAttribute('standardDate', $row['toDate']);
								$writer->text($row['toDate']);
							} else {
								$writer->text('Uncertain');
							}
						$writer->endElement();
					$writer->endElement();
				$writer->endElement();
			}
			//end existDates
			//read EAD file, extract biogHist, if applicable			
			$pieces = explode('|', $row['guides']);
			foreach ($pieces as $eadid){
				$doc = new DOMDocument();
				$doc->load('guides/' . $eadid . '.xml');
				$xpath = new DOMXPath($doc);
				$xpath->registerNamespace('ead', "urn:isbn:1-931666-22-9");
				$ps = $xpath->query('descendant::ead:bioghist/ead:p');
				if ($ps->length > 0){
					$writer->startElement('biogHist');
					foreach ($ps as $p){
						$writer->writeElement('p', preg_replace('!\s+!', ' ', trim($p->nodeValue)));
					}
					$writer->endElement();
				}
			}
			//end biogHist
			//end description
			$writer->endElement();
		//end cpfDescription
		$writer->endElement();
	//end eac-cpf
	$writer->endElement();
	$writer->flush();
}

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