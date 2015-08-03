<?php 	

$data = generate_json('non-islamic-dynasties.csv');

foreach ($data as $row){
	if (strlen(trim($row['nomisma_id'])) > 0){
		if ($row['class'] == 'org'){
			$type = 'foaf:Organization';
		} elseif ($row['class'] == 'dynasty'){
			$type = 'rdac:Family';
		} elseif ($row['class'] == 'ethnic'){
			$type = 'nmo:Ethnic';
		} elseif ($row['class'] == 'period'){
			$type = 'crm:E4_Period';
		}
		
		$writer = new XMLWriter();
		$writer->openURI('ids/' . trim($row['nomisma_id']) . '.rdf');
		$writer->setIndent(true);
		$writer->startDocument('1.0','UTF-8');
		$writer->startElementNS('rdf', 'RDF', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
			$writer->writeAttribute('xmlns:nm', 'http://nomisma.org/id/');
			$writer->writeAttribute('xmlns:skos', 'http://www.w3.org/2004/02/skos/core#');
			$writer->writeAttribute('xmlns:geo', 'http://www.w3.org/2003/01/geo/wgs84_pos#');
			$writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema#');
			$writer->writeAttribute('xmlns:nmo', 'http://nomisma.org/ontology#');
			$writer->writeAttribute('xmlns:org', 'http://www.w3.org/ns/org#');
			$writer->writeAttribute('xmlns:dcterms', 'http://purl.org/dc/terms/');
			$writer->writeAttribute('xmlns:un', 'http://www.owl-ontologies.com/Ontology1181490123.owl#');
			$writer->writeAttribute('xmlns:foaf', 'http://xmlns.com/foaf/0.1/');
			$writer->writeAttribute('xmlns:rdfs', 'http://www.w3.org/2000/01/rdf-schema#');				
			$writer->writeAttribute('xmlns:rdac', 'http://www.rdaregistry.info/Elements/c/');
			$writer->writeAttribute('xmlns:crm', 'http://erlangen-crm.org/current/');
			$writer->startElement($type);
				$writer->writeAttribute('rdf:about', 'http://nomisma.org/id/' .trim($row['nomisma_id']));
				$writer->startElement('rdf:type');
						$writer->writeAttribute('rdf:resource', 'http://www.w3.org/2004/02/skos/core#Concept');
				$writer->endElement();
				
				$writer->startElement('skos:prefLabel');
					$writer->writeAttribute('xml:lang', 'en');
					$writer->text($row['en']);
				$writer->endElement();
				$writer->startElement('skos:prefLabel');
					$writer->writeAttribute('xml:lang', 'ar');
					$writer->text($row['ar']);
				$writer->endElement();
				if (strlen($row['en_altLabel1']) > 0){
					$writer->startElement('skos:altLabel');
						$writer->writeAttribute('xml:lang', 'en');
						$writer->text($row['en_altLabel1']);
					$writer->endElement();
				}
				if (strlen($row['en_altLabel2']) > 0){
					$writer->startElement('skos:altLabel');
						$writer->writeAttribute('xml:lang', 'en');
						$writer->text($row['en_altLabel2']);
					$writer->endElement();
				}
				$writer->startElement('skos:definition');
					$writer->writeAttribute('xml:lang', 'en');
					$writer->text($row['definition']);
				$writer->endElement();
				//if the wikipedia column contains the string 'wikipedia'
				if (strpos($row['wikipedia'], 'wikipedia') > 0 ){
					$pieces = explode('/', trim($row['wikipedia']));
					
					//if there is a hash, it is a rdfs:seeAlso page
					if (strpos($pieces[4], '#') > 0){
						$writer->startElement('rdfs:seeAlso');
						$writer->writeAttribute('rdf:resource', trim($row['wikipedia']));
						$writer->endElement();
					} else {
						$writer->startElement('skos:exactMatch');
							$writer->writeAttribute('rdf:resource', 'http://dbpedia.org/resource/' . $pieces[4]);
						$writer->endElement();
					}					
				}
				
				//hander broader dynasties
				if (strlen($row['broader']) > 0){
					$writer->startElement('skos:broader');
						$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/' . trim($row['broader']));
					$writer->endElement();
				}
				
				//optional start and end dates
				if (is_numeric($row['startDate'])){
					$writer->startElement('nmo:hasStartDate');
						$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#gYear');
						$writer->text($row['startDate']);
					$writer->endElement();
				}
				if (is_numeric($row['endDate'])){
					$writer->startElement('nmo:hasEndDate');
						$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#gYear');
						$writer->text($row['endDate']);
					$writer->endElement();
				}
				$writer->startElement('dcterms:isPartOf');
					$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/islamic_numismatics');
				$writer->endElement();
			$writer->endElement();
		$writer->endElement();
		$writer->endDocument();
		$writer->flush();
	}	
}

//create list of ids
/*$list = '';
foreach ($data as $row){
	if (strlen(trim($row['nomisma_id'])) > 0){
		$list .= "{$row['nomisma_id']}\n";
	}
}
file_put_contents('list.txt', $list);*/
	

//functions
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