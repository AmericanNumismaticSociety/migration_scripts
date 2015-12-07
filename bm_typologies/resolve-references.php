<?php 
require_once( "sparqllib.php" );

$data = generate_json('refs.csv', false);

//$csv = '"ref","title","id"' . "\n";

$csv = '';
$count = 1;
foreach ($data as $row){
	$textRefs = query_bm($row['uri']);
	$arrayKeys = array_keys($textRefs);
	
	$csv .= '"' . $row['uri'] . '","' . $row['title'] . '","' . $row['id'] . '"';
	foreach ($textRefs[$arrayKeys[0]] as $ref){
		$pieces = explode('::', $ref);
		$ref = trim($pieces[1]);
		
		$csv .= ',"' . $ref . '"';
	}
	$csv .= "\n";
	$count++;
	echo "{$count}: {$row['uri']}\n";
}

file_put_contents('refs-new.csv', $csv);

function query_bm($ref){
	$textRefs = array();
	
	$db = sparql_connect( "http://collection.britishmuseum.org/sparql" );
	if( !$db ) {
		print sparql_errno() . ": " . sparql_error(). "\n"; exit;
	}
	sparql_ns( "bmo","http://collection.britishmuseum.org/id/ontology/" );
	sparql_ns( "ecrm","http://erlangen-crm.org/current/" );
	sparql_ns( "object","http://collection.britishmuseum.org/id/object/" );

	$sparql = 'SELECT DISTINCT ?coin ?text WHERE {
  ?coin ecrm:P70i_is_documented_in <REF> . FILTER regex(str(?coin), "C[A-Z]{2}[0-9]+") .
  ?coin a ecrm:E22_Man-Made_Object ;
        ecrm:P50_has_current_keeper <http://collection.britishmuseum.org/id/thesauri/department/C> ;
        bmo:PX_display_wrap ?text . FILTER regex(?text, "Bibliograpic\\\sreference")
  } LIMIT 10';

	$result = sparql_query(str_replace('REF',$ref,$sparql));
	if( !$result ) {
		print sparql_errno() . ": " . sparql_error(). "\n"; exit;
	}

	$fields = sparql_field_array($result);
	while( $row = sparql_fetch_array( $result ) )
	{
		$textRefs[$row['coin']][] = $row['text'];
		/*foreach( $fields as $field )
		{
			$textRefs
		}*/
	}
	return $textRefs;
}

/***** FUNCTIONS *****/
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