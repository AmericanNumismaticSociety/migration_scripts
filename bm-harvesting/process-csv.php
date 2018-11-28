<?php 

error_reporting(0);

$data = generate_json('coins.csv');
$filename = 'bm-svoronos.rdf';

//use XML writer to generate RDF
$writer = new XMLWriter();
$writer->openURI($filename);
//$writer->openURI('php://output');
$writer->startDocument('1.0','UTF-8');
$writer->setIndent(true);
//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
$writer->setIndentString("    ");

$writer->startElement('rdf:RDF');
$writer->writeAttribute('xmlns:rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
$writer->writeAttribute('xmlns:dcterms', "http://purl.org/dc/terms/");
$writer->writeAttribute('xmlns:nm', "http://nomisma.org/id/");
$writer->writeAttribute('xmlns:nmo', "http://nomisma.org/ontology#");
$writer->writeAttribute('xmlns:foaf', "http://xmlns.com/foaf/0.1/");
$writer->writeAttribute('xmlns:rdfs', "http://www.w3.org/2000/01/rdf-schema#");
$writer->writeAttribute('xmlns:void', "http://rdfs.org/ns/void#");
$writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema#');

//iterate through each row in the spreadsheet
$count = 1;
foreach ($data as $row){
   
    
    if (strlen($row['coinType']) > 0){
        $pieces = explode('/', $row['uri']);
        $id = $pieces[5];
        echo "Processing {$count}: {$id}. ";
        
        //evaluate HTTP codes for the coin type URIs
        $file_headers = @get_headers($row['coinType']);
        if ($file_headers[0] == 'HTTP/1.1 200 OK'){
           $coinType = $row['coinType'];
           echo $coinType . "\n";           
        } elseif ($file_headers[0] == 'HTTP/1.1 303 See Other'){
            //get URI from 303 redirect
            $coinType = str_replace('Location: ', '', $file_headers[7]);            
            echo "{$row['coinType']} -> {$coinType}\n";
        } else {
            "{$row['coinType']} does not resolve.\n";
        }
        
        if (isset($coinType)){
            $collectionURL = "https://www.britishmuseum.org/research/collection_online/collection_object_details.aspx?objectId={$row['codexId']}&partId=1";
            
            $writer->startElement('nmo:NumismaticObject');
                $writer->writeAttribute('rdf:about', $row['uri']);
                $writer->startElement('dcterms:title');
                    $writer->writeAttribute('xml:lang', 'en');
                    $writer->text("British Museum: " . $row['regno']);
                $writer->endElement();
                $writer->writeElement('dcterms:identifier', $row['regno']);
                $writer->startElement('nmo:hasCollection');
                        $writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/bm');
                $writer->endElement();
                $writer->startElement('nmo:hasTypeSeriesItem');
                    $writer->writeAttribute('rdf:resource', $coinType);
                $writer->endElement();
                
                //measurements
                if (is_numeric($row['axis'])){
                    $writer->startElement('nmo:hasAxis');
                        $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
                        $writer->text($row['axis']);
                    $writer->endElement();
                }
                if (is_numeric($row['diameter'])){
                    $writer->startElement('nmo:hasDiameter');
                        $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
                        $writer->text($row['diameter']);
                    $writer->endElement();
                }
                if (is_numeric($row['weight'])){
                    $writer->startElement('nmo:hasWeight');
                        $writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
                        $writer->text($row['weight']);
                    $writer->endElement();
                }
                
                //Collection Online
                $writer->startElement('rdfs:seeAlso');
                    $writer->writeAttribute('rdf:resource', $collectionURL);
                $writer->endElement();
                
                //image
                if (strlen($row['image']) > 0){
                    $writer->startElement('foaf:depiction');
                        $writer->writeAttribute('rdf:resource', $row['image']);
                    $writer->endElement();
                } else {
                    //attempt to screen script the codexID
                    $image = process_html($collectionURL, $row['codexId']);
                    if (isset($image)){
                        //if the image is an array, then create obverse and reverse
                        if (is_array($image)){
                            //obverse
                            $writer->startElement('nmo:hasObverse');
                                $writer->startElement('rdf:Description');
                                    $writer->startElement('foaf:thumbnail');
                                        $writer->writeAttribute('rdf:resource', $image[0] . '?maxwidth=120&maxheight=120');
                                    $writer->endElement();
                                    $writer->startElement('foaf:depiction');
                                        $writer->writeAttribute('rdf:resource', $image[0]);
                                    $writer->endElement();
                                $writer->endElement();
                            $writer->endElement();
                            
                            //reverse
                            $writer->startElement('nmo:hasReverse');
                                $writer->startElement('rdf:Description');
                                    $writer->startElement('foaf:thumbnail');
                                        $writer->writeAttribute('rdf:resource', $image[1] . '?maxwidth=120&maxheight=120');
                                    $writer->endElement();
                                    $writer->startElement('foaf:depiction');
                                        $writer->writeAttribute('rdf:resource', $image[1]);
                                    $writer->endElement();
                                $writer->endElement();
                            $writer->endElement();
                        } else {
                            $writer->startElement('foaf:thumbnail');
                                $writer->writeAttribute('rdf:resource', $image . '?maxwidth=240');
                            $writer->endElement();
                            $writer->startElement('foaf:depiction');
                                $writer->writeAttribute('rdf:resource', $image);
                            $writer->endElement();
                        }
                    }
                }
                
                //hoard URI
                if (strlen($row['hoard']) > 0){
                    $writer->startElement('dcterms:isPartOf');
                        $writer->writeAttribute('rdf:resource', $row['hoard']);
                    $writer->endElement();
                }
                
                //void:inDataset
                $writer->startElement('void:inDataset');
                    $writer->writeAttribute('rdf:resource', 'http://www.britishmuseum.org/');
                $writer->endElement();
                
            //end nmo:NumismaticObject
            $writer->endElement();
        }       
        unset($coinType);
        $count++;
    }
}

$writer->endElement();
$writer->flush();


/***** FUNCTIONS *****/
//parse the HTML from the British Museum collection page in order to screen scrape an image file.
//If there is alternatively a gallery with 2 images, use the obverse and reverse instead of the primary image
function process_html($url, $codexId){
    $dom = new DomDocument();
    $dom->loadHTMLFile($url);
    $xpath = new DOMXpath($dom);
    $xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');
    
    $image = $xpath->query("//*[local-name() = 'meta'][@property='og:image']");    

    if ($image->length == 1){
        $combined = $image->item(0)->getAttribute('content');
        echo "Found primary image {$combined}\n";
        preg_match('/^http:\/\/.*AN[0]+([1-9][0-9]+)_([0-9]+)_l\.jpg.*/', $combined, $matches);
        
        if (isset($matches[1])){
            
            $assetId = $matches[1] . $matches[2];
            $assetPage = "https://www.britishmuseum.org/research/collection_online/collection_object_details/collection_image_gallery.aspx?assetId={$assetId}&objectId={$codexId}&partId=1";
            
            $dom = new DomDocument();
            $dom->loadHTMLFile($assetPage);
            $xpath = new DOMXpath($dom);
            
            $images = $xpath->query("//*[local-name() = 'ul'][@class='imageThumbnails']/descendant::*[local-name()='img']");    
             
            if ($images->length == 2){
               $obv = parse_image_url($images->item(0)->getAttribute('src'));
               $rev = parse_image_url($images->item(1)->getAttribute('src'));  
               
               echo "Found obverse and reverse images in gallery\n";
               
               //return array of obv and rev images
               return array($obv, $rev);
            } else {
                //otherwise return the combined image
                return $combined;                
            }
        }        
    }
}

//create a full URI from the relative path
function parse_image_url($src){
    return 'https://www.britishmuseum.org' . substr($src, 0, strpos($src, '?'));
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