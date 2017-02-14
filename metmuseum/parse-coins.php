<?php 

//load RIC 10 number, auth k=>v pairs
$data = generate_json('coins.csv');
$con = generate_json('concordances.csv');
$records = array();


foreach ($con as $item){
	$accnum = $item['accnum'];
	if (strlen($item['uri']) > 0){
		foreach ($data as $row){
			if ($accnum == $row['Object Number']){
				$record = array();
				
				$record['objectnumber'] = $accnum;
				$record['cointype'] = $item['uri'];
				$record['uri'] = $row['Link Resource'];
				$record['title'] = $row['Title'];
				
				//parse dimensions
				if (isset($row['Dimensions'])){
					preg_match('/(\d+\.?\d+?)g/', $row['Dimensions'], $weight);
					
					if (isset($weight[1])){
						if (is_numeric($weight[1])){
							$record['weight'] = $weight[1];
						}						
					}
					
					preg_match('/[A-Za-z]+\.?:.*\((\d\.?\d?)\s/', $row['Dimensions'], $diameter);
					if (isset($diameter[1])){						
						if (is_numeric($diameter[1])){
							$record['diameter'] = $diameter[1] * 10;
						}
					}
				}
				
				echo "Processing {$record['uri']}\n";
				$service = "http://www.metmuseum.org/api/collection/additionalImages?page=1&perPage=10&crdId={$row['Object ID']}";
				$json = file_get_contents($service);
				$images = json_decode($json);
				
				if (isset($images->results)){
					$count = count($images->results);	
					$num = 0;
					foreach ($images->results as $image){
						if ($count == 1){
							if (isset($image->imageUrl)){
								$record['obvThumb'] = $image->imageUrl;
							}
							if (isset($image->webImageUrl)){
								$record['obvRef'] = $image->webImageUrl;
							}
						} elseif ($count == 2){
							if ($num == 0){
								if (isset($image->imageUrl)){
									$record['obvThumb'] = $image->imageUrl;
								}
								if (isset($image->webImageUrl)){
									$record['obvRef'] = $image->webImageUrl;
								}
							} elseif ($num == 1){
								if (isset($image->imageUrl)){
									$record['revThumb'] = $image->imageUrl;
								}
								if (isset($image->webImageUrl)){
									$record['revRef'] = $image->webImageUrl;
								}
							}							
						} elseif ($count >= 3){
							//http://www.metmuseum.org/art/collection/search/248042 has three images
							echo "{$record['uri']}: {$image->imageUrl}\n";
						}
						$num++;
					}
				}
				
				$records[] = $record;
				unset($record);
			}
		}
	}
}

generate_rdf($records);

function generate_rdf($records){
	//start RDF/XML file
	//use XML writer to generate RDF
	$writer = new XMLWriter();
	$writer->openURI("metmuseum.rdf");
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
				$writer->writeElement('dcterms:identifier', $record['objectnumber']);
				$writer->startElement('nmo:hasCollection');
					$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/metmuseum');
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
						$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
						$writer->text($record['diameter']);
					$writer->endElement();
				}
	
				//conditional images
				//obverse
				if (isset($record['obvThumb']) || isset($record['obvRef'])){
					$writer->startElement('nmo:hasObverse');
						$writer->startElement('rdf:Description');
						if (isset($record['obvThumb'])){
							$writer->startElement('foaf:thumbnail');
								$writer->writeAttribute('rdf:resource', $record['obvThumb']);
							$writer->endElement();
						}
						if (isset($record['obvRef'])){
							$writer->startElement('foaf:depiction');
								$writer->writeAttribute('rdf:resource', $record['obvRef']);
							$writer->endElement();
						}
						$writer->endElement();
					$writer->endElement();
				}				
					
				//reverse
				if (isset($record['revThumb']) || isset($record['revRef'])){
					$writer->startElement('nmo:hasReverse');
						$writer->startElement('rdf:Description');
						if (isset($record['revThumb'])){
							$writer->startElement('foaf:thumbnail');
								$writer->writeAttribute('rdf:resource', $record['revThumb']);
							$writer->endElement();
						}
						if (isset($record['revRef'])){
							$writer->startElement('foaf:depiction');
								$writer->writeAttribute('rdf:resource', $record['revRef']);
							$writer->endElement();
						}
						$writer->endElement();
					$writer->endElement();
				}
	
				//void:inDataset
				$writer->startElement('void:inDataset');
					$writer->writeAttribute('rdf:resource', 'http://www.metmuseum.org/');
				$writer->endElement();
	
				//end nmo:NumismaticObject
			$writer->endElement();
		}
	}

	//end RDF file
	$writer->endElement();
	$writer->flush();
}

function parse_html($url){
	echo "Scraping {$url}\n";
	$html = file_get_contents($url);
	
	$dom = new DOMDocument();
	@$dom->loadHTML($html);
	$xpath = new DOMXpath($dom);
	
	$images = $xpath->query("descendant::img[@ng-click='addImg.selectImage(img)']");
	var_dump($images);
	foreach ($images as $image){
		var_dump($image);
		echo "test:";
		echo $image->getAttribute('src') . "\n";
	}
	
	
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