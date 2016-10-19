<?php 

/*****
 * Author: Ethan Gruber
 * Date: October 2016
 * Function: This script uses a combination of the Cambridge University Fitzwilliam Museum API (to query for a list of responses) and
 * screen scraping to parse coin type reference numbers and measurements in order to generate Nomisma-compliant RDF
 *****/

//set user agent
ini_set('user_agent', 'Nomisma.org/PHP Harvesting');

//array of queries
$queries = array('crro'=>'coin AND "Roman Republic"','pella'=>'"Alexander III" AND coin');
$project = 'pella';

$api = 'http://data.fitzmuseum.cam.ac.uk/api/?';
$params = array('query'=>$queries[$project],'size'=>100,'from'=>0);
$records = array();
$coinTypes = array();

parse_response($api, $params);

//var_dump($records);

//after the API pages have been parsed, then process the resulting records array into Nomisma-conformant RDF
generate_csv($records, $project);
generate_rdf($records, $project);

function parse_response($api, $params){
	GLOBAL $records;
	
	$page = $params['from'] / $params['size'] + 1;
	
	echo "Page {$page}\n";
	
	//compare the individual request parameters into k=>v pairs to the URL
	$qparams = array();
	foreach ($params as $k=>$v){
		$qparams[] = "{$k}=" . urlencode($v);
	}
	
	$service = $api . implode('&', $qparams);
	
	//request JSON from API
	$json = file_get_contents($service);
	$data = json_decode($json);
	
	//iterate through results
	foreach ($data->results as $result){
		$record = array();
		
		$record['id'] = $result->priref;
		$record['uri'] = "http://data.fitzmuseum.cam.ac.uk/id/object/{$result->priref}";
		$record['objectnumber'] = $result->ObjectNumber;
		$record['title'] = "Fitzwilliam Museum - Object {$result->ObjectNumber}";
		
		$imageCount = 0;
		foreach ($result->images->thumbnailURI as $url){
			switch ($imageCount){
				case 0:
					$record['obv_image'] = $url;
					break;
				case 1:
					$record['rev_image'] = $url;
			}
			$imageCount++;
		}
		
		//begin screen scraping
		$url = "http://webapps.fitzmuseum.cam.ac.uk/explorer/index.php?oid={$result->priref}";
		$fields = parse_html($url);
		
		if (isset($fields['reference'])){
			$record['reference'] = $fields['reference'];
		}
		
		if (isset($fields['coinType'])){
			$record['cointype'] = $fields['coinType'];
		}
		
		if (isset($fields['weight'])){
			$record['weight'] = $fields['weight'];
		}
		
		if (isset($fields['axis'])){
			$record['axis'] = $fields['axis'];
		}
		
		$records[] = $record;
	}
	
	//var_dump($records);
	
	//if there are more pages to parse, curse function
	$numFound = $data->total;
	if (($params['from'] + $params['size']) <= $numFound){
		$params['from'] = $params['from'] + $params['size'];
		
		parse_response($api, $params);
	}
}

function parse_html($url){
	echo "Scraping {$url}\n";
	
	//create an array of items to be parsed from HTML
	$fields = array();
	
	$html = file_get_contents($url);
	
	$dom = new DOMDocument();
	@$dom->loadHTML($html);
	$xpath = new DOMXpath($dom);
	
	//find the reference numbers
	$refNode = $xpath->query("//p[@class='ttag'][contains(text(), 'Alternative')]/following-sibling::p[1]");
	
	if ($refNode->length > 0){
		//parse as text and clean to generate an array of reference numbers
		$refText = $dom->saveHTML($refNode->item(0));
		$refText = str_replace('<p class="vtag">', '', str_replace('</p>', '', $refText));
		
		$refs = explode('<br>', trim($refText));
		
		foreach ($refs as $ref){
			$ref = trim($ref);
			if (strpos($ref, 'RRC') !== FALSE || strpos($ref, 'Crawford') !== FALSE){
				echo "Parsing {$ref}\n";
				$fields['reference'] = $ref;
				$pieces = explode(' ', $ref);
				$num = str_replace('/', '.', $pieces[1]);
					
				$uri = "http://numismatics.org/crro/id/rrc-{$num}";
					
				$typeValid = check_uri($uri);
				if ($typeValid == true){
					$fields['coinType'] = $uri;
				}
			} else if (strpos($ref, 'price') !== FALSE){
				echo "Parsing {$ref}\n";
				$fields['reference'] = $ref;
				$pieces = explode(' ', $ref);
				$num = $pieces[1];
				
				$uri = "http://numismatics.org/pella/id/price.{$num}";
				
				$typeValid = check_uri($uri);
				if ($typeValid == true){
					$fields['coinType'] = $uri;
				}
			}
		}
	} else {
		echo "No references.\n";
	}
	
	//parse measurements
	$measurementsNode = $xpath->query("//p[@class='ttag'][contains(text(), 'Dimension')]/following-sibling::p[1]");
	if ($measurementsNode->length > 0){
		$text = $dom->saveHTML($measurementsNode->item(0));
		$text = str_replace('<p class="vtag">', '', str_replace('</p>', '', $text));
		
		$measurements = explode('<br>', trim($text));
		
		foreach ($measurements as $measurement){
			if (strpos($measurement, 'weight') !== FALSE){
				preg_match('/(\d+\.\d+)/', $measurement, $matches);
				
				if (is_numeric($matches[1])){
					$fields['weight'] = (float)$matches[1];
				}
			} elseif (strpos($measurement, 'axis') !== FALSE){
				preg_match('/(d+)/', $measurement, $matches);
				
				$axis360 = $matches[1];
				
				//round the 360 degree axis to the nearest clock integer
				$axis = round(($axis360 / 360) * 12);
				
				//if axis is between 0 and 12, reset 0 to 12 and return the value
				if ($axis <= 12 && $axis >= 0){
					if ($axis == 0){
						$axis = 12;
					}
					
					$fields['axis'] = $axis;
				}
			}
		}
		//var_dump($measurements);
	}
	
	
	return $fields;
}

function check_uri($uri){
	GLOBAL $coinTypes;
	
	//if the URI is in the array
	if (array_key_exists($uri, $coinTypes)){
		if ($coinTypes[$uri] == true) {
			echo "Found {$uri}\n";
			$valid = true;
		} else {
			echo "Did not find {$uri}\n";
			$valid = false;
		}
	} else {
		$file_headers = @get_headers($uri);
		if ($file_headers[0] == 'HTTP/1.1 200 OK'){
			echo "Matched new {$uri}\n";
			$coinTypes[$uri] = true;
			$valid = true;
		} else {
			echo "Did not find {$uri}\n";
			$coinTypes[$uri] = false;			
			$valid = false;
		}
	}
	
	return $valid;
}

function generate_rdf($records, $project){
	//start RDF/XML file
	//use XML writer to generate RDF
	$writer = new XMLWriter();
	$writer->openURI("fitzwilliam-{$project}.rdf");
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

	foreach ($records as $record){
		if (isset($record['cointype'])){
			$writer->startElement('nmo:NumismaticObject');
				$writer->writeAttribute('rdf:about', $record['uri']);
				$writer->startElement('dcterms:title');
					$writer->writeAttribute('xml:lang', 'en');
					$writer->text($record['title']);
				$writer->endElement();
				$writer->writeElement('dcterms:identifier', $record['objectnumber']);
				$writer->startElement('dcterms:publisher');
					$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/fitzwilliam');
				$writer->endElement();
				$writer->startElement('nmo:hasCollection');
					$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/fitzwilliam');
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
			if (isset($record['axis'])){
				$writer->startElement('nmo:hasAxis');
					$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
					$writer->text($record['axis']);
				$writer->endElement();
			}

			//conditional images
			if (isset($record['obv_image'])){
				$writer->startElement('nmo:hasObverse');
					$writer->startElement('rdf:Description');
					$writer->startElement('foaf:depiction');
						$writer->writeAttribute('rdf:resource', $record['obv_image']);
					$writer->endElement();
					$writer->endElement();
				$writer->endElement();
			}
			if (isset($record['rev_image'])){
				$writer->startElement('nmo:hasReverse');
					$writer->startElement('rdf:Description');
						$writer->startElement('foaf:depiction');
							$writer->writeAttribute('rdf:resource', $record['rev_image']);
						$writer->endElement();
					$writer->endElement();
				$writer->endElement();
			}

				//void:inDataset
				$writer->startElement('void:inDataset');
					$writer->writeAttribute('rdf:resource', 'http://www.fitzmuseum.cam.ac.uk/');
				$writer->endElement();

			//end nmo:NumismaticObject
			$writer->endElement();
		}
	}

	//end RDF file
	$writer->endElement();
	$writer->flush();
}

//generate csv
function generate_csv($records, $project){
	$csv = '"objectnumber","title","uri","reference","type"' . "\n";

	foreach ($records as $record){
		$csv .= '"' . $record['objectnumber'] . '","' . $record['title'] . '","' . $record['uri'] . '","' . (isset($record['reference']) ? $record['reference'] : '') . '","' . (isset($record['cointype']) ? $record['cointype'] : '') . '"' . "\n";
	}

	file_put_contents("concordances-{$project}.csv", $csv);
}

?>