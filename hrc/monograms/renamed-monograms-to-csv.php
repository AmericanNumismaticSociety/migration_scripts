<?php 

/*****
 * Ethan Gruber
 * Date: June 2020
 * Function: Read the concordance between new and old svg filenames and generate a CSV file for unique monogram URIs and updated filenames to be published to HRC
 * through the Google spreadsheet export mechanism. The script should read the Greek letters from monograms.xml file that were inputted in the XForms app
*****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vSFMAciBwXoe_egvgsdO3okHslqOXl9dm98WZI50EX2dGEesZXgXP3V7Bertm8RLAE6yHLlA12Do6tf/pub?output=csv');
$xml = simplexml_load_file('/usr/local/projects/migration_scripts/fonts/xforms/xml/monograms.xml');
$objects = array();

foreach ($data as $row){
    $id = trim($row['monogram ID']);
    
    //ignore blank rows, which are letters
    if (strlen($id) > 0){        
        $filename = trim($row['Old Filename']) . '.svg';
        
        $objects[$id]['ID'] = $id;
        //add filenames
        $objects[$id]['files'][] = trim($row['New Filename']);
        
        //only read the constituent letters from the XML file if it hasn't already been done
        
        if (!array_key_exists('letters', $objects[$id])){
            foreach ($xml->folder[3]->children() as $file){
                if (trim($file['name']) == $filename){
                    
                    $objects[$id]['letters'] = trim($file['letters']);
                    //echo $file['letters'];
                }
            }
        }
    }
}

generate_xml($objects);


//var_dump($objects);


/**** FUNCTIONS ****/
//generate a new folder XML node for inserting letters
function generate_xml($objects){
    $doc = new XMLWriter();    
    $doc->openUri('php://output');
    //$doc->openUri('lorber.xml');
    $doc->setIndent(true);
    //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
    $doc->setIndentString("    ");    
    $doc->startDocument('1.0','UTF-8');    
    $doc->startElement('folder');
        $doc->writeAttribute('name', "Lorber");
    
    
        foreach ($objects as $monogram){
            $letters = (array_key_exists('letters', $monogram)) ? $monogram['letters'] : '';
            
            foreach ($monogram['files'] as $file){
                
                $doc->startElement('file');
                    $doc->writeAttribute('name', $file . '.svg');
                    $doc->writeAttribute('letters', $letters);
                $doc->endElement();
            }
            
            unset($letters);
        }
    
    $doc->endElement();
    
    //close file
    $doc->endDocument();
    $doc->flush();
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