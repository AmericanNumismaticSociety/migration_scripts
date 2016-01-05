<?php 
/**** GET ALL NUMISMATIC REFERENCE WORKS FROM THE BM VIA SPARQL *****/
require_once( "sparqllib.php" );

//initiate fiel
$fp = fopen('refs.csv', 'w');

//initiate SPARQL query
$db = sparql_connect( "http://collection.britishmuseum.org/sparql" );
if( !$db ) {
	print sparql_errno() . ": " . sparql_error(). "\n"; exit;
}
sparql_ns( "bibo","http://purl.org/ontology/bibo/" );
sparql_ns( "bmo","http://collection.britishmuseum.org/id/ontology/" );
sparql_ns( "ecrm","http://erlangen-crm.org/current/" );
sparql_ns( "object","http://collection.britishmuseum.org/id/object/" );
sparql_ns( "rdfs","http://www.w3.org/2000/01/rdf-schema#" );
sparql_ns( "skos","http://www.w3.org/2004/02/skos/core#" );

$sparql = 'SELECT DISTINCT ?ref ?title ?author ?id WHERE {
  ?ref a ecrm:E31_Document ;
         skos:prefLabel ?title ;
         bibo:identifier ?id ;
         ecrm:P94i_was_created_by ?pub .
  ?pub ecrm:P2_has_type <http://collection.britishmuseum.org/id/thesauri/production/authoring> ;
       ecrm:P14_carried_out_by ?auth .
  ?auth rdfs:label ?author .
  ?coin ecrm:P70i_is_documented_in ?ref ;
        a ecrm:E22_Man-Made_Object ;
		bmo:PX_object_type <http://collection.britishmuseum.org/id/thesauri/x6089> ;
        ecrm:P50_has_current_keeper <http://collection.britishmuseum.org/id/thesauri/department/C> 
  } ORDER BY ?title';

$result = sparql_query($sparql);
if( !$result ) {
	print sparql_errno() . ": " . sparql_error(). "\n"; exit;
}

$fields = sparql_field_array($result);
while($row = sparql_fetch_array($result)){
	$line = array();
	foreach( $fields as $field ){
			$line[] = $row[$field];
	}
	fputcsv($fp, $line);
}

//close file
fclose($fp);

?>