<?php 	

$data = generate_json('islamic-persons.csv');

foreach ($data as $row){
	if (strlen(trim($row['nomisma_id'])) > 0){
		$languages = array();
		$languages['en'] = trim($row['prefLabel_en']);
		$languages['ar'] = trim($row['prefLabel_ar']);
		
		//if the wikipedia column contains the string 'wikipedia'
		if (strpos($row['wikipedia'], 'wikipedia') > 0 ){
			$pieces = explode('/', trim($row['wikipedia']));
				
			//if there is a hash, it is a rdfs:seeAlso page
			if (strpos($pieces[4], '#') > 0){
				$wikipedia = trim($row['wikipedia']);
			} else {
				//get the dbpedia RDF and extract names
				$xmlDoc = new DOMDocument();
				$xmlDoc->load('https://www.wikidata.org/w/api.php?action=wbgetentities&sites=enwiki&titles=' . $pieces[4] . '&props=labels&format=xml');
				$xpath = new DOMXpath($xmlDoc);
				$labels = $xpath->query("descendant::label[@language]");
				$entityId = $xpath->query("descendant::entity")->item(0)->getAttribute('id');
				foreach ($labels as $label){
					$lang = $label->getAttribute('language');
					if (!array_key_exists($lang, $languages)){
						if (strlen($lang) == 2){
							$languages[$lang] = $label->getAttribute('value');
						} elseif (strpos($lang, '-') !== FALSE){
							$lang = substr($lang, 0, 2);
							$languages[$lang] = $label->getAttribute('value');
						} 
					}
				}
				
				//set dbpedia and wikidata
				$dbpedia = 'http://dbpedia.org/resource/' . trim($pieces[4]);
				$wikidata = 'http://www.wikidata.org/entity/' . $entityId;
			}
		}
		
		$type = 'foaf:Person';
		
		$writer = new XMLWriter();
		$writer->openURI('ids/' . trim($row['nomisma_id']) . '.rdf');
		//$writer->openURI('php://output');
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
				
				foreach ($languages as $k=>$v){
					$writer->startElement('skos:prefLabel');
						$writer->writeAttribute('xml:lang', $k);
					$writer->text($v);
					$writer->endElement();
				}
				
				$writer->startElement('skos:definition');
					$writer->writeAttribute('xml:lang', 'en');
					$writer->text($row['definition_en']);
				$writer->endElement();
				
				//matches related to Wikipedia or Wikidata			
				if (isset($wikipedia)){
					$writer->startElement('rdfs:seeAlso');
						$writer->writeAttribute('rdf:resource', $wikipedia);
					$writer->endElement();
				}
				
				if (isset($dbpedia)){
					$writer->startElement('skos:exactMatch');
						$writer->writeAttribute('rdf:resource', $dbpedia);
					$writer->endElement();
				}		
				if (isset($wikidata)){
					$writer->startElement('skos:exactMatch');
						$writer->writeAttribute('rdf:resource', $wikidata);
					$writer->endElement();
					
					//use the wikidata entity ID to extract claims to insert other matching URIs
					$pieces = explode('/', $wikidata);
					$xmlDoc = new DOMDocument();
					$xmlDoc->load('https://www.wikidata.org/w/api.php?action=wbgetentities&sites=enwiki&ids=' . $pieces[4] . '&props=claims&format=xml');
					$xpath = new DOMXpath($xmlDoc);
					$claims = $xpath->query("descendant::mainsnak");
					
					foreach ($claims as $claim){
						$property = $claim->getAttribute('property');
						$datavalues = $claim->getElementsByTagName('datavalue');
						foreach ($datavalues as $dv){
							if ($dv->hasAttribute('value')){
								$value = $dv->getAttribute('value');
							}
						}
						if (isset($value)){
							switch ($property){
								case 'P214':
									if (strlen($row['viaf']) == 0) {
										$writer->startElement('skos:exactMatch');
										$writer->writeAttribute('rdf:resource', 'http://viaf.org/viaf/' . $value);
										$writer->endElement();
									}
									break;
								case 'P213':
									$writer->startElement('skos:exactMatch');
									$writer->writeAttribute('rdf:resource', 'http://isni.org/isni/' . str_replace(' ' , '', $value));
									$writer->endElement();
									break;
								case 'P227':
									$writer->startElement('skos:exactMatch');
									$writer->writeAttribute('rdf:resource', 'http://d-nb.info/gnd/' . $value);
									$writer->endElement();
									break;
								case 'P227':
									$writer->startElement('skos:exactMatch');
									$writer->writeAttribute('rdf:resource', 'http://www.idref.fr/' . $value);
									$writer->endElement();
									break;
								case 'P268':
									$writer->startElement('skos:exactMatch');
									$writer->writeAttribute('rdf:resource', 'http://catalogue.bnf.fr/ark:/12148/cb' . $value);
									$writer->endElement();
									break;
								case 'P646':
									$writer->startElement('skos:exactMatch');
									$writer->writeAttribute('rdf:resource', 'https://www.freebase.com' . $value);
									$writer->endElement();
									break;							
							}
						}
					}
				}
				
				//VIAF
				if (strlen($row['viaf']) > 0){
					$writer->startElement('skos:exactMatch');
						$writer->writeAttribute('rdf:resource', trim($row['viaf']));
					$writer->endElement();
				}
				
				$writer->startElement('dcterms:isPartOf');
					$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/islamic_numismatics');
				$writer->endElement();
				
				$writer->startElement('org:hasMembership');
					$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/' . trim($row['nomisma_id']) . '#authority');
				$writer->endElement();
				
				//create new Membership for second set of dates
				if (strlen($row['fromDate2']) > 0 && strlen($row['toDate2']) > 0){
					$writer->startElement('org:hasMembership');
						$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/' . trim($row['nomisma_id']) . '#authority2');
					$writer->endElement();
				}
			$writer->endElement();
			
			//Membership
			$writer->startElement('org:Membership');
				$writer->writeAttribute('rdf:about', 'http://nomisma.org/id/' .trim($row['nomisma_id']) . '#authority');
				$writer->startElement('org:role');
					$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/authority');
				$writer->endElement();
				
				//dynasty/authority
				if (strlen(trim($row['dynasty'])) > 0){
					$writer->startElement('org:organization');
						$writer->writeAttribute('rdf:resource', trim($row['dynasty']));
					$writer->endElement();
				}
				
				//dates
				if (is_numeric($row['fromDate1'])){
					$writer->startElement('nmo:hasStartDate');
					$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#gYear');
					$writer->text(number_pad(trim($row['fromDate1']), 4));
					$writer->endElement();
				}
				if (is_numeric($row['toDate1'])){
					$writer->startElement('nmo:hasEndDate');
					$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#gYear');
					$writer->text(number_pad(trim($row['toDate1']), 4));
					$writer->endElement();
				}
			$writer->endElement();
			
			//second role
			if (strlen($row['fromDate2']) > 0 && strlen($row['toDate2']) > 0){
				$writer->startElement('org:Membership');
					$writer->writeAttribute('rdf:about', 'http://nomisma.org/id/' .trim($row['nomisma_id']) . '#authority2');
					$writer->startElement('org:role');
						$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/authority');
					$writer->endElement();
					
					//dynasty/authority
					if (strlen(trim($row['dynasty'])) > 0){
						$writer->startElement('org:organization');
							$writer->writeAttribute('rdf:resource', trim($row['dynasty']));
						$writer->endElement();
					}
						
					//dates
					if (is_numeric($row['fromDate2'])){
						$writer->startElement('nmo:hasStartDate');
						$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#gYear');
						$writer->text(number_pad(trim($row['fromDate2']), 4));
						$writer->endElement();
					}
					if (is_numeric($row['toDate2'])){
						$writer->startElement('nmo:hasEndDate');
						$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#gYear');
						$writer->text(number_pad(trim($row['toDate2']), 4));
						$writer->endElement();
					}
				$writer->endElement();
			}
			
		$writer->endElement();
		$writer->endDocument();
		$writer->flush();
		echo "Wrote {$row['nomisma_id']}\n";
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

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
	if ($number > 0){
		$gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
	} elseif ($number < 0) {
		$gYear = '-' . str_pad((int) abs($number),$n,"0",STR_PAD_LEFT);
	}
	return $gYear;
}

?>