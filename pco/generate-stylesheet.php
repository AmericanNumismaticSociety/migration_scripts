<?php 

/*****
 * Author: Ethan Gruber
 * Date: October 2018
 * Function: Process the PCO spreadsheet in order to generate Obverse and Reverse type description codes for the multilingual stylesheet
 *****/


$data = generate_json('pco.csv');
$csv = array();
$obverse = array();
$reverse = array();

foreach ($data as $row){
    if (strlen($row['O (en)']) > 0){
        $val = $row['O (en)'];
        
        if (!in_array($val, $obverse)){
            $obverse[] = $val;
        }
    }
    if (strlen($row['R (en)']) > 0){
        $val = $row['R (en)'];
        
        if (!in_array($val, $reverse)){
            $reverse[] = $val;
        }
    }
}

foreach ($obverse as $k=>$v){
    $num = $k + 1;
    $id = 'O' . number_pad($num, 3);
    
    $csv[] = array($id, $v);
}
foreach ($reverse as $k=>$v){
    $num = $k + 1;
    $id = 'R' . number_pad($num, 3);
    
    $csv[] = array($id, $v);
}

//write CSV
$file = fopen('stylesheet.csv', 'w');
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