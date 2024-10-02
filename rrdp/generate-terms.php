<?php 
/*****
 * Author: Ethan Gruber
 * Date: October 2024
 * Function: Generate index terms between schaefer pages and CRRO URIs
 */

$data = generate_json('Schaefer ID CRRO mapping - Sheet1.csv');

foreach ($data as $row) {
    $id = $row['Page ID'];
    
    foreach ($row as $k=>$v) {
        if ($k != 'Page ID' && strlen($v) > 0) {
            $label = str_replace('.', '/', str_replace('http://numismatics.org/crro/id/rrc-', 'RRC ', $v));
            
            $term = '<term ref="' . $v . '" facs="#' . $id . '">' . $label . '</term>' . "\n";
            echo $term;
        }
    }
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