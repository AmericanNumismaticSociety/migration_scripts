<?php 
$collection = 'leeds';
$data = generate_json("{$collection}.csv");
//store successful hits
$coinTypes = array();

//generate an array of records for outputting
$records = array();

$project = 'pella';

$count = 1;
foreach ($data as $row){
	echo "Processing #{$count}: {$row['uri']}\n";
	$pieces = explode('/', $row['uri']);
	
	$record = array();
	
	$record['uri'] = $row['uri'];
	$record['title'] = $row['title'];
	
	if ($collection == 'ashmolean'){
	    $record['objectnumber'] = $pieces[4];
	} elseif ($collection == 'leeds'){
	    $record['objectnumber'] = $row['IRN'];
	}
	
	//$record['reference'] = $row['Price number'];
	
	if (is_numeric($row['die_axis'])){
		$record['axis'] = $row['die_axis'];
	}
	if (is_numeric($row['diameter'])){
		$record['diameter'] = $row['diameter'];
	}
	if (is_numeric($row['weight'])){
		$record['weight'] = $row['weight'];
	}	
	if (strlen($row['hoard_uri']) > 0){
		$record['hoard'] = $row['hoard_uri'];
	}
	
	//images	
	$record['obv_image'] = $row['uri_obverse_image'];
	$record['rev_image'] = $row['uri_reverse_image'];
	
	//new spreadsheet contains PELLA URI
	if (strlen($row['pella_uri']) > 0){
		$record['cointype'] = $row['pella_uri'];
	}
	
	//validate Price number
	/*$uri = "http://numismatics.org/pella/id/price.{$row['Price number']}";	
	$typeValid = check_uri($uri);
	if ($typeValid == true){
		$record['cointype'] = $uri;
	}*/
	
	$records[] = $record;
	$count++;
}

//after the CSV has been parsed, then process the resulting records array into Nomisma-conformant RDF
//generate_csv($records, $project);
generate_rdf($records, $project, $collection);

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

function generate_rdf($records, $project, $collection){
	//start RDF/XML file
	//use XML writer to generate RDF
	$writer = new XMLWriter();
	$writer->openURI("{$collection}-{$project}.rdf");
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
			$writer->startElement('nmo:hasCollection');
			if ($collection == 'ashmolean'){
			    $writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/ashmolean');
			} elseif ($collection == 'leeds'){
			    $writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/leeds_university_library');
			}				
			$writer->endElement();
			$writer->startElement('nmo:hasTypeSeriesItem');
				$writer->writeAttribute('rdf:resource', $record['cointype']);
			$writer->endElement();
			
			if (isset($record['hoard'])){
				$writer->startElement('dcterms:isPartOf');
					$writer->writeAttribute('rdf:resource', $record['hoard']);
				$writer->endElement();
			}

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
    					if ($collection == 'ashmolean'){
    					    $writer->startElement('foaf:thumbnail');
    					       $writer->writeAttribute('rdf:resource', str_replace('of', 'ot', $record['obv_image']));
    					    $writer->endElement();
    					}
						$writer->startElement('foaf:depiction');
							$writer->writeAttribute('rdf:resource', $record['obv_image']);
						$writer->endElement();
					$writer->endElement();
				$writer->endElement();
			}
			if (isset($record['rev_image'])){
				$writer->startElement('nmo:hasReverse');
					$writer->startElement('rdf:Description');
    					if ($collection == 'ashmolean'){
    						$writer->startElement('foaf:thumbnail');
    							$writer->writeAttribute('rdf:resource', str_replace('rf', 'rt', $record['rev_image']));
    						$writer->endElement();
    					}
						$writer->startElement('foaf:depiction');
							$writer->writeAttribute('rdf:resource', $record['rev_image']);
						$writer->endElement();
					$writer->endElement();
				$writer->endElement();
			}

			//void:inDataset
			$writer->startElement('void:inDataset');
			if ($collection == 'ashmolean'){
			    $writer->writeAttribute('rdf:resource', 'http://hcr.ashmus.ox.ac.uk/');
			} elseif ($collection == 'leeds'){
			    $writer->writeAttribute('rdf:resource', 'https://library.leeds.ac.uk/special-collections/collection/1491');
			}				
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