<?php 

/*****
 * Author: Ethan Gruber
 * Date: October 2018
 * Function: add the stylesheet IDs for type descriptions back into the master CSV document
 *****/


$data = generate_json('pco.csv');
$stylesheet = generate_json('stylesheet.csv');

$csv = array();

foreach ($data as $row){
   $obv = $row['O (en)'];
   $rev = $row['R (en)'];
   
   if (strlen($obv) > 0){
       foreach ($stylesheet as $desc){
           if ($desc['en'] == $obv){
               $row['O'] = $desc['Abbreviation'];
           }
           
           if ($desc['en'] == $rev){
               $row['R'] = $desc['Abbreviation'];
           }
       }
   }
   
   $csv[] = $row;
}

var_dump($csv);


//write CSV
/*$file = fopen('stylesheet.csv', 'w');
fputcsv($file, array('Abbreviation', 'en'));
foreach ($csv as $fields) {
    fputcsv($file, $fields);
}
fclose($file);*/


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