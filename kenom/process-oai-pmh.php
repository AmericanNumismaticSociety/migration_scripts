<?php 

/*****
 * Author: Ethan Gruber
 * Date May 1, 2017
 * Function: Process KENOM OAI-PMH feed with the LIDO metadataPrefix in order to generate Nomisma RDF for Roman coins
 *****/

//libxml timeout
$options = ['http' => ['method' => 'GET','timeout' => '10']];
$context = stream_context_create($options);
libxml_set_streams_context($context);

$records = array();

$url = 'http://www.kenom.de/oai/?verb=ListRecords&metadataPrefix=lido&set=relation:nomisma.org:true';

//generate RDF
$writer = new XMLWriter();
$writer->openURI('kenom.rdf');
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

	read_oai($url, $writer);

$writer->endElement();
$writer->flush();




/****** FUNCTIONS ******/
function read_oai($url, $writer){
	$doc = new DOMDocument();
	if ($doc->load($url) === FALSE){
		return "FAIL";
	} else {
		echo "Processing {$url}\n";		
		$xpath = new DOMXpath($doc);
		$xpath->registerNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
		$xpath->registerNamespace("lido", "http://www.lido-schema.org");
		
		$service = $doc->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'request')->item(0)->nodeValue;		
		$oaiRecords = $doc->getElementsByTagNameNS('http://www.lido-schema.org', 'lido');
		
		foreach ($oaiRecords as $node){
			$coinURIs = $xpath->query("lido:objectPublishedID[@lido:type='http://terminology.lido-schema.org/identifier_type/uri']", $node);
			
			foreach ($coinURIs as $uri){
				if (strpos($uri->nodeValue, 'handle.net') !== FALSE){
					$coinURI= $uri->nodeValue;
				}
			}
			
			$refNodes = $xpath->query("descendant::lido:relatedWorkSet[lido:relatedWork/lido:object/lido:objectNote='Literatur']/lido:relatedWork/lido:object/lido:objectWebResource", $node);
			foreach ($refNodes as $reference){
				if (strpos($reference->nodeValue, 'numismatics.org') !== FALSE){
					$typeURI = $reference->nodeValue;
				}
			}
			
			//if the $uri is parseable as handle.net, continue with the rest
			if (strlen($coinURI) > 0 && isset($typeURI)){	
				echo "Match {$coinURI}: {$typeURI}\n";
				$title = $xpath->query("descendant::lido:titleSet/lido:appellationValue", $node)->item(0)->nodeValue;
				$identifier = $xpath->query("descendant::lido:recordID[@lido:type='http://terminology.lido-schema.org/identifier_type/local_identifier']", $node)->item(0)->nodeValue;
				$collection = $xpath->query("descendant::lido:repositoryName/lido:legalBodyID", $node)->item(0)->nodeValue;
				
				//replace zdb URI with Nomisma URI
				switch ($collection){
					case 'http://ld.zdb-services.de/resource/organisations/DE-MUS-062622':
						$nomisma_collection = 'http://nomisma.org/id/mk_goettingen';
						break;
					case 'http://ld.zdb-services.de/resource/organisations/DE-MUS-099114':
						$nomisma_collection = 'http://nomisma.org/id/mk_munich';
						break;
					case 'http://ld.zdb-services.de/resource/organisations/DE-MUS-878719':
						$nomisma_collection = 'http://nomisma.org/id/thuringia_museum';
						break;
					case 'http://ld.zdb-services.de/resource/organisations/DE-MUS-805518':
						$nomisma_collection = 'http://nomisma.org/id/kunstmuseum_moritzburg';
						break;
					case 'http://ld.zdb-services.de/resource/organisations/DE-MUS-109513':
						$nomisma_collection = 'http://nomisma.org/id/stadtmuseum_oldenburg';
						break;						
				}
				
				$writer->startElement('nmo:NumismaticObject');
					$writer->writeAttribute('rdf:about', $coinURI);
					$writer->startElement('dcterms:title');
						$writer->writeAttribute('xml:lang', 'de');
						$writer->text($title);
					$writer->endElement();
					$writer->writeElement('dcterms:identifier', $identifier);
					$writer->startElement('nmo:hasCollection');
						$writer->writeAttribute('rdf:resource', $nomisma_collection);
					$writer->endElement();
					$writer->startElement('nmo:hasTypeSeriesItem');
						$writer->writeAttribute('rdf:resource', $typeURI);
					$writer->endElement();
				
					//measurements
					$measurements = $xpath->query("descendant::lido:measurementsSet", $node);
					foreach($measurements as $measurement){
						$type = $measurement->getElementsByTagNameNS('http://www.lido-schema.org', 'measurementType')->item(0)->nodeValue;
						$value = str_replace(',', '.', $measurement->getElementsByTagNameNS('http://www.lido-schema.org', 'measurementValue')->item(0)->nodeValue);
						
						switch ($type){
							case 'diameter':
								if (is_numeric($value)){
									$writer->startElement('nmo:hasDiameter');
										$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
										$writer->text($value);
									$writer->endElement();
								}
								break;
							case 'weight':
								if (is_numeric($value)){
									$writer->startElement('nmo:hasWeight');
										$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
										$writer->text($value);
									$writer->endElement();
								}
								break;
							case 'orientation':
								if (is_int((int)$value)){
									$writer->startElement('nmo:hasAxis');
										$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
										$writer->text($value);
									$writer->endElement();
								}
								break;
						}
					}
					//images
					$webResources = array();
					$images = $xpath->query("descendant::lido:resourceSet/lido:resourceRepresentation[@lido:type='http://terminology.lido-schema.org/resourceRepresentation_type/preview_representation']/lido:linkResource", $node);
					foreach ($images as $image){
						$webResources[] = $image->nodeValue;
					}
					
					$pos = 0;
					foreach ($webResources as $link){
						if ($pos == 0){
							$prop = 'nmo:hasObverse';						
						} elseif ($pos == 1){
							$prop = 'nmo:hasReverse';
						}
						
						$writer->startElement($prop);
							$writer->startElement('rdf:Description');
								$writer->startElement('foaf:thumbnail');
									$writer->writeAttribute('rdf:resource', str_replace('300,', '120,', $link));
								$writer->endElement();
								$writer->startElement('foaf:depiction');
									$writer->writeAttribute('rdf:resource', str_replace('300,', '600,', $link));
								$writer->endElement();
							$writer->endElement();
						$writer->endElement();
						
						$pos++;
					}
					unset($pos);
					
					//void:inDataset
					$writer->startElement('void:inDataset');
						$writer->writeAttribute('rdf:resource', 'http://www.kenom.de/');
					$writer->endElement();
				
				//end nmo:NumismaticObject
				$writer->endElement();
				
				//Web Resources and Services
				foreach ($webResources as $link){
					$iiif_service = str_replace('/full/300,/0/default.jpg', '', $link);
					
					$writer->startElement('edm:WebResource');
						$writer->writeAttribute('rdf:about', str_replace('300,', '600,', $link));
						$writer->startElement('svcs:has_service');
							$writer->writeAttribute('rdf:resource', $iiif_service);
						$writer->endElement();
						$writer->startElement('dcterms:isReferencedBy');
							$writer->writeAttribute('rdf:resource', $iiif_service. '/info.json');
						$writer->endElement();
					$writer->endElement();
					
					$writer->startElement('svcs:Service');
					$writer->writeAttribute('rdf:about', $iiif_service);
						$writer->startElement('dcterms:conformsTo');
							$writer->writeAttribute('rdf:resource', 'http://iiif.io/api/image');
						$writer->EndElement();
						$writer->startElement('doap:implements');
							$writer->writeAttribute('rdf:resource', 'http://iiif.io/api/image/2/level1.json');
						$writer->endElement();
					$writer->endElement();
				}
				
				unset($webResources);	
			}
		}
		
		//look for resumptionToken
		$token = $xpath->query("descendant::oai:resumptionToken");
		
		if ($token->length > 0){
			echo "Checked " . $token->item(0)->getAttribute('cursor') . " of " . $token->item(0)->getAttribute('completeListSize') . ".\n";
			$url = $service . '?verb=ListRecords&resumptionToken=' . $token->item(0)->nodeValue;
			//echo "{$url}\n";
			read_oai($url, $writer);
		}
	}
}

?>