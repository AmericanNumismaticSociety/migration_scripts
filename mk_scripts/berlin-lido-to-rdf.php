<?php 

//libxml timeout
$options = ['http' => ['method' => 'GET','timeout' => '10']];
$context = stream_context_create($options);
libxml_set_streams_context($context);

$collections = array();

$collections[] = array('name'=>'muenster','collection'=>'http://nomisma.org/id/archaeological_museum_wwu', 
		'homepage'=>'http://archaeologie.uni-muenster.de/ikmk-ms/',
		'title'=> array('en'=>'Archäologisches Museum der Westfälischen Wilhelms-Universität','de'=>'Archäologisches Museum der Westfälischen Wilhelms-Universität'),
		'description'=>array('en'=>'The coin collection of the Archaelogical Museum of Münster University consists of more than 5,500 objects covering all historical periods in antiquity: Greek coins (of the archaic, classical and hellenistic periods), coins of the Roman Republic and empire, Civic and provincial coins of the Imperial period, and Byzantine ones.',
				'de'=>'Die Münzsammlung des Archäologischen Museums der Universität Münster umfasst mehr als 5.500 Objekte, die alle Epochen der antiken Münzgeschichte abdecken: Griechische Münzen archaischer, klassischer und hellenistischer Zeit, römische Münzen der Republik und Kaiserzeit, kaiserzeitliche Städte- und Provinzialprägungen sowie einige wenige byzantinische Münzen.'),
		'license'=>'http://creativecommons.org/licenses/by-nc-sa/3.0/',
		'uri_space'=>'http://archaeologie.uni-muenster.de/ikmk-ms/object.php?id=',
		'sets'=>['http://archaeologie.uni-muenster.de/mk_edit/coin_export/3/content.txt',
		'http://archaeologie.uni-muenster.de/mk_edit/coin_export/1/content.txt',
		'http://archaeologie.uni-muenster.de/mk_edit/coin_export/2/content.txt']);


foreach ($collections as $collection){
	//use XML writer to generate RDF for void:dataDump	
	write_dump($collection);
	
	//create void:Dataset
	write_metadata($collection);
}

function write_dump($collection){
	$writer = new XMLWriter();
	$writer->openURI("{$collection['name']}.rdf");
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
	
	//iterate through export sets
	foreach ($collection['sets'] as $set){
		$list = file_get_contents($set);
		$files = explode(PHP_EOL, $list);
	
		echo "Parsing set {$set}\n";
	
		$count = 1;
		foreach ($files as $file){
			if (strlen($file) > 0){
				process_row($file, $collection, $writer, $count);
				$count++;
				}
			}
		}
	
		$writer->endElement();
		$writer->flush();
}

function process_row($file, $collection, $writer, $count){
	$fileArray = explode('/', $file);
	$id = str_replace('.xml', '', $fileArray[count($fileArray) - 1]);
	
	$dom = new DOMDocument('1.0', 'UTF-8');
	if ($dom->load($file) === FALSE){
		echo "{$file} failed to load.\n";
	} else {
		$xpath = new DOMXpath($dom);
		$xpath->registerNamespace("lido", "http://www.lido-schema.org");
		
		//look for the Price URI
		$terms = $xpath->query("descendant::lido:term[@lido:label='typereference']");
		$hoardURI = null;
		$typeURI = null;
		
		foreach ($terms as $term){
			if (preg_match('/coinhoards\.org/', $term->nodeValue)){
				$hoardURI = $term->nodeValue;
				echo "Found {$hoardURI}\n";
			} else {
				$typeURI = $term->nodeValue;
				echo "Processing #{$count}: {$id}, {$typeURI}\n";
			} 
		}
		
		if (isset($typeURI)) {
			$title = $xpath->query("descendant::lido:titleSet/lido:appellationValue")->item(0)->nodeValue;
			if (strlen($xpath->query("descendant::lido:displayDate")->item(0)->nodeValue) > 0){
				$title .= ', ' . $xpath->query("descendant::lido:eventDate/lido:displayDate")->item(0)->nodeValue;
			}		
			$measurements = $xpath->query("descendant::lido:measurementsSet");
			
			$writer->startElement('nmo:NumismaticObject');
				$writer->writeAttribute('rdf:about', $collection['uri_space'] . $id);
				$writer->startElement('dcterms:title');
					$writer->writeAttribute('xml:lang', 'de');
					$writer->text($title);
				$writer->endElement();
				$writer->writeElement('dcterms:identifier', $id);
				$writer->startElement('nmo:hasCollection');
					$writer->writeAttribute('rdf:resource', $collection['collection']);
				$writer->endElement();
				$writer->startElement('nmo:hasTypeSeriesItem');
					$writer->writeAttribute('rdf:resource', $typeURI);
				$writer->endElement();
			
				//measurements
				foreach($measurements as $measurement){
					$type = $measurement->getElementsByTagNameNS('http://www.lido-schema.org', 'measurementType')->item(0)->nodeValue;
					$value = str_replace(',', '.', $measurement->getElementsByTagNameNS('http://www.lido-schema.org', 'measurementValue')->item(0)->nodeValue);
						
					switch ($type){
						case 'diameter':
							$writer->startElement('nmo:hasDiameter');
								$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
								$writer->text($value);
							$writer->endElement();
							break;
						case 'weight':
							$writer->startElement('nmo:hasWeight');
								$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
								$writer->text($value);
							$writer->endElement();
							break;
						case 'orientation':
							$writer->startElement('nmo:hasAxis');
								$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
								$writer->text($value);
							$writer->endElement();
							break;
					}
				}
				
			//images
			$image_url = $xpath->query("descendant::lido:resourceRepresentation/lido:linkResource")->item(0)->nodeValue;
			if (strlen($image_url) > 0){
				$pieces = explode('/', $image_url);
				$image_id = $pieces[5];

				switch ($collection['name']){
					case 'berlin':
						$image_path = 'http://ww2.smb.museum';
						break;
					case 'muenster':
						$image_path = 'http://archaeologie.uni-muenster.de';
						break;
				}
					
				//obverse
				$writer->startElement('nmo:hasObverse');
					$writer->startElement('rdf:Description');
						$writer->startElement('foaf:thumbnail');
							$writer->writeAttribute('rdf:resource', "{$image_path}/mk_edit/images/{$image_id}/vs_thumb.jpg");
						$writer->endElement();
						$writer->startElement('foaf:depiction');
							$writer->writeAttribute('rdf:resource', "{$image_path}/mk_edit/images/{$image_id}/vs_opt.jpg");
						$writer->endElement();
					$writer->endElement();
				$writer->endElement();
					
				//reverse
				$writer->startElement('nmo:hasReverse');
					$writer->startElement('rdf:Description');
						$writer->startElement('foaf:thumbnail');
							$writer->writeAttribute('rdf:resource', "{$image_path}/mk_edit/images/{$image_id}/rs_thumb.jpg");
						$writer->endElement();
						$writer->startElement('foaf:depiction');
							$writer->writeAttribute('rdf:resource', "{$image_path}/mk_edit/images/{$image_id}/rs_opt.jpg");
						$writer->endElement();
					$writer->endElement();
				$writer->endElement();
			}				
			
			//if the $hoardURI is set, then use the hoard URI in dcterms:isPartOf; otherwise, use nmo:hasFindspot for finding_place URI
			$geonameId = '';
			$findspotUri = '';
			
			if (isset($hoardURI)) {
				$writer->startElement('dcterms:isPartOf');
					$writer->writeAttribute('rdf:resource', $hoardURI);
				$writer->endElement();
			} else {				
				$places = $xpath->query("descendant::lido:place");
				foreach ($places as $place){
					$attr = $place->getAttribute('lido:politicalEntity');
					
					if ($attr == 'finding_place'){						
						$findspots = $place->getElementsByTagNameNS('http://www.lido-schema.org', 'placeID');
						
						foreach ($findspots as $findspot){
							$findspotUri = $findspot->nodeValue;
							if (strstr($findspotUri, 'geonames') != FALSE) {
								$ffrags = explode('/', $findspotUri);
								$geonameId = $ffrags[3];
									
								//if the id is valid
								if ($geonameId != '0'){
									echo "Found {$findspotUri}\n";
										$writer->startElement('nmo:hasFindspot');
											$writer->writeAttribute('rdf:resource', $findspotUri);
										$writer->endElement();
									break;
								}
							} elseif (strstr($findspotUri, 'nomisma') !== FALSE){
									$writer->startElement('nmo:hasFindspot');
										$writer->writeAttribute('rdf:resource', $findspotUri . '#this');
									$writer->endElement();
							}
						}
					}					
				}
			}				
			
			//void:inDataset
			$writer->startElement('void:inDataset');
				$writer->writeAttribute('rdf:resource', $collection['homepage']);
			$writer->endElement();
			
			//end nmo:NumismaticObject
			$writer->endElement();
			
			//get coordinates
			if (strlen($geonameId) > 0 && $geonameId != '0'){
				$service = 'http://api.geonames.org/get?geonameId=' . $geonameId . '&username=anscoins&style=full';
				$coords = query_geonames($service);
				
				$writer->startElement('geo:SpatialThing');
					$writer->writeAttribute('rdf:about', $findspotUri);
					$writer->writeElement('foaf:name', $coords['name']);
					$writer->startElement('geo:lat');
						$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
						$writer->text($coords['lat']);
					$writer->endElement();
					$writer->startElement('geo:long');
						$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
						$writer->text($coords['long']);
					$writer->endElement();
				$writer->endElement();
			}
		}
	}			
}

function query_geonames($service){
	$dom = new DOMDocument('1.0', 'UTF-8');
	if ($dom->load($service) === FALSE){
		echo "{$service} failed to load.\n";
	} else {
		$xpath = new DOMXpath($dom);
		
		$coords = array();
		$name = $xpath->query('descendant::name')->item(0)->nodeValue . ' (' . $xpath->query('descendant::countryName')->item(0)->nodeValue . ')';
		$coords['name'] = $name;
		$coords['lat'] = $xpath->query('descendant::lat')->item(0)->nodeValue;
		$coords['long'] = $xpath->query('descendant::lng')->item(0)->nodeValue;
		
		return $coords;
	}
}

function write_metadata($collection){
	$writer = new XMLWriter();
	$writer->openURI("{$collection['name']}.void.rdf");
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
	
	$writer->startElement('void:Dataset');
		$writer->writeAttribute('rdf:about', $collection['homepage']);
		foreach ($collection['title'] as $lang=>$title){
			$writer->startElement('dcterms:title');
				$writer->writeAttribute('xml:lang', $lang);
				$writer->text($title);
			$writer->endElement();
			
			if ($lang == 'en'){
				$writer->writeElement('dcterms:publisher', $title);
			}
		}
		foreach ($collection['description'] as $lang=>$description){
			$writer->startElement('dcterms:description');
				$writer->writeAttribute('xml:lang', $lang);
				$writer->text($description);
			$writer->endElement();
		}		
		$writer->startElement('dcterms:license');
			$writer->writeAttribute('rdf:resource', $collection['license']);
		$writer->endElement();
		$writer->startElement('void:dataDump');
			$writer->writeAttribute('rdf:resource', 'http://numismatics.org/rdf/' . $collection['name'] . '.rdf');
		$writer->endElement();
	$writer->endElement();
	
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