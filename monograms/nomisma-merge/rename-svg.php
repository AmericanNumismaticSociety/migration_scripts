<?php 
/*****
 * Author: Ethan Gruber 
 * Date: September 2024
 * Function: Rename SVG files to new sequential Nomisma IDs
 *****/

$data = generate_json('2024-09-25-monogram.csv');


$count = 1;

foreach ($data as $row) {
    
    if ($row['duplicate'] != 'delete'){   
        
        $id = 'monogram.' . number_pad($count, 5) . '.svg';
        
        $old = $row['image'] . '.svg';
        
        if (!copy('svg-in/' . $old, 'svg-out/' . $id)) {
            echo "failed to copy $old...\n";
        }
        
        $count++;
    }
}




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