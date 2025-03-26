<?php 

/*****
 * Author: Ethan Gruber
 * Date: February 2024
 * Function: Read the RRDP specimen spreadsheet and perform Solr queries of the simpleAnnotationStore endpoint in order to join 
 * coin metadata with IIIF URIs for obverse and reverse images. This script performs data validation and will report errors via email.
 * It also PUTs the XML data to eXist-db and triggers Solr indexing in Numishare. It is intended to run in a cron job to facilitate regular updates without
 * intermediary intervention.
 *****/

ini_set("allow_url_fopen", 1);

//constants
define("ANNOTATION_SOLR_URL", "http://numismatics.org:8983/solr/annotations/select");
define("NUMISHARE_SOLR_URL", "http://localhost:8983/solr/numishare/update");
define("INDEX_COUNT", 500);
define("COLLECTION_NAME", 'sitnam');

//an array of sheets for batches of RRC numbers
$sheets = array('https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=0&single=true&output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=1351881890&single=true&output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=1880415193&single=true&output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=949141746&single=true&output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=1239303383&single=true&output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=1931963948&single=true&output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=799072239&single=true&output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=777226094&single=true&output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=953185103&single=true&output=csv'
);

//load sources sheet separately
$sources = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vR7jfpBFfSzCLTTXLNCjU0p49GLFUMxbgrb1I5daS0uUjSFrBeM3SjHLUOTYE3NGd7ugMpi29qzu8cn/pub?gid=1073544792&single=true&output=csv');

//generate an array of records for outputting
$records = array();

//coinType validation to prevent multiple lookups of the same URI
$coinTypes = array();

//list of valid IDs in order to facilitate automated batch Solr ingestion (follows code from Mantis)
$accnums = array();

$errors = array();
$warnings = array();

//read the RDF for Nomisma collection URIs to use the preferred label from Nomisma
$nomismaURIs = parse_nomisma_collections($sources);

//read the Donum bibliographic metadata for catalog citations
$donum = array();

$startTime = date(DATE_W3C);

//get the eXist-db password from disk
$eXist_config_path = '/usr/local/projects/numishare/exist-config.xml';
$eXist_config = simplexml_load_file($eXist_config_path);
$eXist_credentials = $eXist_config->username . ':' . $eXist_config->password;

foreach ($sheets as $sheet){
    $count = 2;
    $data = generate_json($sheet);
    
    echo "Processing {$sheet}\n";
    
    foreach ($data as $row){        
        if (strlen(trim($row['ID'])) > 0){
            $id = trim($row['ID']);
            
            //don't process rows with a canonical URI that already exists in CRRO
            if (strlen(trim($row['Canonical URI (CRRO only)'])) == 0) {
                $record = array();
                $errorCount = 0;
                
                echo "Processing {$id}\n";                
                
                $record['recordId'] = $id;
                
                if (strlen($row['Title']) > 0){
                    $record['title'] = $row['Title'];
                } else {
                    $errors[] = "{$id}: No title";
                    $errorCount++;
                }
                
                //read sources sheet in order to create a reference with an optional URI
                $ref = trim($row['Source Ref']);
                if (strlen($ref) > 0){
                    foreach ($sources as $source){
                        if ($source['Label'] == $ref){
                           if ($source['Type'] == 'collection'){
                                $collection = array();                                
                                
                                //get preferred label from $nomismaURIs array
                                if (strpos($source['Collection Nomisma URI'], 'nomisma.org') !== FALSE){
                                    $collection['URI'] = $source['Collection Nomisma URI'];
                                    $collection['label'] = $nomismaURIs[$source['Collection Nomisma URI']]['label'];
                                } else {
                                    $collection['label'] = $ref;
                                }
                                
                                $record['collection'] = $collection;
                                
                                //if there's also a Donum URI, insert as a citation, since this corresponds to a collection catalog
                                if (strlen($source['Donum URI']) > 0){
                                    $donumURI = $source['Donum URI'];
                                    
                                    //read bibliographic metadata from Donum MODS XML                                    
                                    if (array_key_exists($donumURI, $donum)){
                                        echo "Found {$donumURI}\n";
                                        $record['citation'] = $donum[$donumURI];
                                    } else {
                                        $record['citation'] = parse_mods($donumURI);
                                    }  
                                }
                            } elseif ($source['Type'] == 'citation'){
                               
                                //lookup bibliographic metadata from Donum
                                if (strlen($source['Donum URI']) > 0){
                                    $donumURI = $source['Donum URI'];
                                    
                                    //read bibliographic metadata from Donum MODS XML
                                    if (array_key_exists($donumURI, $donum)){
                                        echo "Found {$donumURI}\n";
                                        $record['citation'] = $donum[$donumURI];
                                    } else {
                                        $record['citation'] = parse_mods($donumURI);
                                    }
                                } else {
                                    $citation = array();
                                    $citation['label'] = $ref;
                                    $record['citation'] = $citation;
                                }
                            } else {
                                $provenance = array();
                                $provenance['label'] = $ref;
                                if (strlen($source['Donum URI']) > 0){
                                    $provenance['URI'] = $source['Donum URI'];
                                }
                                
                                if (strlen(trim($row['Source ID number'])) > 0){
                                    $provenance['ID'] = trim($row['Source ID number']);
                                }
                                
                                if (strlen($source['Start Date']) > 0 && strlen($source['End Date']) > 0){
                                    if ($source['Start Date'] == $source['End Date']){
                                        $provenance['date'] = $source['Start Date'];
                                    } else {
                                        $provenance['fromDate'] = $source['Start Date'];
                                        $provenance['toDate'] = $source['End Date'];
                                    }
                                }
                                
                                $record['provenance'] = $provenance;
                                
                            }
                        }
                    }
                }
                
                //validate the type URI
                if (strlen(trim($row['Type'])) > 0){
                    $uri = trim($row['Type']);
                    if (in_array($uri, $coinTypes)){
                        $record['coinType'] = $uri;
                    } else {
                        if (preg_match('/^http:\/\/numismatics\.org\/crro\/id\/rrc-/', $uri)){
                            $file_headers = @get_headers($uri);
                            if (strpos($file_headers[0], '200') !== FALSE){
                                $record['coinType'] = $uri;
                                $coinTypes[] = $uri;
                            } else {
                                $errors[] = "{$id}: coin type URI ({$uri}) does not resolve in CRRO";
                                $errorCount++;
                            }
                        } else {
                            $errors[] = "{$id}: coin type URI ({$uri}) is invalid";
                            $errorCount++;
                        }
                    }
                } else {
                    $warnings[] = $id;
                }
                
                //read images from the annotation endpoint
                $obverseAnnotation = query_solr($id, 'obv');
                $reverseAnnotation = query_solr($id, 'rev');
                
                //parse images into resource link
                if (isset($obverseAnnotation)){
                    if (array_key_exists('image', $obverseAnnotation)){
                        $record['obvImage'] = $obverseAnnotation['image'];
                        $record['obvContext'] = $obverseAnnotation['context'];
                    }   
                }
                        
                if (isset($reverseAnnotation)){
                    if (array_key_exists('image', $reverseAnnotation)){
                        $record['revImage'] = $reverseAnnotation['image'];
                        $record['revContext'] = $reverseAnnotation['context'];
                    }
                }                
                
                //physDesc objects
                $weight = trim($row['Weight']);
                if (strlen($weight) > 0){
                    if (is_numeric($weight) && $weight > 0){
                        $record['weight'] = $weight;
                    } elseif(!is_numeric($weight) && strlen($weight) > 0){
                        $errors[] = "{$id}: has non-numeric weight.";
                        $errorCount++;
                    }
                }
                
                $diameter = trim($row['Diameter']);
                if (strlen($diameter) > 0){
                    if (is_numeric($diameter) && $diameter > 0){
                        $record['diameter'] = $diameter;
                    } elseif(!is_numeric($diameter) && strlen($diameter) > 0){
                        $errors[] = "{$id}: has non-numeric diameter.";
                        $errorCount++;
                    }
                }
                
                $axis = trim($row['Axis']);
                if (strlen($axis) > 0){
                    if (is_int((int) $axis) && $axis > 0){
                        $record['axis'] = $axis;
                    } elseif(!is_numeric($diameter) && strlen($diameter) > 0){
                        $errors[] = "{$id}: has non-numeric axis.";
                        $errorCount++;
                    }
                }

                if (strlen(trim($row['Schaefer Notes'])) > 0){
                    $record['Schaefer Notes'] = trim($row['Schaefer Notes']);
                }
                
                if (strlen(trim($row['New Notes'])) > 0){
                    $record['New Notes'] = trim($row['New Notes']);
                }
                
                //only insert the record if there are no validation errors
                if ($errorCount == 0){
                    $records[] = $record;
                }
            } else {
                //output the URI
                echo "Skipping {$id}: {$row['Canonical URI (CRRO only)']}\n";
            }
        } else {
            $errors[] = "Row {$count} has no ID";
        }            
        $count++;
    }
}

//var_dump($records);



//process $records object into NUDS and write to eXist-db
foreach ($records as $record){
    $id = $record['recordId'];
    $fileName = "/tmp/{$id}.xml";
    $datetime = date(DATE_W3C);
    
    generate_nuds($record, $fileName);
    
    //read file back into memory for PUT to eXist
    if (($readFile = fopen($fileName, 'r')) === FALSE){
        error_log(COLLECTION_NAME . ": unable to read {$fileName} for putting to eXist-db.\n", 3, "/var/log/numishare/error.log");
    } else {
        //PUT xml to eXist
        $putToExist=curl_init();
        
        //set curl opts
        curl_setopt($putToExist,CURLOPT_URL,'http://localhost:8888/exist/rest/db/' . COLLECTION_NAME . '/objects/' . $id . '.xml');
        curl_setopt($putToExist,CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=utf-8"));
        curl_setopt($putToExist,CURLOPT_CONNECTTIMEOUT,2);
        curl_setopt($putToExist,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($putToExist,CURLOPT_PUT,1);
        curl_setopt($putToExist,CURLOPT_INFILESIZE,filesize($fileName));
        curl_setopt($putToExist,CURLOPT_INFILE,$readFile);
        curl_setopt($putToExist,CURLOPT_USERPWD,$eXist_credentials);
        $response = curl_exec($putToExist);
        
        $http_code = curl_getinfo($putToExist,CURLINFO_HTTP_CODE);
        
        //error and success logging
        if (curl_error($putToExist) === FALSE){
            error_log(COLLECTION_NAME . ": {$id} failed to upload to eXist at {$datetime}\n", 3, "/var/log/numishare/error.log");
        } else {
            if ($http_code == '201'){
                $datetime = date(DATE_W3C);
                echo "Writing {$id}.\n";
                error_log(COLLECTION_NAME . ": {$id} written at {$datetime}\n", 3, "/var/log/numishare/success.log");
                
                //if file was successfully PUT to eXist, add the accession number to the array for Solr indexing.
                $accnums[] = $id;
                
                //index records into Solr in increments of the INDEX_COUNT constant
                if (count($accnums) > 0 && count($accnums) % INDEX_COUNT == 0 ){
                    $start = count($accnums) - INDEX_COUNT;
                    $toIndex = array_slice($accnums, $start, INDEX_COUNT);
                    
                    //POST TO SOLR
                    generate_solr_shell_script($toIndex);
                }
            }
        }
        //close eXist curl
        curl_close($putToExist);
        
        //close files and delete from /tmp
        fclose($readFile);
        unlink($fileName);
    }
}

//execute process for remaining accnums.
$start = floor(count($accnums) / INDEX_COUNT) * INDEX_COUNT;
$toIndex = array_slice($accnums, $start);

//POST TO SOLR
generate_solr_shell_script($toIndex);

$endTime = date(DATE_W3C);

//send email report
generate_email_report($accnums, $errors, $warnings, $startTime, $endTime);

var_dump($errors);

/***** CREATE NUDS RECORD *****/
function generate_nuds($record, $fileName){
    GLOBAL $coinTypes;
    
    $writer = new XMLWriter();
    $writer->openURI($fileName);
    //$writer->openURI('php://output');
    $writer->startDocument('1.0','UTF-8');
    $writer->setIndent(true);
    //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
    $writer->setIndentString("    ");
    
    $writer->startElement('nuds');
        $writer->writeAttribute('xmlns', 'http://nomisma.org/nuds');
        $writer->writeAttribute('xmlns:xs', "http://www.w3.org/2001/XMLSchema");
        $writer->writeAttribute('xmlns:xlink', "http://www.w3.org/1999/xlink");
        $writer->writeAttribute('xmlns:mets', "http://www.loc.gov/METS/");
        $writer->writeAttribute('xmlns:tei', "http://www.tei-c.org/ns/1.0");
        $writer->writeAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
        $writer->writeAttribute('xsi:schemaLocation', 'http://nomisma.org/nuds http://nomisma.org/nuds.xsd');
        $writer->writeAttribute('recordType', "physical");
    
    //start control
    $writer->startElement('control');
        $writer->writeElement('recordId', $record['recordId']);
        $writer->writeElement('publicationStatus', 'approved');
        $writer->writeElement('maintenanceStatus', 'derived');
        $writer->startElement('maintenanceAgency');
            $writer->writeElement('agencyName', 'American Numismatic Society');
        $writer->endElement();
        
        //maintenanceHistory
        $writer->startElement('maintenanceHistory');
            $writer->startElement('maintenanceEvent');
                $writer->writeElement('eventType', 'derived');
                $writer->startElement('eventDateTime');
                    $writer->writeAttribute('standardDateTime', date(DATE_W3C));
                    $writer->text(date("D, d M Y", time()));
                $writer->endElement();
                $writer->writeElement('agentType', 'machine');
                $writer->writeElement('agent', 'PHP');
                $writer->writeElement('eventDescription', 'Generated from CSV on Google Drive');
            $writer->endElement();
        $writer->endElement();
        
        //rightsStmt
        $writer->startElement('rightsStmt');
        
        //data and image licenses
        $writer->startElement('license');
            $writer->writeAttribute('for', 'data');
            $writer->writeAttribute('xlink:type', 'simple');
            $writer->writeAttribute('xlink:href', 'http://opendatacommons.org/licenses/odbl/');
            $writer->text('Metadata are openly licensed with a Open Data Commons Open Database License (ODbL)');
        $writer->endElement();
        
        $writer->startElement('license');
            $writer->writeAttribute('for', 'images');
            $writer->writeAttribute('xlink:type', 'simple');
            $writer->writeAttribute('xlink:href', 'https://creativecommons.org/choose/mark/');
            $writer->text('Public Domain Mark');        
        $writer->endElement();
        
        //rights statement about physical object
        $writer->startElement('rights');
            $writer->writeAttribute('xlink:type', 'simple');
            $writer->writeAttribute('xlink:href', 'http://rightsstatements.org/vocab/NoC-US/1.0/');
            $writer->text('No Copyright - United States');
        $writer->endElement();
        
        //rights statement
        $writer->endElement();
        
        //semanticDeclaration
        $writer->startElement('semanticDeclaration');
            $writer->writeElement('prefix', 'nmo');
            $writer->writeElement('namespace', 'http://nomisma.org/ontology#');
        $writer->endElement();
        
        $writer->startElement('semanticDeclaration');
            $writer->writeElement('prefix', 'skos');
            $writer->writeElement('namespace', 'http://www.w3.org/2004/02/skos/core#');
        $writer->endElement();
    $writer->endElement();
    //end control
    
    //begin descMeta
    $writer->startElement('descMeta');
        //title
        $writer->startElement('title');
            $writer->writeAttribute('xml:lang', 'en');
            $writer->text($record['title']);
        $writer->endElement();
        
        if (array_key_exists('Schaefer Notes', $record) || array_key_exists('New Notes', $record)){
            $writer->startElement('noteSet');
                if (array_key_exists('Schaefer Notes', $record)){
                    $writer->startElement('note');
                        $writer->writeAttribute('localType', 'Schaefer Notes');
                        $writer->text($record['Schaefer Notes']);
                    $writer->endElement();
                }
                if (array_key_exists('New Notes', $record)){
                    $writer->startElement('note');
                        $writer->writeAttribute('localType', 'New Notes');
                        $writer->text($record['New Notes']);
                    $writer->endElement();
                }
            $writer->endElement();
        }
    
        //typeDesc
        
        if (array_key_exists('coinType', $record)){
            $writer->startElement('typeDesc');
                $writer->writeAttribute('xlink:type', 'simple');
                $writer->writeAttribute('xlink:href', $record['coinType']);
            $writer->endElement();
        }
        
        //physDesc
        if (array_key_exists('axis', $record) || array_key_exists('weight', $record) || array_key_exists('diameter', $record)){
            $writer->startElement('physDesc');
            if (array_key_exists('axis', $record)){
               $writer->writeElement('axis', $record['axis']); 
            }
            if (array_key_exists('weight', $record) || array_key_exists('diameter', $record)){
                $writer->startElement('measurementsSet');                
                if (array_key_exists('diameter', $record)){
                    $writer->startElement('diameter');
                        $writer->writeAttribute('units', 'mm');
                        $writer->text($record['diameter']);
                    $writer->endElement();
                }
                if (array_key_exists('weight', $record)){
                    $writer->startElement('weight');
                        $writer->writeAttribute('units', 'g');
                        $writer->text($record['weight']);
                    $writer->endElement();
                }
                $writer->endElement();
            }
            $writer->endElement();
        }
        
        //refDesc
        if (array_key_exists('citation', $record)){
            $writer->startElement('refDesc');
                $writer->startElement('citation');
                    if (array_key_exists('URI', $record['citation'])){
                        $writer->writeAttribute('xlink:type', 'simple');
                        $writer->writeAttribute('xlink:href', $record['citation']['URI']);                    
                    }
                    
                    //authors
                    if (array_key_exists('authors', $record['citation'])){
                        foreach ($record['citation']['authors'] as $author){
                            $writer->writeElement('tei:author', $author);
                        }
                    }
                    
                    $writer->startElement('tei:title');
                        $writer->writeAttribute('type', 'main');
                        $writer->text($record['citation']['title']);
                    $writer->endElement();
                    
                    if (array_key_exists('subtitle', $record['citation'])){
                        $writer->startElement('tei:title');
                            $writer->writeAttribute('type', 'sub');
                            $writer->text($record['citation']['subtitle']);
                        $writer->endElement();
                    }                    
                    
                    if (array_key_exists('pubPlaces', $record['citation'])){
                        foreach ($record['citation']['pubPlaces'] as $place){
                            $writer->writeElement('tei:pubPlace', $place);
                        }
                    }
                    
                    if (array_key_exists('publisher', $record['citation'])){
                        $writer->writeElement('tei:publisher', $record['citation']['publisher']);
                    }
                    
                    if (array_key_exists('date', $record['citation'])){
                        $writer->writeElement('tei:date', $record['citation']['date']);
                    }
                    
                $writer->endElement();
            $writer->endElement();
        }
        
        //put collection/provenance in adminDesc
        if (array_key_exists('collection', $record) || array_key_exists('provenance', $record)){
            $writer->startElement('adminDesc');
            //collection
            if (array_key_exists('collection', $record)) {
                $writer->startElement('collection');
                    if (array_key_exists('URI', $record['collection'])){
                        $writer->writeAttribute('xlink:type', 'simple');
                        $writer->writeAttribute('xlink:href', $record['collection']['URI']);
                    }
                    $writer->text($record['collection']['label']);
                $writer->endElement();
            }
            //construct provenance
            if (array_key_exists('provenance', $record)) {
                $writer->startElement('provenance');
                    $writer->startElement('chronList');
                        $writer->startElement('chronItem');
                            $writer->startElement('previousColl');
                                $writer->startElement('saleCatalog');
                                    if (array_key_exists('URI', $record['provenance'])){
                                        $writer->writeAttribute('xlink:type', 'simple');
                                        $writer->writeAttribute('xlink:href', $record['provenance']['URI']);
                                    }
                                    $writer->text($record['provenance']['label']);                                    
                                $writer->endElement();
                                
                                if (array_key_exists('ID', $record['provenance'])){
                                    $writer->writeElement('identifier', $record['provenance']['ID']);
                                }
                                
                                $writer->writeElement('method', 'auction');
                            $writer->endElement();
                            
                            //insert optional dates into the chronItem
                            if (array_key_exists('date', $record['provenance'])){
                                $writer->startElement('date');
                                    $writer->writeAttribute('standardDate', $record['provenance']['date']);
                                    $writer->text(date("j M Y", strtotime($record['provenance']['date'])));
                                $writer->endElement();
                            } elseif (array_key_exists('fromDate', $record['provenance']) && array_key_exists('toDate', $record['provenance'])){
                                $writer->startElement('dateRange');
                                    $writer->startElement('fromDate');
                                        $writer->writeAttribute('standardDate', $record['provenance']['fromDate']);
                                        $writer->text(date("j M Y", strtotime($record['provenance']['fromDate'])));
                                    $writer->endElement();
                                    $writer->startElement('toDate');
                                        $writer->writeAttribute('standardDate', $record['provenance']['toDate']);
                                        $writer->text(date("j M Y", strtotime($record['provenance']['toDate'])));
                                    $writer->endElement();
                                $writer->endElement();
                            }
                            
                        $writer->endElement();
                    $writer->endElement();
                $writer->endElement();
            }
            $writer->endElement();
        }    
        $writer->endElement();
        //end descMeta
        
        //digRep
        $writer->startElement('digRep');
            $writer->startElement('mets:fileSec');
            
            if (array_key_exists('obvImage', $record)){                
                //obverse images
                $writer->startElement('mets:fileGrp');
                    $writer->writeAttribute('USE', 'obverse');
                    //reference
                    $writer->startElement('mets:file');
                        $writer->writeAttribute('USE', 'reference');
                        $writer->writeAttribute('MIMETYPE', 'image/jpeg');
                        $writer->startElement('mets:FLocat');
                            $writer->writeAttribute('LOCYPE', 'URL');
                            $writer->writeAttribute('xlink:href', $record['obvImage']);
                        $writer->endElement();
                    $writer->endElement();
                    
                    //thumbnail
                    $writer->startElement('mets:file');
                        $writer->writeAttribute('USE', 'thumbnail');
                        $writer->writeAttribute('MIMETYPE', 'image/jpeg');
                        $writer->startElement('mets:FLocat');
                            $writer->writeAttribute('LOCYPE', 'URL');
                            $writer->writeAttribute('xlink:href', str_replace('full', ',120', $record['obvImage']));
                        $writer->endElement();
                    $writer->endElement();
                    
                    //link to page in Archer
                    $writer->startElement('mets:file');
                        $writer->writeAttribute('USE', 'context');
                        $writer->startElement('mets:FLocat');
                            $writer->writeAttribute('LOCYPE', 'URL');
                            $writer->writeAttribute('xlink:href', $record['obvContext']);
                        $writer->endElement();
                    $writer->endElement();
                    
                $writer->endElement();    
            }
            if (array_key_exists('revImage', $record)){
                //reverse images
                $writer->startElement('mets:fileGrp');
                    $writer->writeAttribute('USE', 'reverse');
                    //reference
                    $writer->startElement('mets:file');
                        $writer->writeAttribute('USE', 'reference');
                        $writer->writeAttribute('MIMETYPE', 'image/jpeg');
                        $writer->startElement('mets:FLocat');
                            $writer->writeAttribute('LOCYPE', 'URL');
                            $writer->writeAttribute('xlink:href', $record['revImage']);
                        $writer->endElement();
                    $writer->endElement();
                    
                    //thumbnail
                    $writer->startElement('mets:file');
                        $writer->writeAttribute('USE', 'thumbnail');
                        $writer->writeAttribute('MIMETYPE', 'image/jpeg');
                        $writer->startElement('mets:FLocat');
                            $writer->writeAttribute('LOCYPE', 'URL');
                            $writer->writeAttribute('xlink:href', str_replace('full', ',120', $record['revImage']));
                        $writer->endElement();
                    $writer->endElement();
                    
                    //link to page in Archer
                    $writer->startElement('mets:file');
                        $writer->writeAttribute('USE', 'context');
                        $writer->startElement('mets:FLocat');
                            $writer->writeAttribute('LOCYPE', 'URL');
                            $writer->writeAttribute('xlink:href', $record['revContext']);
                        $writer->endElement();
                    $writer->endElement();
                $writer->endElement();    
            }
                
            //end mets:fileSec and nuds:digRep
            $writer->endElement();
        $writer->endElement();
    
    //end nuds
    $writer->endElement();
    
    //close file
    $writer->endDocument();
    $writer->flush();
}


//var_dump($records);
//var_dump($errors);

/***** DATA PARSING FUNCTIONS *****/
//get JSON from Solr and evaluate the number of docs
function query_solr($id, $side){
    GLOBAL $errors;
    
    $url = ANNOTATION_SOLR_URL . "?q=body:{$id}_{$side}&wt=json";
    $json = file_get_contents($url);
    $obj = json_decode($json);
    
    if ($obj->response->numFound == 1){
        //parse the base64 encoded annotation
        $target = $obj->response->docs[0]->target;
        $base64 = $obj->response->docs[0]->data;   
        
        $context = str_replace('manifest', 'ark:/53695', str_replace('/canvas/', '#', $target));
        
        $imageURL = parse_annotation($id, $base64);
        
        $image = array('image'=>$imageURL, 'context'=>$context);
        return $image;
    } elseif ($obj->response->numFound == 0){
        $errors[] = "{$id}: No annotated {$side} image";
    } else {
        $errors[] = "{$id}: More than one annotated {$side} image";
    }
}

//base64_decode the 'data' field in the Solr document and then decode that into JSON
function parse_annotation ($id, $base64) {
    GLOBAL $errors;
    
    $json = base64_decode($base64);
    $obj = json_decode($json);
    
    //parse the fragment identifier
    if (isset($obj->on[0]->selector->default->value)){
        $canvas = $obj->on[0]->full;        
        $frag = explode("=", $obj->on[0]->selector->default->value)[1];
        
        $pieces = explode('/', $canvas);
        $filename = $pieces[count($pieces) - 1];
        
        $image = "http://images.numismatics.org/archivesimages%2Farchive%2F{$filename}.jpg/{$frag}/full/0/default.jpg";
        return $image;
    } else {
        $errors[] = "{$id}: No fragment identifier found in annotation";
        return null;
    }
}

/***** GET PREFLABEL FOR NOMISMA COLLECTION *****/
function parse_nomisma_collections($sources){
    $nomismaURIs = array();
    
    foreach ($sources as $row){
        if (strpos($row['Collection Nomisma URI'], 'nomisma.org') !== FALSE){
            $uri = $row['Collection Nomisma URI'];
            $nomismaURIs[$uri] = parse_nomisma_uri($uri);
        }
    }
    
    return $nomismaURIs;
}

function parse_nomisma_uri ($uri){
    
    $file_headers = @get_headers($uri);
    
    //only get RDF if the ID exists
    if (strpos($file_headers[0], '200') !== FALSE){
        $xmlDoc = new DOMDocument();
        $xmlDoc->load($uri . '.rdf');
        $xpath = new DOMXpath($xmlDoc);
        $xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
        $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $type = $xpath->query("/rdf:RDF/*")->item(0)->nodeName;
        $label = $xpath->query("descendant::skos:prefLabel[@xml:lang='en']")->item(0)->nodeValue;
        
        if (!isset($label)){
            echo "Error with {$uri}\n";
        }
        
        return array('label'=>$label,'type'=>$type);
    } else {
        //otherwise output the error
        echo "Error: {$uri} not found.\n";
        return array('label'=>$uri,'type'=>'nmo:Collection');
    }
}

function parse_mods ($uri){
    GLOBAL $donum;
    
    $donumID = explode('/', $uri)[4];
    $modsURL = "https://donum.numismatics.org/cgi-bin/koha/opac-export.pl?op=export&bib={$donumID}&format=mods";
    
    $file_headers = @get_headers($modsURL);
    
    //only get RDF if the ID exists
    if (strpos($file_headers[0], '200') !== FALSE){
        $xmlDoc = new DOMDocument();
        $xmlDoc->load($modsURL);
        $xpath = new DOMXpath($xmlDoc);
        $xpath->registerNamespace('mods', 'http://www.loc.gov/mods/v3');
        
        echo "Extracting bib {$donumID}\n";
        
        $bib = array();
               
        //titles
        $bib['title'] = $xpath->query("/mods:mods/mods:titleInfo/mods:title")->item(0)->nodeValue;        
        if (count($xpath->query("/mods:mods/mods:titleInfo/mods:subTitle")) > 0){
            $bib['subtitle'] = $xpath->query("/mods:mods/mods:titleInfo/mods:subTitle")->item(0)->nodeValue;
        }        
        
        //authors
        $authors = $xpath->query("/mods:mods/mods:name[@type = 'personal'][mods:role/mods:roleTerm = 'creator' or mods:role/mods:roleTerm = 'auth.' or mods:role/mods:roleTerm = 'author']/mods:namePart");
        
        foreach ($authors as $author){
            $bib['authors'][] = $author->nodeValue;
        }
        
        if (count($xpath->query("/mods:mods/mods:originInfo/mods:publisher")) > 0){
            $bib['publisher'] = $xpath->query("/mods:mods/mods:originInfo/mods:publisher")->item(0)->nodeValue;
        }  
        
        $places = $xpath->query("/mods:mods/mods:originInfo/mods:place/mods:placeTerm[@type = 'text']");
        foreach ($places as $place){
            $bib['pubPlaces'][] = $place->nodeValue;
        }
        
        if (count($xpath->query("/mods:mods/mods:originInfo/mods:dateIssued")) > 0){
            $bib['date'] = $xpath->query("/mods:mods/mods:originInfo/mods:dateIssued")->item(0)->nodeValue;
        }   
        
        $bib['URI'] = $uri;
        
        $donum[$uri] = $bib;
        return $bib;
    } else {
        //otherwise output the error
        echo "Error: {$uri} not found.\n";
    }
}

/***** PUBLICATION AND REPORTING FUNCTIONS *****/
//generate a shell script to activate batch ingestion
function generate_solr_shell_script($array){
    $uniqid = uniqid();
    $solrDocUrl = 'http://localhost:8080/orbeon/numishare/' . COLLECTION_NAME . '/ingest?identifiers=' . implode('%7C', $array);
    
    //generate content of bash script
    $sh = "#!/bin/sh\n";
    $sh .= "curl {$solrDocUrl} > /tmp/{$uniqid}.xml\n";
    $sh .= "curl " . NUMISHARE_SOLR_URL . " --data-binary @/tmp/{$uniqid}.xml -H 'Content-type:text/xml; charset=utf-8'\n";
    $sh .= "curl " . NUMISHARE_SOLR_URL . " --data-binary '<commit/>' -H 'Content-type:text/xml; charset=utf-8'\n";
    $sh .= "rm /tmp/{$uniqid}.xml\n";
    
    $shFileName = '/tmp/' . $uniqid . '.sh';
    $file = fopen($shFileName, 'w');
    if ($file){
        fwrite($file, $sh);
        fclose($file);
        
        echo "Posting to Solr\n";
        
        //execute script
        shell_exec('sh /tmp/' . $uniqid . '.sh > /dev/null 2>/dev/null &');
        //commented out the line below because PHP seems to delete the file before it has had a chance to run in the shell
        //unlink('/tmp/' . $uniqid . '.sh');
    } else {
        error_log("{COLLECTION_NAME}: Unable to create {$uniqid}.sh at " . date(DATE_W3C) . "\n", 3, "/var/log/numishare/error.log");
    }
}

//send an email report
function generate_email_report ($accnums, $errors, $warnings, $startTime, $endTime){
    $to = 'database@numismatics.org, lcarbone@numismatics.org, yarrow@brooklyn.cuny.edu, ewg4xuva@gmail.com';
    $subject = "Error report for " . COLLECTION_NAME;
    $body = "Error Report for " . COLLECTION_NAME . "\n\n";
    $body .= "Successful objects: " . count($accnums) . "\n";
    $body .= "Errors: " . count($errors) . "\n\n";
    $body .= "Start Time: {$startTime}\n";
    $body .= "End Time: {$endTime}\n\n";
    $body .= "The following accession numbers failed to process:\n\n";
    foreach ($errors as $error){
        $body .= $error . "\n";
    }
    
    if (count($warnings) > 0){
        $body .= "\n\nThe following coins do not have coin type URIs, but were processed anyway:\n\n";
        foreach ($warnings as $warning){
            $body .= $warning . "\n";
        }
    }
    
    $body .= "\nNote that records with errors were not published to Numishare. Please review the relevant spreadsheets.\n";
    mail($to, $subject, $body);
}

//test URL http://images.numismatics.org/archivesimages%2Farchive%2Fschaefer_015_b01_p081-0.jpg/142,669,235,253/full/0/default.jpg

/***** CSV FUNCTIONS *****/
function generate_json($doc){
    $keys = array();
    $array = array();
    
    $csv = csvToArray($doc, ',');
    
    // Set number of elements (minus 1 because we shift off the first row)
    $count = count($csv) - 1;
    
    //Use first row for names
    $labels = array_shift($csv);
    
    foreach ($labels as $label) {
        $keys[] = $label;
    }
    
    // Bring it all together
    for ($j = 0; $j < $count; $j++) {
        $d = array_combine($keys, $csv[$j]);
        $array[$j] = $d;
    }
    return $array;
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

function url_exists($url) {
    if (!$fp = curl_init($url)) return false;
    return true;
}

?>