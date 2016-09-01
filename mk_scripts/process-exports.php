<?php 
//libxml timeout
$options = ['http' => ['method' => 'GET','timeout' => '10']];
$context = stream_context_create($options);
libxml_set_streams_context($context);

//load RIC 10 number, auth k=>v pairs
$data = generate_json('ric10-pairs.csv');
$pairs = array();
foreach ($data as $row){
	$pairs[$row['key']] = $row['val'];
}

//store successful hits
$types = array();

//load the collection metadata stored in a Google Spreadsheet
$collections = generate_json('https://docs.google.com/spreadsheets/d/1I01Nva_udl0DHJnsjEQ_z-Q1XJaZ6uuAptLVoQSrNfU/pub?gid=0&single=true&output=csv');

foreach ($collections as $collection){
	//if the $id is specified, only process that collection
	if (isset($argv[1]) > 0){
		$id = $argv[1];
		if ($collection['project_id'] == $id){
			//use XML writer to generate RDF for void:dataDump
			write_dump($collection);
			
			//create void:Dataset
			write_metadata($collection);
		}
	} else {
		//use XML writer to generate RDF for void:dataDump
		write_dump($collection);
		
		//create void:Dataset
		write_metadata($collection);
	}
}

function write_dump($collection){
	$writer = new XMLWriter();
	$writer->openURI("{$collection['project_id']}.rdf");
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
	foreach (explode('|', $collection['sets']) as $set){
		$list = file_get_contents(trim($set));
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
		
		//look for the URI
		$terms = $xpath->query("descendant::lido:term[@lido:label='typereference']");
		$hoardURI = null;
		$typeURI = null;
		
		foreach ($terms as $term){
			if (preg_match('/coinhoards\.org/', $term->nodeValue)){
				$hoardURI = $term->nodeValue;
				echo "Found {$hoardURI}\n";
			} else {
				$typeURI = $term->nodeValue;				
			} 
		}
		
		//if the type URI can be assertained from the typereference LIDO field
		if (isset($typeURI)){
			echo "Processing #{$count}: {$id}, {$typeURI}\n";
			generateNumismaticObject($id, $typeURI, $collection, $xpath, $writer);
		} else {
			$typeURI = parseReference($xpath);
			
			if (isset($typeURI)){
				echo "Processing #{$count}: {$id}, {$typeURI}\n";
				generateNumismaticObject($id, $typeURI, $collection, $xpath, $writer);
			} else {
				echo "Processing #{$count}: {$id}, unable to match.\n";
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

function parseReference($xpath){
	GLOBAL $types;
	GLOBAL $pairs;
	
	$refNodes = $xpath->query("descendant::lido:relatedWorkSet[lido:relatedWorkRelType/lido:term='reference']/lido:relatedWork/lido:object/lido:objectNote");
	if ($refNodes->length > 0){
		$ref = $refNodes->item(0)->nodeValue;
		//RIC
		if (strpos($ref, 'RIC') !== FALSE){
			$ref = str_replace(',', '', $ref);
			$pieces = explode(' ', $ref);
			$vol = $pieces[1];
			
			//assemble id
			$nomismaId = array();
			$nomismaId[] = 'ric';
			
			//volume
			switch ($vol) {
				case 'I²':
					$nomismaId[] = '1(2)';
					break;
				case 'II-1²':
					$nomismaId[] = '2_1(2)';
					break;
				case 'II':
					$nomismaId[] = '2';
					break;
				case 'III':
					$nomismaId[] = '3';
					break;
				case 'IV-1':
				case 'IV-2':
				case 'IV-3':
					$nomismaId[] = '4';
					break;
				case 'V-1':
					$nomismaId[] = '5';
					break;
				case 'V-2':
					$nomismaId[] = '5';
					break;
				case 'VI':
					$nomismaId[] = '6';
					break;
				case 'VII':
					$nomismaId[] = '7';
					break;
				case 'VIII':
					$nomismaId[] = '8';
					break;
				case 'X':
					$nomismaId[] = '10';
					break;
				default:
					$nomismaId[] = null;
			}
			
			//normalize authority
			$names = array_slice($pieces, 2, count($pieces) - 3);
			$authority = implode(' ', $names);
			switch ($authority) {
				case 'Augustus':
					$nomismaId[] = 'aug';
					break;
				case 'Tiberius':
					$nomismaId[] = 'tib';
					break;
				case 'Caligula':
					$nomismaId[] = 'gai';
					break;
				case 'Claudius':
					$nomismaId[] = 'cl';
					break;
				case 'Nero':
					$nomismaId[] = 'ner';
					break;
				case 'Galba':
					$nomismaId[] = 'gal';
					break;
				case 'Otho':
					$nomismaId[] = 'ot';
					break;
				case 'Vitellius':
					$nomismaId[] = 'vit';
					break;
				case 'Macer':
					$nomismaId[] = 'clm';
					break;
				case 'Civil Wars':
					$nomismaId[] = 'cw';
					break;
				case 'Vespasianus':
					$nomismaId[] = 'ves';
					break;
				case 'Titus':
					$nomismaId[] = 'tit';
					break;
				case 'Domitianus':
					$nomismaId[] = 'dom';
					break;
				case 'Nerva':
					$nomismaId[] = 'ner';
					break;
				case 'Traianus':
					$nomismaId[] = 'tr';
					break;
				case 'Hadrianus':
					$nomismaId[] = 'hdn';
					break;
				case 'Pius':
					$nomismaId[] = 'ant';
					break;
				case 'Aurelius':
					$nomismaId[] = 'm_aur';
					break;
				case 'Commodus':
					$nomismaId[] = 'com';
					break;
				case 'Pertinax':
					$nomismaId[] = 'pert';
					break;
				case 'Didius Iulianus':
					$nomismaId[] = 'dj';
					break;
				case 'Niger':
					$nomismaId[] = 'pn';
					break;
				case 'Clodius Albinus':
					$nomismaId[] = 'ca';
					break;
				case 'Septimius Severus':
					$nomismaId[] = 'ss';
					break;
				case 'Caracalla':
					$nomismaId[] = 'crl';
					break;
				case 'Geta':
					$nomismaId[] = 'ge';
					break;
				case 'Macrinus':
					$nomismaId[] = 'mcs';
					break;
				case 'Elagabalus':
					$nomismaId[] = 'el';
					break;
				case 'Severus Alexander':
					$nomismaId[] = 'sa';
					break;
				case 'Maximinus Thrax':
					$nomismaId[] = 'max_i';
					break;
				case 'Maximus':
					$nomismaId[] = 'mxs';
					break;
				case 'Diva Paulina':
					$nomismaId[] = 'pa';
					break;
				case 'Gordianus I.':
					$nomismaId[] = 'gor_i';
					break;
				case 'Gordianus II.':
					$nomismaId[] = 'gor_ii';
					break;
				case 'Pupienus':
					$nomismaId[] = 'pup';
					break;
				case 'Balbinus':
					$nomismaId[] = 'balb';
					break;
				case 'Gordianus III. Caesar':
					$nomismaId[] = 'gor_iii_caes';
					break;
				case 'Gordianus III.':
					$nomismaId[] = 'gor_iii';
					break;
				case 'Philippus I.':
					$nomismaId[] = 'ph_i';
					break;
				case 'Pacatianus':
					$nomismaId[] = 'pac';
					break;
				case 'Iotapianus':
					$nomismaId[] = 'jot';
					break;
				case 'Decius':
					$nomismaId[] = 'tr_d';
					break;
				case 'Trebonianus':
					$nomismaId[] = 'tr_g';
					break;
				case 'Aemilianus':
					$nomismaId[] = 'aem';
					break;
				case 'Uranus':
					$nomismaId[] = 'uran_ant';
					break;
				case 'Valerianus':
					$nomismaId[] = 'val_i';
					break;
				case 'Mariniana':
					$nomismaId[] = 'mar';
					break;
				case 'Gallienus Mitherrscher':
					$nomismaId[] = 'gall(1)';
					break;
				case 'Salonina Mitherrscher':
					$nomismaId[] = 'sala(1)';
					break;
				case 'Saloninus':
					$nomismaId[] = 'sals';
					break;
				case 'Valerianus II.':
					$nomismaId[] = 'val_ii';
					break;
				case 'Gallienus':
					$nomismaId[] = 'gall(2)';
					break;
				case 'Salonina':
					$nomismaId[] = 'sala(2)';
					break;
				case 'Claudius Gothicus':
					$nomismaId[] = 'cg';
					break;
				case 'Quintillus':
					$nomismaId[] = 'qu';
					break;
				case 'Aurelianus':
					$nomismaId[] = 'aur';
					break;
				case 'Severina':
					$nomismaId[] = 'seva';
					break;
				case 'Tacitus':
					$nomismaId[] = 'tac';
					break;
				case 'Florianus':
					$nomismaId[] = 'fl';
					break;
				case 'Probus':
					$nomismaId[] = 'pro';
					break;
				case 'Carus/Familie':
					$nomismaId[] = 'car';
					break;
				case 'Tetrarchie (vorreform)':
					$nomismaId[] = 'dio';
					break;
				case 'Postumus':
					$nomismaId[] = 'post';
					break;
				case 'Laelianus':
					$nomismaId[] = 'lae';
					break;
				case 'Marius':
					$nomismaId[] = 'mar';
					break;
				case 'Victorinus':
					$nomismaId[] = 'vict';
					break;
				case 'Tetrici':
					$nomismaId[] = 'tet_i';
					break;
				case 'Carausius':
					$nomismaId[] = 'cara';
					break;
				case 'Carausius, Diocletianus, Maximianus Herculius':
					$nomismaId[] = 'cara-dio-max_her';
					break;
				case 'Allectus':
					$nomismaId[] = 'all';
					break;
				case 'Macrianus':
					$nomismaId[] = 'mac_ii';
					break;
				case 'Quietus':
					$nomismaId[] = 'quit';
					break;
				case 'Regalianus':
					$nomismaId[] = 'reg';
					break;
				case 'Dryantilla':
					$nomismaId[] = 'dry';
					break;
				case 'Iulianus von Pannonien':
					$nomismaId[] = 'jul_i';
					break;
				case 'Alexandria':
					$nomismaId[]='alex';
					break;
				case 'Amiens':
					$nomismaId[]='amb';
					break;
				case 'Antioch':
					$nomismaId[]='anch';
					break;
				case 'Antiochia':
					$nomismaId[]='anch';
					break;
				case 'Aquileia':
					$nomismaId[]='aq';
					break;
				case 'Arles':
					$nomismaId[]='ar';
					break;
				case 'Carthago':
					$nomismaId[]='carth';
					break;
				case 'Constantinople':
					$nomismaId[]='cnp';
					break;
				case 'Cyzicus':
					$nomismaId[]='cyz';
					break;
				case 'Heraclea':
					$nomismaId[]='her';
					break;
				case 'London':
				case 'Londinium':
					$nomismaId[]='lon';
					break;
				case 'Lugdunum':
				case 'Lyons':
					$nomismaId[]='lug';
					break;
				case 'Nicomedia':
					$nomismaId[]='nic';
					break;
				case 'Ostia':
					$nomismaId[]='ost';
					break;
				case 'Roma':
				case 'Rome':
					$nomismaId[]='rom';
					break;
				case 'Serdica':
					$nomismaId[]='serd';
					break;
				case 'Sirmium':
					$nomismaId[]='sir';
					break;
				case 'Siscia':
					$nomismaId[]='sis';
					break;
				case 'Thessalonica':
					$nomismaId[]='thes';
					break;
				case 'Ticinum':
					$nomismaId[]='tic';
					break;
				case 'Treveri':
				case 'Trier':
					$nomismaId[]='tri';
					break;
				default:
					$nomismaId[] = null;
			}
			
			//add number
			$num = ltrim($pieces[count($pieces) - 1], '0');
			
			//test which number matches in OCRE
			if (strlen($num) > 0){
				if ($vol == 'X'){
					echo "RIC 10:\n";					
					// handle RIC 10 in a lookup table
					if (array_key_exists($num, $pairs)){
						$nomismaId[] = $pairs[$num];
						$uri = 'http://numismatics.org/ocre/id/' . implode('.', $nomismaId) .  '.' . $num;
								
						if (in_array($uri, $types)){
							return $uri;
						} else {
							$file_headers = @get_headers($uri . '.xml');
							if ($file_headers[0] == 'HTTP/1.1 200 OK'){
								$types[] = $uri;
								return $uri;
							}
						}
					}
				} elseif ($nomismaId[1] != null && $nomismaId[2] != null){
					$uri = 'http://numismatics.org/ocre/id/' . implode('.', $nomismaId) . '.' . strtoupper($num);
					//see if the URI is already in the validated array
					if (in_array($uri, $types)){
						return $uri;
					} else {
						$file_headers = @get_headers($uri . '.xml');
						if ($file_headers[0] == 'HTTP/1.1 200 OK'){
							$types[] = $uri;
							return $uri;
						} else {
							$uri = 'http://numismatics.org/ocre/id/' . implode('.', $nomismaId) .  '.' . $num;
							 
							//see if the URI is already in the validated array
							if (in_array($uri, $types)){
								return $uri;
							} else {
								$file_headers = @get_headers($uri . '.xml');
								if ($file_headers[0] == 'HTTP/1.1 200 OK'){
									$types[] = $uri;
									return $uri;
								}
							}
						}
					}
				} 
			} 			 
		} else if (strpos($ref, 'RRC') !== FALSE){
			//RRC
			$pieces = explode(',', $ref);
			$frag = array();
			$frag[] = ltrim(trim($pieces[1]), '0');
			if (isset($pieces[2])) {
				$frag[] = ltrim(trim($pieces[2]), '0');
			} else {
				$frag[] = '1';
			}
				
			$id = 'rrc-' . implode('.', $frag);				
			$uri = 'http://numismatics.org/crro/id/' . $id;
			
			//see if the URI is already in the validated array
			if (in_array($uri, $types)){
				return $uri;
			} else {
				$file_headers = @get_headers($uri . '.xml');
				if ($file_headers[0] == 'HTTP/1.1 200 OK'){
					$types[] = $uri;
					return $uri;
				}
			}						
		}
	}
}

function generateNumismaticObject($id, $typeURI, $collection, $xpath, $writer){
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
			$writer->writeAttribute('rdf:resource', $collection['collection_uri']);
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
	$image_url = $xpath->query("descendant::lido:resourceRepresentation[@lido:type='image_thumb']/lido:linkResource")->item(0)->nodeValue;
	if (strlen($image_url) > 0){
		$pieces = explode('/', $image_url);
		$fname = array_pop($pieces);		
		$image_path = implode('/', $pieces);
		
			
		//obverse
		$writer->startElement('nmo:hasObverse');
			$writer->startElement('rdf:Description');
				$writer->startElement('foaf:thumbnail');
					$writer->writeAttribute('rdf:resource', "{$image_path}/vs_thumb.jpg");
				$writer->endElement();
				$writer->startElement('foaf:depiction');
					$writer->writeAttribute('rdf:resource', "{$image_path}/vs_opt.jpg");
				$writer->endElement();
			$writer->endElement();
		$writer->endElement();
			
		//reverse
		$writer->startElement('nmo:hasReverse');
			$writer->startElement('rdf:Description');
				$writer->startElement('foaf:thumbnail');
					$writer->writeAttribute('rdf:resource', "{$image_path}/rs_thumb.jpg");
				$writer->endElement();
				$writer->startElement('foaf:depiction');
					$writer->writeAttribute('rdf:resource', "{$image_path}/rs_opt.jpg");
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
		$writer->writeAttribute('rdf:resource', $collection['database_homepage']);
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

function write_metadata($collection){
	$writer = new XMLWriter();
	$writer->openURI("{$collection['project_id']}.void.rdf");
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
		$writer->writeAttribute('rdf:about', $collection['database_homepage']);
		
		//titles
		if (strlen($collection['title_en']) > 0){
			$writer->startElement('dcterms:title');
				$writer->writeAttribute('xml:lang', 'en');
				$writer->text(trim($collection['title_en']));
			$writer->endElement();
		}
		if (strlen($collection['title_de']) > 0){
			$writer->startElement('dcterms:title');
				$writer->writeAttribute('xml:lang', 'de');
				$writer->text(trim($collection['title_de']));
			$writer->endElement();
		}	
		
		//descriptions
		if (strlen($collection['description_en']) > 0){
			$writer->startElement('dcterms:description');
				$writer->writeAttribute('xml:lang', 'en');
				$writer->text(trim($collection['description_en']));
			$writer->endElement();
		}
		if (strlen($collection['description_de']) > 0){
			$writer->startElement('dcterms:description');
				$writer->writeAttribute('xml:lang', 'de');
				$writer->text(trim($collection['description_de']));
			$writer->endElement();
		}	
		$writer->writeElement('dcterms:publisher', $collection['publisher']);
		$writer->startElement('dcterms:license');
			$writer->writeAttribute('rdf:resource', $collection['license']);
		$writer->endElement();
		$writer->startElement('void:dataDump');
			$writer->writeAttribute('rdf:resource', 'http://numismatics.org/rdf/' . $collection['project_id'] . '.rdf');
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