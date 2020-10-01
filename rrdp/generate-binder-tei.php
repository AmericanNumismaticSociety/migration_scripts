<?php 
/*****
 * Author: Ethan Gruber
 * Date: October 2020
 * Function: Process a list of JPG files from an imagemagick identify of a folder for the Roman Republican Die Project.
 * Parse the filenames into 14 binders, reordered by page, and insert subjects for CRRO URIs
 *****/

$filename = "identify-binders.list";
$coinTypes = array();

$json = json_decode(file_get_contents("page-crro-concordance.json"), true);
$concordance = generate_json('concordance.csv');

//parse filename listing and pixel dimensions into a data object for processing into TEI
$object = parse_files($filename);

//var_dump($object);
generate_tei($object);


/***** FUNCTIONS *****/
function generate_tei($object){
    foreach ($object as $no=>$binder){
        echo "Processing {$no}\n";
        $id = "schaefer.rrdp.{$no}";
        
        $doc = new XMLWriter();
        
        //$doc->openUri('php://output');
        $doc->openUri("tei/{$id}.xml");
        $doc->setIndent(true);
        //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
        $doc->setIndentString("    ");
        
        $doc->startDocument('1.0','UTF-8');
        
        //start TEI file
        $doc->startElement('TEI');
            $doc->writeAttribute('xmlns', 'http://www.tei-c.org/ns/1.0');
            $doc->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $doc->writeAttribute('xsi:schemaLocation', 'http://www.tei-c.org/ns/1.0 http://www.tei-c.org/release/xml/tei/custom/schema/xsd/tei_all.xsd');
            $doc->writeAttribute('xml:id', $id);
            
            //TEI Header
            $doc->startElement('teiHeader');
                $doc->startElement('fileDesc');
                    $doc->startElement('titleStmt');
                        $doc->writeElement('title', 'Schaefer Roman Republican Die Study: Binder ' . ltrim(str_replace('b', '', $no), '0'));
                        $doc->startElement('author');
                            $doc->startElement('persName');
                                $doc->writeAttribute('ref', 'http://numismatics.org/authority/schaefer_richard');
                                $doc->text('Schaefer, Richard, 1946-');
                            $doc->endElement();
                        $doc->endElement();
                    $doc->endElement();
                    
                    //these items probably shouldn't have a publisher
                    $doc->startElement('publicationStmt');
                        $doc->startElement('publisher');
                            $doc->writeElement('name', 'American Numismatic Society');
                            $doc->startElement('idno');
                                $doc->writeAttribute('type', 'URI');
                                $doc->text('http://numismatics.org/authority/american_numismatic_society');
                            $doc->endElement();
                        $doc->endElement();
                        $doc->writeElement('pubPlace', 'New York (N.Y.)');
                    $doc->endElement();
                    
                    //sourceDesc
                    $doc->startElement('sourceDesc');
                        $doc->startElement('biblStruct');
                            $doc->startElement('monogr');
                                $doc->writeElement('title', 'Schaefer Roman Republican Die Study: Binder' . ltrim(str_replace('b', '', $no), '0'));
                                $doc->startElement('author');
                                    $doc->startElement('persName');
                                        $doc->writeAttribute('ref', 'http://numismatics.org/authority/schaefer_richard');
                                        $doc->text('Schaefer, Richard, 1946-');
                                    $doc->endElement();
                                $doc->endElement();
                                $doc->startElement('imprint');
                                    $doc->writeElement('date', 'unknown');
                                $doc->endElement();
                            $doc->endElement();
                        $doc->endElement();
                        //add extent later
                    $doc->endElement();
                    
                //end fileDesc    
                $doc->endElement();
                    
                //profileDesc (general document metadata)
                $doc->startElement('profileDesc');
                    $doc->startElement('langUsage');
                        $doc->startElement('language');
                            $doc->writeAttribute('ident', 'en');
                            $doc->text('English');
                        $doc->endElement();
                    $doc->endElement();
                    
                    $doc->startElement('textClass');
                        $doc->startElement('classCode');
                            $doc->writeAttribute('scheme', 'http://vocab.getty.edu/aat/');
                            $doc->text('300264354');
                        $doc->endElement();
                    
                        //subjects
                        $doc->startElement('keywords');
                            $doc->writeAttribute('scheme', 'nmo:TypeSeriesItem');
                            
                            foreach ($binder as $page){
                                
                                //if there are URIs in the 'ref'
                                if (count($page['ref']) > 0){
                                    foreach ($page['ref'] as $uri){
                                        $type = parse_type($uri);
                                        if (isset($type)){
                                            $facs = '#' . str_replace('.jpg', '', $page['filename']);
                                            
                                            $doc->startElement('term');
                                                $doc->writeAttribute('ref', $type['uri']);
                                                $doc->writeAttribute('facs', $facs);
                                                $doc->text($type['label']);
                                            $doc->endElement();
                                        }      
                                    }
                                }                         
                            }
                            
                        $doc->endElement();
                    $doc->endElement();
                //end profileDesc
                $doc->endElement();
                
                $doc->startElement('revisionDesc');
                    $doc->startElement('change');
                        $doc->writeAttribute('when', substr(date(DATE_W3C), 0, 10));
                        $doc->text('Generated TEI document by processing list of files generated by ImageMagick identify library, after sequential image renaming.');
                    $doc->endElement();
                $doc->endElement();
                    
                
            //end TEI header
            $doc->endElement();
            
        
            //facsimiles
            $num = 0;
            foreach ($binder as $page){
                $filename = $page['filename'];
                $doc->startElement('facsimile');
                    $doc->writeAttribute('xml:id', str_replace('.jpg', '', $filename));                    
                    
                    //designate first file as depiction
                    if ($num == 0){
                        $doc->writeAttribute('style', 'depiction');
                    }
                    
                    $doc->startElement('media');
                        $doc->writeAttribute('url', "http://images.numismatics.org/archivesimages%2Farchive%2F{$filename}");
                        $doc->writeAttribute('n', ltrim(str_replace('p', '', $page['page']), '0'));                        
                        $doc->writeAttribute('mimeType', 'image/jpeg');
                        $doc->writeAttribute('type', 'IIIFService');
                        $doc->writeAttribute('height', "{$page['height']}px");
                        $doc->writeAttribute('width', "{$page['width']}px");
                    $doc->endElement();
                $doc->endElement();
                
                $num++;
            }
            
        //end TEI file
        $doc->endElement();
        
        //close file
        $doc->endDocument();
        $doc->flush();
    }
}

//parse the ref to ensure it is a valid CRRO URI
function parse_type($uri){
    GLOBAL $coinTypes;
    
    if (array_key_exists($uri, $coinTypes)){
        //echo "Matched {$uri}\n";
        
        return array('label'=>$coinTypes[$uri]['label'], 'uri'=>$uri);
    } else {
        $file_headers = @get_headers($uri);
        if ($file_headers[0] == 'HTTP/1.1 200 OK'){
            //echo "Found {$uri}\n";
            $xmlDoc = new DOMDocument();
            $xmlDoc->load($uri . '.rdf');
            $xpath = new DOMXpath($xmlDoc);
            $xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
            $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
            $label = $xpath->query("descendant::skos:prefLabel[@xml:lang='en']")->item(0)->nodeValue;
            
            if (!isset($label)){
                echo "Error with {$id}\n";
            } else {
                $coinTypes[$uri] = array('label'=>$label, 'uri'=>$uri);
                return array('label'=>$label, 'uri'=>$uri);
            }
        }
    }
}

function parse_files($filename){
    GLOBAL $json;
    GLOBAL $concordance;
    
    $object = array();
    
    $handle = fopen($filename, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            preg_match('/(.*\.jpg).*\sJPEG\s(\d+)x(\d+)/', $line, $matches);
            
            $image = $matches[1];
            $width = $matches[2];
            $height = $matches[3];
            
            $id = str_replace('.jpg', '', $image);
            
            //parse image
            //echo $image . "\n";
            
            //parse binder ID from filename
            $binder = explode('_', str_replace('schaefer.rrdp.', '', $image))[0];
            
            //parse the old filename from the concordance list and parse out the page number
            $old_id = null;
            foreach ($concordance as $row){
                if ($row['new_file'] == $id){
                    $old_id = $row['old_file'];
                    $pageNumber = explode('_', $old_id)[3];
                    break;
                }
            }
            
            //get the array of associated CRRO URIs from concordance JSON
            if (isset($old_id)){
                if (array_key_exists($old_id, $json)){
                    $ref = $json[$old_id];
                } else {
                    $ref = array();
                }
            }
            
            
            //insert page metadata into each object
            $page = array('filename'=>$image, 'ref'=>$ref, 'page'=>$pageNumber, 'height'=>$height, 'width'=>$width);
            $object[$binder][$id] = $page;
            
            unset($pageNumber);
            unset($old_id);
        }
        fclose($handle);
    }
    
    //sort array in the order of binder numbers
    ksort($object);
    
    //then sort each binder by page number (as a string)
    foreach ($object as $k=>$array){
        ksort($array);
        $object[$k] = $array;
    }
    
    return $object;
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