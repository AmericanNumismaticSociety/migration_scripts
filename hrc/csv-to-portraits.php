<?php 

/*****
 * Author: Ethan Gruber
 * Date: June 2020
 * Function: Write a CSV of portrait images for HRC into the XML model for the identify page (like OCRE)
 *****/

$portraits = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vRjtGAw0Tlp32OKrYhzRumpfDpJDXXnCIguV5i1U8HMm7Pqfomo-8bIEHAWhcYtvIZtO5gM9AWQOuIu/pub?output=csv');
$records = array();
$dynasties = array();

//extract dynasties in chronological order
foreach($portraits as $row){
    if (!in_array($row['org'], $dynasties)){
        $dynasties[] = $row['org'];
    }
}

$writer = new XMLWriter();
$writer->openURI('portraits.xml');
//$writer->openURI('php://output');
$writer->startDocument('1.0','UTF-8');
$writer->setIndent(true);
$writer->setIndentString("    ");
$writer->startElement('portraits');

//process each row, organized by dynasty
foreach($dynasties as $dynasty){
    $writer->startElement('period');
    $writer->writeAttribute('label', $dynasty);
    foreach($portraits as $row){
        if ($row['org'] == $dynasty){
            //recreate records to generate a new CSV export that includes only image URLs
            $record = array();
            $record['uri'] = $row['uri'];
            $record['name'] = $row['name'];
            $record['org'] = $row['org'];
            $record['dynasty'] = $row['dynasty'];
            
            //create portrait elements
            $writer->startElement('portrait');
            $writer->writeAttribute('uri', $row['uri']);
           
            //process columns for coin images in various materials
            if (strlen(trim($row['AV'])) > 0){
                $writer->startElement('material');
                $writer->writeAttribute('uri', 'http://nomisma.org/id/av');
                
                $images = explode('|', trim($row['AV']));
                $record['AV'] = $images;
                if (count($images) > 0){
                    foreach ($images as $coinURL){
                        $writer->writeElement('image', $coinURL);
                    }
                }
                $writer->endElement();
            }
            if (strlen(trim($row['AR'])) > 0){
                $writer->startElement('material');
                $writer->writeAttribute('uri', 'http://nomisma.org/id/ar');
                
                $images = explode('|', trim($row['AR']));
                $record['AR'] = $images;
                if (count($images) > 0){
                    foreach ($images as $coinURL){
                        $writer->writeElement('image', $coinURL);
                    }
                }
                $writer->endElement();
            }
            if (strlen(trim($row['AE'])) > 0){
                $writer->startElement('material');
                $writer->writeAttribute('uri', 'http://nomisma.org/id/ae');
               
                $images = explode('|', trim($row['AE']));
                $record['AE'] = $images;
                if (count($images) > 0){
                    foreach ($images as $coinURL){
                        $writer->writeElement('image', $coinURL);
                    }
                }
                $writer->endElement();
            }
            if (strlen(trim($row['worn'])) > 0){
                $writer->startElement('worn');
                $images = explode('|', trim($row['worn']));
                $record['worn'] = $images;
                if (count($images) > 0){
                    foreach ($images as $coinURL){
                        $writer->writeElement('image', $coinURL);
                    }
                }
                $writer->endElement();
            }
            //add $record to $records
            $records[] = $record;
            
            //end portrait
            $writer->endElement();
        }
    }
    //end period
    $writer->endElement();
}
//end file
$writer->endElement();
$writer->flush();

//write CSV into an array
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