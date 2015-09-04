<?php

require_once( "sparqllib.php" );
error_reporting(0);

$data = generate_json('bm-concordances.csv', false);

$open = '<rdf:RDF xmlns:xsd="http://www.w3.org/2001/XMLSchema#" xmlns:nm="http://nomisma.org/id/"
         xmlns:dcterms="http://purl.org/dc/terms/" xmlns:foaf="http://xmlns.com/foaf/0.1/" 
         xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:nmo="http://nomisma.org/ontology#" 
         xmlns:void="http://rdfs.org/ns/void#">';

file_put_contents('bm-price.rdf', $open);

$count = 1;
foreach ($data as $row){
	$pieces = explode('/', $row['uri']);
	$id = $pieces[5];
	echo "Processing {$count}: {$id}\n";	
	
	$rdf = '';
	if (strlen($row['coinType']) > 0){
		$rdf .= '<nmo:NumismaticObject rdf:about="' . $row['uri'] . '">';
		$rdf .= '<dcterms:title xml:lang="en">British Museum: ' . $row['regno'] . '</dcterms:title>';
		$rdf .= '<dcterms:identifier>' . $row['regno'] . '</dcterms:identifier>';
		$rdf .= '<dcterms:publisher rdf:resource="http://nomisma.org/id/bm"/>';
		$rdf .= '<nmo:hasCollection rdf:resource="http://nomisma.org/id/bm"/>';
		$rdf .= '<nmo:hasTypeSeriesItem rdf:resource="' . $row['coinType'] . '"/>';
		$rdf .= query_bm($id);
		$rdf .= '<void:inDataset rdf:resource="http://www.britishmuseum.org/"/>';
		$rdf .= '</nmo:NumismaticObject>';
		
		file_put_contents('bm-price.rdf', $rdf, FILE_APPEND);
	}
	$count++;
	//echo $rdf;
}

file_put_contents('bm-price.rdf', '</rdf:RDF>', FILE_APPEND);

function query_bm($id){
	$db = sparql_connect( "http://collection.britishmuseum.org/sparql" );
	if( !$db ) {
		print sparql_errno() . ": " . sparql_error(). "\n"; exit;
	}
	sparql_ns( "thesDimension","http://collection.britishmuseum.org/id/thesauri/dimension/" );
	sparql_ns( "bmo","http://collection.britishmuseum.org/id/ontology/" );
	sparql_ns( "ecrm","http://erlangen-crm.org/current/" );
	sparql_ns( "object","http://collection.britishmuseum.org/id/object/" );
	
	$sparql = "SELECT ?image ?weight ?axis ?diameter ?objectId WHERE {
  OPTIONAL {object:OBJECT bmo:PX_has_main_representation ?image }
  OPTIONAL { object:OBJECT ecrm:P43_has_dimension ?wDim .
           ?wDim ecrm:P2_has_type thesDimension:weight .
           ?wDim ecrm:P90_has_value ?weight}
  OPTIONAL {
     object:OBJECT ecrm:P43_has_dimension ?wAxis .
           ?wAxis ecrm:P2_has_type thesDimension:die-axis .
           ?wAxis ecrm:P90_has_value ?axis
    }
  OPTIONAL {
     object:OBJECT ecrm:P43_has_dimension ?wDiameter .
           ?wDiameter ecrm:P2_has_type thesDimension:diameter .
           ?wDiameter ecrm:P90_has_value ?diameter
    }
  OPTIONAL {
     object:OBJECT ecrm:P1_is_identified_by ?identifier.
     ?identifier ecrm:P2_has_type <http://collection.britishmuseum.org/id/thesauri/identifier/codexid> ;
        rdfs:label ?objectId
    }
  }";
	
	$result = sparql_query(str_replace('OBJECT',$id,$sparql));
	if( !$result ) {
		print sparql_errno() . ": " . sparql_error(). "\n"; exit;
	}
	
	$xml = '';
	$fields = sparql_field_array($result);
	while( $row = sparql_fetch_array( $result ) )
	{
		foreach( $fields as $field )
		{
			if (strlen($row[$field]) > 0) {
				switch ($field) {
					case 'image':
						$xml .= '<foaf:depiction rdf:resource="' . $row[$field] . '"/>';
						break;
					case 'objectId':
						$xml .= '<foaf:homepage rdf:resource="http://www.britishmuseum.org/research/collection_online/collection_object_details.aspx?objectId=' . $row[$field] . '&amp;partId=1"/>';
						break;
					case 'axis':
						$xml .= '<nmo:hasAxis rdf:datatype="http://www.w3.org/2001/XMLSchema#integer">' . (int)$row[$field] . '</nmo:hasAxis>';
						break;
					case 'weight':
						$xml .= '<nmo:hasWeight rdf:datatype="http://www.w3.org/2001/XMLSchema#decimal">' . $row[$field] . '</nmo:hasWeight>';
						break;
					case 'diameter':
						$xml .= '<nmo:hasDiameter rdf:datatype="http://www.w3.org/2001/XMLSchema#decimal">' . $row[$field] . '</nmo:hasDiameter>';
						break;
				}
			}
		}
	}
	return $xml;
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
