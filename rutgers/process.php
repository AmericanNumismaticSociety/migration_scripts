<?php 


define("RUPREFIX", 'http://rucore.libraries.rutgers.edu/schemas/rulib/0.1/metadata.dtd');
$records = array();
$cointypes = array();

//first iterate through all pages of the collection to generate a list of identifiers
$setURL = 'https://rucore.libraries.rutgers.edu/api/search/query/?key=7Uf5Sx5Sh&view=identifiers&numresults=100&start=';
$start = 1;

//process the XML collection list
process_set($setURL, $start);

//generate RDF from $records array
generate_rdf($records);



/******** FUNCTIONS *********/
function process_set($setURL, $start){
	$dom = new DOMDocument('1.0', 'UTF-8');
	if ($dom->load($setURL . $start) === FALSE){
		echo "URL failed to load.\n";
	} else {
		$total = $dom->getElementsByTagName('results')->item(0)->getAttribute('total');
		foreach ($dom->getElementsByTagName('result') as $result){
			$num = $result->getAttribute('resultposition');
			$id = str_replace('rutgers-lib:', '', $result->getAttribute('recordid'));
			echo "{$num}: ";
			process_record($id);
			
			
			//echo "{$num}\n";
		}
		//if there are still records to process, call function again
		if ($num < $total){
			$start += 100;
			process_set($setURL, $start);
		}
	}
}

//process the MODS and source XML documents
function process_record($id){
	GLOBAL $records;
	GLOBAL $cointypes;
	
	$file = "https://rucore.libraries.rutgers.edu/api/get/{$id}/mods/";
	$dom = new DOMDocument('1.0', 'UTF-8');
	if ($dom->load($file) === FALSE){
		echo "{$file} failed to load.\n";
	} else {
		$row = array();
	
		//load source metadata
		$sourceFile = "https://rucore.libraries.rutgers.edu/api/get/{$id}/source/1";
		$sourceXML = new DOMDocument('1.0', 'UTF-8');
		if ($sourceXML->load($sourceFile) === FALSE){
			echo "{$sourceFile} failed to load.\n";
		} else {
			//process each node in the source XML document
			foreach ($sourceXML->getElementsByTagNameNS(RUPREFIX, '*') as $element) {
				$name = $element->nodeName;
				if ($element->getAttribute('TYPE') == 'Catalog Number'){
					$row['identifier'] = $element->nodeValue;
				} else {
					if ($name == 'rulib:dimensions'){
						//process diameter
						preg_match('/(\d+)\smm/', $element->nodeValue, $matches);
						if (isset($matches[1])){
							$row['diameter'] = $matches[1];
						}
						unset($matches);
					} elseif ($name == 'rulib:extent'){
						//process weight
						preg_match('/(.*)\sg/', $element->nodeValue, $matches);
						if (isset($matches[1]) && is_numeric($matches[1])){
							$row['weight'] = $matches[1];
						}
						unset($matches);
					}
				}
			}
		}
	
		//process MODS metadata
		$xpath = new DOMXpath($dom);
		$xpath->registerNamespace("mods", "http://www.loc.gov/mods/");
	
		$ref = $xpath->query("//mods:classification[@authority='Crawford']");
	
		if ($ref->length > 0){
			$row['id'] = $id;
			$row['uri'] = 'http://dx.doi.org/' . $xpath->query('//mods:identifier[@type="doi"]')->item(0)->nodeValue;
			$row['title'] = $xpath->query('//mods:titleInfo/mods:title')->item(0)->nodeValue;
						
			//parse JSON-LD IIIF manifest to service services
			$services = parse_manifest("https://rucore.libraries.rutgers.edu/api/iiif/presentation/2.0/rutgers-lib:{$id}/manifest");
			
			$row['obvService'] = $services['obv'];
			$row['revService'] = $services['rev'];
		
			
			$row['reference'] = $ref->item(0)->nodeValue;
			
			if (preg_match('/\d+[A-Z]?\/\d+[a-z]?$/', $ref->item(0)->nodeValue)){
				$cointype = 'http://numismatics.org/crro/id/rrc-' . str_replace('/', '.', $ref->item(0)->nodeValue);
					
				//check to see if the coin type URI has already been validated
				if (in_array($cointype, $cointypes)){
					echo "Found match {$id}: {$row['title']} - {$cointype}\n";
					$row['cointype'] = $cointype;
				} else {
					$file_headers = @get_headers($cointype);
					if ($file_headers[0] == 'HTTP/1.1 200 OK'){
						//echo "{$row['objectnumber']}: {$cointype}\n";
						echo "Matching {$id}: {$row['title']} - {$cointype}\n";
						$row['cointype'] = $cointype;
						$cointypes[] = $cointype;
					} else {
						echo "Not found {$id}: {$row['title']} - {$row['reference']}\n";
					}
				}
			} else {
				echo "No match {$id}: {$row['title']} - {$row['reference']}\n";
			}
		}
	
		$records[] = $row;
	}
}

//parse the JSON-LD IIIF manifest to dynamically extract the services and images
function parse_manifest($url){
	//Update, March 2018: read the label, look for "Obverse" and "Reverse"
	$services = array();
	
	$contents = file_get_contents($url);	
	$manifest = json_decode($contents);
	
	foreach ($manifest->sequences as $sequence){
		//if there are two canvases, the first is obverse and second is reverse
		if (count($sequence->canvases) == 2){
			echo "2 canvases: ";
			$services['obv'] = $sequence->canvases{0}->images{0}->resource->service->{'@id'};
			$services['rev'] = $sequence->canvases{1}->images{0}->resource->service->{'@id'};
		} else {
			echo "multiple canvases: ";
			foreach ($sequence->canvases as $canvas){				
				//label is within the canvas
				$label = $canvas->label;
				if (strpos($label, 'Obverse') !== FALSE){
					foreach ($canvas->images as $image){
						$services['obv'] = $image->resource->service->{'@id'};
					}
				}
				
				if (strpos($label, 'Reverse') !== FALSE){
					foreach ($canvas->images as $image){
						$services['rev'] = $image->resource->service->{'@id'};
					}
				}
			}
		}
		
	}
	
	return $services;
}

//generate Nomisma-compliant RDF
function generate_rdf($records){
	//start RDF/XML file
	//use XML writer to generate RDF
	$writer = new XMLWriter();
	$writer->openURI("rutgers.rdf");
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
			$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/rutgers');
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
						$writer->writeAttribute('rdf:resource', "{$record['obvService']}/full/,120/0/default.jpg");
					$writer->endElement();
					$writer->startElement('foaf:depiction');
						$writer->writeAttribute('rdf:resource', "{$record['obvService']}/full/600,/0/default.jpg");
					$writer->endElement();
				$writer->endElement();
			$writer->endElement();
				
			//reverse
			$writer->startElement('nmo:hasReverse');
				$writer->startElement('rdf:Description');
					$writer->startElement('foaf:thumbnail');
						$writer->writeAttribute('rdf:resource', "{$record['revService']}/full/,120/0/default.jpg");
					$writer->endElement();
					$writer->startElement('foaf:depiction');
						$writer->writeAttribute('rdf:resource', "{$record['revService']}/full/600,/0/default.jpg");
					$writer->endElement();
				$writer->endElement();
			$writer->endElement();

			//void:inDataset
			$writer->startElement('void:inDataset');
			$writer->writeAttribute('rdf:resource', 'http://coins.libraries.rutgers.edu/romancoins/');
			$writer->endElement();

			//end nmo:NumismaticObject
			$writer->endElement();
			
			//create WebResources for IIIF: obverse and reverse images specifically should reference the info.json for the image, not the entire manifest
			$writer->startElement('edm:WebResource');
				$writer->writeAttribute('rdf:about', "{$record['obvService']}/full/600,/0/default.jpg");
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
				$writer->writeAttribute('rdf:about', "{$record['revService']}/full/600,/0/default.jpg");
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

function generate_metadata(){
	//load rights metadata: all rights statements are identical
	$rightsFile = "https://rucore.libraries.rutgers.edu/api/get/50413/rights/1";
	$rightsXML = new DOMDocument('1.0', 'UTF-8');
	if ($rightsXML->load($rightsFile) === FALSE){
		echo "{$rightsFile} failed to load.\n";
	} else {
		foreach ($rightsXML->getElementsByTagNameNS(RUPREFIX, 'rightsDeclaration') as $element) {
			$rightsStmt = $element->nodeValue;
		}			
	}
	
	$writer = new XMLWriter();
	//$writer->openURI("{$collection['project_id']}.void.rdf");
	$writer->openURI('php://output');
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
		$writer->writeAttribute('rdf:about', 'http://coins.libraries.rutgers.edu/romancoins/');
		$writer->writeElement('dcterms:rights', $rightsStmt);
		
		
	//end void:Dataset
	$writer->endElement();
	//end RDF file
	$writer->endElement();
	$writer->flush();	
}

?>