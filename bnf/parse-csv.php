<?php 

$data = generate_json('pella.csv');
$records = array();

foreach ($data as $row){
	$record = array();
	
	if (strlen($row['Lien ark']) > 0){
		$record['uri'] = $row['Lien ark'];
		$record['cointype'] = $row['018 $u'];
		
		echo "Processing {$record['uri']}\n";
		$id = str_replace('http://gallica.bnf.fr/', '', $record['uri']);
		$recordURL = 'http://oai.bnf.fr/oai2/OAIHandler?verb=GetRecord&metadataPrefix=oai_dc&identifier=oai:bnf.fr:gallica/' . $id;
		
		//IIIF services
		$record['obvService'] = 'http://gallica.bnf.fr/iiif/' . $id . '/f1';
		$record['revService'] = 'http://gallica.bnf.fr/iiif/' . $id . '/f2';
		
		//get measurement data from OAI-PMH
		$doc = new DOMDocument();
		if ($doc->load($recordURL) === FALSE){
			return "FAIL";
		} else {
			$xpath = new DOMXpath($doc);
			$xpath->registerNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
			$xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
			
			$record['title'] = $doc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'title')->item(0)->nodeValue;
			$record['identifier'] = trim(str_replace('Bibliothèque nationale de France, département Monnaies, médailles et antiques,', '',
					$doc->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'source')->item(0)->nodeValue));
			
			//get measurements
			$formats= $xpath->query("descendant::dc:format");
			foreach ($formats as $format){
				preg_match('/(\d+)\smm/', $format->nodeValue, $matches);
				if (isset($matches[1])){
					$record['diameter'] = $matches[1];
				}
				unset($matches);
				preg_match('/(\d+,\d+)\sg/', $format->nodeValue, $matches);
				if (isset($matches[1])){
					$weight = str_replace(',', '.', $matches[1]);
					if (is_numeric($weight)){
						$record['weight'] = $weight;
					}
				}
				unset($matches);
			}
		}
		
		//var_dump($record);
		$records[] = $record;
	}	
}

generate_rdf($records);


//generate Nomisma-compliant RDF
function generate_rdf($records){
	//start RDF/XML file
	//use XML writer to generate RDF
	$writer = new XMLWriter();
	$writer->openURI("bnf.rdf");
	//$writer->openURI('php://output');
	$writer->startDocument('1.0','UTF-8');
	$writer->setIndent(true);
	//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
	$writer->setIndentString("    ");
	
	$writer->startElement('rdf:RDF');
	$writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema#');
	$writer->writeAttribute('xmlns:nm', "http://nomisma.org/id/");
	$writer->writeAttribute('xmlns:nmo', "http://nomisma.org/ontology#");
	$writer->writeAttribute('xmlns:dcterms', "http://purl.org/dc/terms/");
	$writer->writeAttribute('xmlns:foaf', "http://xmlns.com/foaf/0.1/");
	$writer->writeAttribute('xmlns:geo', "http://www.w3.org/2003/01/geo/wgs84_pos#");
	$writer->writeAttribute('xmlns:rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
	$writer->writeAttribute('xmlns:void', "http://rdfs.org/ns/void#");
	$writer->writeAttribute('xmlns:edm', "http://www.europeana.eu/schemas/edm/");
	$writer->writeAttribute('xmlns:svcs', "http://rdfs.org/sioc/services#");
	$writer->writeAttribute('xmlns:doap', "http://usefulinc.com/ns/doap#");
	
	foreach ($records as $record){
		if (isset($record['cointype'])){
			$writer->startElement('nmo:NumismaticObject');
			$writer->writeAttribute('rdf:about', $record['uri']);
			$writer->startElement('dcterms:title');
				$writer->writeAttribute('xml:lang', 'en');
				$writer->text($record['title']);
			$writer->endElement();
			$writer->writeElement('dcterms:identifier', $record['identifier']);
			$writer->startElement('nmo:hasCollection');
				$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/bnf');
			$writer->endElement();
			$writer->startElement('nmo:hasTypeSeriesItem');
				$writer->writeAttribute('rdf:resource', $record['cointype']);
			$writer->endElement();
			
			//conditional measurement data
			if (isset($record['weight'])){
				$writer->startElement('nmo:hasWeight');
					$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
					$writer->text($record['weight']);
				$writer->endElement();
			}
			if (isset($record['diameter'])){
				$writer->startElement('nmo:hasDiameter');
					$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
					$writer->text($record['diameter']);
				$writer->endElement();
			}
			
			//obverse
			$writer->startElement('nmo:hasObverse');
				$writer->startElement('rdf:Description');
					$writer->startElement('foaf:thumbnail');
						$writer->writeAttribute('rdf:resource', "{$record['obvService']}/full/,120/0/native.jpg");
					$writer->endElement();
					$writer->startElement('foaf:depiction');
						$writer->writeAttribute('rdf:resource', "{$record['obvService']}/full/600,/0/native.jpg");
					$writer->endElement();
				$writer->endElement();
			$writer->endElement();
			
			//reverse
			$writer->startElement('nmo:hasReverse');
				$writer->startElement('rdf:Description');
					$writer->startElement('foaf:thumbnail');
						$writer->writeAttribute('rdf:resource', "{$record['revService']}/full/,120/0/native.jpg");
					$writer->endElement();
					$writer->startElement('foaf:depiction');
						$writer->writeAttribute('rdf:resource', "{$record['revService']}/full/600,/0/native.jpg");
					$writer->endElement();
				$writer->endElement();
			$writer->endElement();
			
			//void:inDataset
			$writer->startElement('void:inDataset');
				$writer->writeAttribute('rdf:resource', 'http://bnf.fr/');
			$writer->endElement();
			
			//end nmo:NumismaticObject
			$writer->endElement();
			
			//create WebResources for IIIF: obverse and reverse images specifically should reference the info.json for the image, not the entire manifest
			$writer->startElement('edm:WebResource');
				$writer->writeAttribute('rdf:about', "{$record['obvService']}/full/600,/0/native.jpg");
				$writer->startElement('svcs:has_service');
					$writer->writeAttribute('rdf:resource', $record['obvService']);
				$writer->endElement();
				$writer->startElement('dcterms:isReferencedBy');
					$writer->writeAttribute('rdf:resource', "{$record['obvService']}/info.json");
				$writer->endElement();
			$writer->endElement();
			
			$writer->startElement('svcs:Service');
				$writer->writeAttribute('rdf:about', $record['obvService']);
				$writer->startElement('dcterms:conformsTo');
					$writer->writeAttribute('rdf:resource', 'http://iiif.io/api/image');
				$writer->EndElement();
				$writer->startElement('doap:implements');
					$writer->writeAttribute('rdf:resource', 'http://iiif.io/api/image/2/level1.json');
				$writer->endElement();
			$writer->endElement();
			
			$writer->startElement('edm:WebResource');
				$writer->writeAttribute('rdf:about', "{$record['revService']}/full/600,/0/native.jpg");
				$writer->startElement('svcs:has_service');
					$writer->writeAttribute('rdf:resource', $record['revService']);
				$writer->endElement();
				$writer->startElement('dcterms:isReferencedBy');
					$writer->writeAttribute('rdf:resource', "{$record['revService']}/info.json");
				$writer->endElement();
			$writer->endElement();
			
			$writer->startElement('svcs:Service');
				$writer->writeAttribute('rdf:about', $record['revService']);
				$writer->startElement('dcterms:conformsTo');
					$writer->writeAttribute('rdf:resource', 'http://iiif.io/api/image');
				$writer->EndElement();
				$writer->startElement('doap:implements');
					$writer->writeAttribute('rdf:resource', 'http://iiif.io/api/image/2/level1.json');
				$writer->endElement();
			$writer->endElement();
		}
	}
	
	//end RDF file
	$writer->endElement();
	$writer->flush();
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