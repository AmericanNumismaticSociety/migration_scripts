<?php 

$json = file_get_contents('provinces.json');
$data = json_decode($json);

foreach ($data->features as $feature){
    $id = str_replace(' ', '_', $feature->properties->NAME);
    $geometry = $feature->geometry;
    
    //commenting out pure GeoJSON export
    //$file = "polygons/{$id}.json";
    //file_put_contents($file, json_encode($geometry));
    
    $writer = new XMLWriter();
    $writer->openURI("rdf/{$id}.rdf");
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
    $writer->writeAttribute('xmlns:osgeo', "http://data.ordnancesurvey.co.uk/ontology/geometry/");
    
    //create SpatialThing
    $writer->startElement('geo:SpatialThing');
        $writer->writeAttribute('rdf:about', '#this');
        $writer->writeElement('osgeo:asGeoJSON', json_encode($geometry));
    $writer->endElement();
    
    //end RDF file
    $writer->endElement();
    $writer->flush();
}

?>