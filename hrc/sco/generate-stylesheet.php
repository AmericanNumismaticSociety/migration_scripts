<?php 

/*****
 * Author: Ethan Gruber
 * Date: October 2018
 * Function: Process the SCO spreadsheet in order to generate Obverse and Reverse type description codes for the multilingual stylesheet
 *****/


$data = generate_json('SCO Part 2 - SCO.csv');
$csv = array();
$obverse = array();
$reverse = array();

foreach ($data as $row){
    if (strlen($row['O (en)']) > 0){
        $key = $row['O'];
        $val = $row['O (en)'];
        
        if (!array_key_exists($key, $obverse)){
            $obverse[$key] = $val;
        }
    }
    if (strlen($row['R (en)']) > 0){
        $key = $row['R'];
        $val = $row['R (en)'];
        
        if (!array_key_exists($key, $reverse)){
            $reverse[$key] = $val;
        }
    }
}

ksort($obverse);
ksort($reverse);

foreach ($obverse as $k=>$v){    
    $csv[] = array($k, $v);
}
foreach ($reverse as $k=>$v){  
    $csv[] = array($k, $v);
}



//write CSV
$file = fopen('stylesheet-new.csv', 'w');
fputcsv($file, array('Abbreviation', 'en'));
foreach ($csv as $fields) {
    fputcsv($file, $fields);
}
fclose($file);


/***** FUNCTIONS *****/
function number_pad($number,$n) {
    if ($number > 0){
        $gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
    } elseif ($number < 0) {
        $gYear = '-' . str_pad((int) abs($number),$n,"0",STR_PAD_LEFT);
    }
    return $gYear;
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