<?php 
 /***** 
 * Author: Ethan Gruber
 * Last Modified: November 2018
 * Function: Harvest the MK Berlin-style lists of LIDO XML files given metadata stored in a Google spreadsheet.
 *****/


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

//store total reference concordance
$results = array();

//store Geonames IDs
$geonames = array();

//load the collection metadata stored in a Google Spreadsheet
$collections = generate_json('https://docs.google.com/spreadsheets/d/1I01Nva_udl0DHJnsjEQ_z-Q1XJaZ6uuAptLVoQSrNfU/pub?gid=0&single=true&output=csv');

foreach ($collections as $collection){
	//if the $id is specified, only process that collection
	if (isset($argv[1]) > 0){
		$id = $argv[1];
		if ($collection['project_id'] == $id){
			//parse all exports into a $records array
			parse_dump($collection);
		}
	} else {
		parse_dump($collection);
	}
	unset($geonames);
	$geonames = array();
	unset($results);
	$results = array();
}

function parse_dump($collection){
	$records = array();
	
	//iterate through export sets
	foreach (explode('|', $collection['sets']) as $set){
		$list = file_get_contents(trim($set));
		$files = explode(PHP_EOL, $list);
		
		echo "Parsing set {$set}\n";
		
		$count = 1;
		foreach ($files as $file){
			if (strlen($file) > 0){
				$fileArray = explode('/', $file);
				$id = str_replace('.xml', '', $fileArray[count($fileArray) - 1]);
				
				//only insert the record into the records array only if it's unique
				$record = parse_lido($file, $collection, $count);
				
				if (isset($record)){
				    if (!array_key_exists($id, $records)){
				        $records[$id] = $record;
				    } else {
				        echo "{$id} has already been processed, and therefore not added to final RDF output.\n";
				    }
				    $count++;
				}
			}
		}
	}
	
	//use XML writer to generate RDF for void:dataDump, using the $records array
	write_dump($collection, $records);
	
	//create void:Dataset
	write_metadata($collection);
	
	//write concordance CSV
    //write_csv($collection['project_id'], $records);
}

function parse_lido($file, $collection, $count){
	$fileArray = explode('/', $file);
	$id = str_replace('.xml', '', $fileArray[count($fileArray) - 1]);
	
	$dom = new DOMDocument('1.0', 'UTF-8');
	if ($dom->load($file) === FALSE){
		echo "{$file} failed to load.\n";
	} else {		
		$xpath = new DOMXpath($dom);
		$xpath->registerNamespace("lido", "http://www.lido-schema.org");
		
		//look for the URI
		$terms = $xpath->query("descendant::lido:*[@lido:label='typereference']");
		$hoardURI = null;
		$typeURI = null;
		
		foreach ($terms as $term){
			if (preg_match('/coinhoards\.org/', $term->nodeValue)){
				$hoardURI = $term->nodeValue;
				echo "Found {$hoardURI}\n";
			} else {
				//ignore IKMK type references
				$source = $term->getAttribute('lido:source');
				if ($source == 'crro' || $source == 'ocre' || $source == 'pella'){
					$typeURI = array($term->nodeValue);
					
					//look for additional SCO refs when there's also already a PELLA URI
					$SCORefs = $xpath->query("descendant::lido:relatedWorkSet[lido:relatedWorkRelType/lido:term='reference']/lido:relatedWork/lido:object/lido:objectNote[contains(., 'Seleucid Coins')]");
					if ($SCORefs->length > 0){
						$SCOUri = parseReference($xpath, $collection['project_id'], $id);
						if (strlen(trim($SCOUri)) > 0){
							$typeURI[] = $SCOUri;
						}
					}
				}
			}
		}
		
		//if the type URI can be assertained from the typereference LIDO field
		if (isset($typeURI)){
			if (is_array($typeURI)){
				$out = implode(',', $typeURI);
				echo "Processing #{$count}: {$id}, {$out}\n";
			} else {
				echo "Processing #{$count}: {$id}, {$typeURI}\n";
			}
			return extract_metadata($id, $typeURI, $hoardURI, $collection, $xpath, true);
			
			//$results[] = array($id, $typeURI, '', 'yes');
		} else {	
		    //otherwise use the textual reference for evaluating OCRE URI
			$ref = parseReference($xpath, $collection['project_id'], $id);			
			
			echo "{$id}: {$ref}\n";
			
			if (isset($ref)){
				$typeURI = array($ref);
				$out = implode(',', $typeURI);
				echo "Processing #{$count}: {$id}, {$out}\n";
				
				return extract_metadata($id, $typeURI, $hoardURI, $collection, $xpath, false);
			} else {
			    return null;
			}
		}
	}	
}

//extract metadata from LIDO XML file and return an array
function extract_metadata ($id, $typeURI, $hoardURI, $collection, $xpath, $containsURI) {
	GLOBAL $geonames;
	$record = array();
	
	//insert metadata into $record array
	$title = $xpath->query("descendant::lido:titleSet/lido:appellationValue")->item(0)->nodeValue;
	if (strlen($xpath->query("descendant::lido:displayDate")->item(0)->nodeValue) > 0){
		$title .= ', ' . $xpath->query("descendant::lido:eventDate/lido:displayDate")->item(0)->nodeValue;
	}
	$measurements = $xpath->query("descendant::lido:measurementsSet");
	$workId = $xpath->query("descendant::lido:workID[@lido:type='inventory']");
	
	if ($workId->length > 0){
		$identifier = $workId->item(0)->nodeValue;
	} else {
		$identifier = $id;
	}
	
	$record['title'] = $title;
	$record['coinURI'] = $xpath->query("descendant::lido:recordInfoLink")->item(0)->nodeValue;
	$record['identifier'] = $identifier;
	$record['collection'] = $collection['collection_uri'];
	$record['containsURI'] = $containsURI;
	
	if (isset($typeURI)){	    
	    $record['coinType'] = $typeURI;
	}
	
	//measurements
	foreach($measurements as $measurement){
		$type = $measurement->getElementsByTagNameNS('http://www.lido-schema.org', 'measurementType')->item(0)->nodeValue;
		$value = str_replace(',', '.', $measurement->getElementsByTagNameNS('http://www.lido-schema.org', 'measurementValue')->item(0)->nodeValue);
		
		switch ($type){
			case 'diameter':
				if (is_numeric($value)){
					$record['diameter'] = $value;
				}
				break;
			case 'weight':
				if (is_numeric($value)){
					$record['weight'] = $value;
				}
				break;
			case 'orientation':
				if (is_int((int)$value)){
					$record['axis'] = $value;
				}
				break;
		}
	}
	
	//images
	$image_url = $xpath->query("descendant::lido:resourceRepresentation[@lido:type='image_thumb']/lido:linkResource")->item(0)->nodeValue;
	if (strlen($image_url) > 0){
		$pieces = explode('/', $image_url);
		$fname = array_pop($pieces);
		$image_path = implode('/', $pieces);
		
		$record['obvThumb'] = $image_path . '/vs_thumb.jpg';
		$record['obvRef'] = $image_path . '/vs_opt.jpg';
		$record['revThumb'] = $image_path . '/rs_thumb.jpg';
		$record['revRef'] = $image_path . '/rs_opt.jpg';
	}
	
	//if the $hoardURI is set, then use the hoard URI in dcterms:isPartOf; otherwise, use nmo:hasFindspot for finding_place URI
	if (isset($hoardURI)) {
		$record['hoard'] = $hoardURI;
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
							//add the id into the $geonames array
							if (!in_array($geonameId, $geonames)){
								$geonames[] = $geonameId;
							}
							$record['findspot'] = $findspotUri;
							break;
						}
					} elseif (strstr($findspotUri, 'nomisma') !== FALSE){
						$record['findspot'] = $findspotUri . '#this';
					}
				}
			}
		}
	}
	
	//void:inDataset
	$record['dataset'] = $collection['database_homepage'];
	
	//var_dump($record);
	return $record;	
}

//generate RDF for all of the numismatic objects in a collection, from the $records array
function write_dump($collection, $records){
	
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
	$writer->writeAttribute('xmlns:edm', "http://www.europeana.eu/schemas/edm/");
	$writer->writeAttribute('xmlns:svcs', "http://rdfs.org/sioc/services#");
	$writer->writeAttribute('xmlns:doap', "http://usefulinc.com/ns/doap#");
	
	//iterate through records
	foreach ($records as $record){
		generateNumismaticObject($record, $writer);
	}
	
	//insert geo:SpatialThings
	write_spatial_things($writer);
	
	$writer->endElement();
	$writer->flush();
}



function generateNumismaticObject($record, $writer){
	GLOBAL $geonames;
	
	//only write the NumismaticObject if it has a valid coin type URI
	if (array_key_exists('coinType', $record)){
		
	$writer->startElement('nmo:NumismaticObject');
		$writer->writeAttribute('rdf:about', $record['coinURI']);
		$writer->startElement('dcterms:title');
			$writer->writeAttribute('xml:lang', 'de');
			$writer->text($record['title']);
		$writer->endElement();
		$writer->writeElement('dcterms:identifier', $record['identifier']);
		$writer->startElement('nmo:hasCollection');
			$writer->writeAttribute('rdf:resource', $record['collection']);
		$writer->endElement();
		
		foreach ($record['coinType'] as $uri){
			$writer->startElement('nmo:hasTypeSeriesItem');
				$writer->writeAttribute('rdf:resource', $uri);
			$writer->endElement();
		}
	
		//measurements
		if (isset($record['diameter'])){
			$writer->startElement('nmo:hasDiameter');
				$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
				$writer->text($record['diameter']);
			$writer->endElement();
		}	
		if (isset($record['weight'])){
			$writer->startElement('nmo:hasWeight');
				$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
				$writer->text($record['weight']);
			$writer->endElement();
		}		
		if (isset($record['axis'])){
			$writer->startElement('nmo:hasAxis');
				$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
				$writer->text($record['axis']);
			$writer->endElement();
		}
		
		
		//add links to 3D models
		if ($record['coinURI']== 'http://www3.hhu.de/muenzkatalog/ikmk/object.php?id=ID4686'){
			$writer->startElement('edm:isShownBy');
				$writer->writeAttribute('rdf:resource', 'https://sketchfab.com/models/58f5c949894144b386103b6c3d039303');
			$writer->endElement();
		} elseif ($record['coinURI']== 'http://www3.hhu.de/muenzkatalog/ikmk/object.php?id=ID2050'){
			$writer->startElement('edm:isShownBy');
				$writer->writeAttribute('rdf:resource', 'https://sketchfab.com/models/e84acd856ce44f97844a9d9f592aeb3f');
			$writer->endElement();
		}
		
		//images
		//obverse
		if (isset($record['obvThumb']) && isset($record['obvRef'])){
			$writer->startElement('nmo:hasObverse');
				$writer->startElement('rdf:Description');
					$writer->startElement('foaf:thumbnail');
						$writer->writeAttribute('rdf:resource', $record['obvThumb']);
					$writer->endElement();
					$writer->startElement('foaf:depiction');
						$writer->writeAttribute('rdf:resource', $record['obvRef']);
					$writer->endElement();
				$writer->endElement();
			$writer->endElement();
		}
		
		//reverse
		if (isset($record['revThumb']) && isset($record['revRef'])){
			$writer->startElement('nmo:hasReverse');
				$writer->startElement('rdf:Description');
					$writer->startElement('foaf:thumbnail');
						$writer->writeAttribute('rdf:resource', $record['revThumb']);
					$writer->endElement();
					$writer->startElement('foaf:depiction');
						$writer->writeAttribute('rdf:resource', $record['revRef']);
					$writer->endElement();
				$writer->endElement();
			$writer->endElement();
		}
		
		//if the $hoardURI is set, then use the hoard URI in dcterms:isPartOf; otherwise, use nmo:hasFindspot for finding_place URI
		if (isset($record['hoard'])) {
			$writer->startElement('dcterms:isPartOf');
				$writer->writeAttribute('rdf:resource', $record['hoard']);
			$writer->endElement();
		}
	
		if (isset($record['findspot'])){
			$writer->startElement('nmo:hasFindspot');
				$writer->writeAttribute('rdf:resource', $record['findspot']);
			$writer->endElement();
		}			
	
		//void:inDataset
		$writer->startElement('void:inDataset');
			$writer->writeAttribute('rdf:resource', $record['dataset']);
		$writer->endElement();
		
		//end nmo:NumismaticObject
		$writer->endElement();
		
		//add 3D metadata if applicable
		if ($record['coinURI'] == 'http://www3.hhu.de/muenzkatalog/ikmk/object.php?id=ID4686'){
			$writer->startElement('edm:WebResource');
				$writer->writeAttribute('rdf:about', 'https://sketchfab.com/models/58f5c949894144b386103b6c3d039303');
				$writer->startElement('dcterms:format');
					$writer->writeAttribute('rdf:resource', 'http://vocab.getty.edu/aat/300053580');				
				$writer->endElement();
				$writer->writeElement('dcterms:publisher', 'NUMiD');
			$writer->endElement();
		} elseif ($record['coinURI'] == 'http://www3.hhu.de/muenzkatalog/ikmk/object.php?id=ID2050'){
			$writer->startElement('edm:WebResource');
				$writer->writeAttribute('rdf:about', 'https://sketchfab.com/models/e84acd856ce44f97844a9d9f592aeb3f');
				$writer->startElement('dcterms:format');
					$writer->writeAttribute('rdf:resource', 'http://vocab.getty.edu/aat/300053580');
				$writer->endElement();
				$writer->writeElement('dcterms:publisher', 'NUMiD');
			$writer->endElement();
		}
	}	
}

//generate all of the geo:SpatialThings from an array of geonames IDs. This reduces redundancy in the RDF output
function write_spatial_things($writer){
	GLOBAL $geonames;	
	
	if (count($geonames) > 0){
		foreach ($geonames as $geonameId){
			$findspotUri = 'http://www.geonames.org/' . $geonameId;
			echo "Generating geo:SpatialThing for {$findspotUri}\n";
			
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

function query_geonames($service){
	$dom = new DOMDocument('1.0', 'UTF-8');
	if ($dom->load($service) === FALSE){
		echo "{$service} failed to load.\n";
	} else {
		$xpath = new DOMXpath($dom);
		
		$coords = array();
		//generate AACR2-compliant place name
		$name = $xpath->query('descendant::name')->item(0)->nodeValue . ' (' . $xpath->query('descendant::countryName')->item(0)->nodeValue . ')';
		$coords['name'] = $name;
		$coords['lat'] = $xpath->query('descendant::lat')->item(0)->nodeValue;
		$coords['long'] = $xpath->query('descendant::lng')->item(0)->nodeValue;
		
		return $coords;
	}
}

//generate the VoID RDF document
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

function parseReference($xpath, $collection, $id){
	GLOBAL $types;
	GLOBAL $pairs;
	GLOBAL $results;
	
	$refNodes = $xpath->query("descendant::lido:relatedWorkSet[lido:relatedWorkRelType/lido:term='reference']/lido:relatedWork/lido:object/lido:objectNote");
	$refs = array();
	foreach ($refNodes as $refNode){
		$refs[] = $refNode->nodeValue;
	}
	
	$fullRef = implode('; ', $refs);
	
	if ($refNodes->length > 0){
		foreach ($refNodes as $refNode){
			$string = $refNode->nodeValue;
			$bibs = explode(';', $string);
			foreach ($bibs as $ref){
				//RIC
				$ref = trim($ref);
				if (strpos($ref, 'RIC') !== FALSE){
					//only parse those starting with RIC to throw an error for c.f., and the like
					if (preg_match('/^RIC/', $ref)){
						if (strpos($ref, 'RIC X') !== FALSE){
							preg_match('/^RIC\s(X),?\s(\d.*)$/', $ref, $pieces);
						} else {
							preg_match('/^RIC\s([^\s]+)\s([^\d]+)\s(\d.*)$/', $ref, $pieces);
						}
						
						//var_dump($pieces);
						
						if (isset($pieces[1]) && isset($pieces[2])){
							$vol = $pieces[1];
							
							if ($vol == 'X'){
								$authority = '';
								$nums = trim($pieces[2]);
							} else {
								$authority = trim(str_replace(',', '', $pieces[2]));
								if (isset($pieces[3])){
									$nums = $pieces[3];
								} else {
									$nums = '';
								}
							}
							
							
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
								case 'IX':
									$nomismaId[] = '9';
									break;
								case 'X':
									$nomismaId[] = '10';
									break;
								default:
									$nomismaId[] = null;
							}
							
							//normalize authority
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
									$nomismaId[] = 'marin';
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
								case 'Constantinopolis':
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
							
							//add number:
							//$num = ltrim($pieces[count($pieces) - 1], '0');
							
							//find numbers through regular expressions
							preg_match_all('/(\d{1,4}[A-Za-z]{1,2}|\d{1,4})/', $nums, $numArray);
							echo "Parsing {$ref}\n";
							
							if (isset($numArray[1])){
								$matches = $numArray[1];
								//only parse if there's one parseable RIC number
								if (count($matches) == 1){
									$num = ltrim($matches[0], '0');
									if ($vol == 'X'){
										//echo "RIC 10:\n";
										// handle RIC 10 in a lookup table
										if (array_key_exists($num, $pairs)){
											//replace a null value for $nomismaId[2] with the new authority pair
											$nomismaId[2] = $pairs[$num];
											$uri = 'http://numismatics.org/ocre/id/' . implode('.', $nomismaId) .  '.' . $num;
											
											if (in_array($uri, $types)){
												//$results[] = array($id, $uri, $fullRef, 'no');
												return $uri;
											} else {
												$file_headers = @get_headers($uri . '.xml');
												if ($file_headers[0] == 'HTTP/1.1 200 OK'){
													$types[] = $uri;
													//$results[] = array($id, $uri, $fullRef, 'no');
													return $uri;
												} else {
													echo "{$id}: unable to match '{$fullRef}'.\n";
													//$results[] = array($id, '', $fullRef, 'no');
												}
											}
										}
									} elseif ($nomismaId[1] != null && $nomismaId[2] != null){
										$uri = 'http://numismatics.org/ocre/id/' . implode('.', $nomismaId) . '.' . strtoupper($num);
										//see if the URI is already in the validated array
										if (in_array($uri, $types)){
											//$results[] = array($id, $uri, $fullRef, 'no');
											return $uri;
										} else {
											$file_headers = @get_headers($uri . '.xml');
											if ($file_headers[0] == 'HTTP/1.1 200 OK'){
												$types[] = $uri;
												//$results[] = array($id, $uri, $fullRef, 'no');
												return $uri;
											} else {
												$uri = 'http://numismatics.org/ocre/id/' . implode('.', $nomismaId) .  '.' . $num;
												
												//see if the URI is already in the validated array
												if (in_array($uri, $types)){
													//$results[] = array($id, $uri, $fullRef, 'no');
													return $uri;
												} else {
													$file_headers = @get_headers($uri . '.xml');
													if ($file_headers[0] == 'HTTP/1.1 200 OK'){
														$types[] = $uri;
														//$results[] = array($id, $uri, $fullRef, 'no');
														return $uri;
													} else {
														echo "{$id}: unable to match '{$fullRef}'.\n";
														//$results[] = array($id, '', $fullRef, 'no');
													}
												}
											}
										}
									}
								} else {
									//if there's more than one possible RIC ID, then add into $results array
									echo "{$id}: unable to match '{$fullRef}'.\n";
									//$results[] = array($id, '', $fullRef, 'no');
								}
							} else {
								//if no RIC numbers are parsed, then add into $results array
								echo "{$id}: unable to match '{$fullRef}'.\n";
								//$results[] = array($id, '', $fullRef, 'no');
							}
						}
					} else {
						//if c.f., or similar uncertain attribution, add into $results array
						echo "{$id}: unable to match '{$fullRef}'.\n";
						//$results[] = array($id, '', $fullRef, 'no');
					}
				} else if (strpos($ref, 'RRC') !== FALSE){
					//RRC
					$pieces = explode(',', $ref);
					
					if ($collection == 'vienna'){
						$id = 'rrc-' . str_replace('/', '.', ltrim(trim($pieces[1]), '0'));
						$uri = 'http://numismatics.org/crro/id/' . $id;
					} else {
						$frag = array();
						$frag[] = ltrim(trim($pieces[1]), '0');
						if (isset($pieces[2])) {
							$frag[] = ltrim(trim($pieces[2]), '0');
						} else {
							$frag[] = '1';
						}
						
						$id = 'rrc-' . implode('.', $frag);
						$uri = 'http://numismatics.org/crro/id/' . $id;
					}
					
					//see if the URI is already in the validated array
					if (in_array($uri, $types)){
						//$results[] = array($id, $uri, $fullRef, 'no');
						return $uri;
					} else {
						$file_headers = @get_headers($uri . '.xml');
						if ($file_headers[0] == 'HTTP/1.1 200 OK'){
							$types[] = $uri;
							//$results[] = array($id, $uri, $fullRef, 'no');
							return $uri;
						} else {
							//$results[] = array($id, '', $fullRef, 'no');
						}
					}
				} else if (strpos($ref, 'Seleucid Coins') !== FALSE){
					//SC
					$pieces = explode(',', $ref);
					
					$frag = array();
					$frag[] = ltrim(trim($pieces[1]), '0');
					if (isset($pieces[2])) {
						$frag[] = ltrim(trim($pieces[2]), '0');
					}
					
					$id = 'sc.1.' . implode('.', $frag);
					$uri = 'http://numismatics.org/sco/id/' . $id;
					
					//always look it up
					$file_headers = @get_headers($uri);
					if ($file_headers[0] == 'HTTP/1.1 200 OK'){
						$types[] = $uri;
						return $uri;
					} else {
					    //echo "No reference: {$ref}\n";
						//$results[] = array($id, '', $fullRef, 'no');
					}
				} else if (strpos($ref, 'CPE') !== FALSE){
				    //CPE
				    $pieces = explode(',', $ref);				    
				    
				    $id = 'cpe.1_1.' . ltrim(trim($pieces[1]), '0');
				    $uri = 'http://numismatics.org/pco/id/' . $id;
				    $file_headers = @get_headers($uri);
				    if ($file_headers[0] == 'HTTP/1.1 200 OK'){
				        $types[] = $uri;
				        return $uri;
				    } else {
				        //echo "No reference: {$ref}\n";
				        //$results[] = array($id, '', $fullRef, 'no');
				    }
				}
			}
		}
	} else {
		//if 0 or more than 1, just output the ref
		echo "No reference\n";
		//$results[] = array($id, '', '', 'no');
	}
	
	
	//only process if there's only one reference
	/*if ($refNodes->length == 1){
	 $ref = $refNodes->item(0)->nodeValue;
	 
	 } else {
	 //if 0 or more than 1, just output the ref
	 echo "Cannot parse {$fullRef}\n";
	 $results[] = array($id, '', $fullRef, 'no');
	 }*/
}

//generate a CSV file from a concordance list of IDs, URIs, and textual references in $results
function write_csv($collection, $records){
	$heading = array('id','coinType','ref','contains_uri');
	
	$file = fopen($collection . '.csv', 'w');
	
	fputcsv($file, $heading);
	foreach ($records as $record) {
	    $row = array($record['identifier'], implode('|', $record['coinType']), '', '');	    
		fputcsv($file, $row);
	}
	
	fclose($file);
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