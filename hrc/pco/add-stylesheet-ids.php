<?php 

/*****
 * Author: Ethan Gruber
 * Date: December 2018
 * Function: add the stylesheet IDs for type descriptions back into the master CSV document
 *****/


$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQ6KOO3NMCVf8YebxKYXF1g5x-r3n1mDoSXkz7RPecj-UFWkezPnmDS6UzkLqGdAMZuJGo4FgoiYHug/pub?output=csv');
$stylesheet = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQ9dm5-zYlLzEm_URfzguJhQr_VT0Xt5uh7qAQTK23YKzwNUvyRQO0LgcihP18h4JRTVD41C-6PZf9c/pub?output=csv');

$csv = array();

foreach ($data as $row){
   $obv = $row['O (en)'];
   $rev = $row['R (en)'];
   
   if (strlen($obv) > 0){
   	foreach ($stylesheet as $desc){
   		if ($desc['ORIGINAL'] == $obv){
   			$row['O'] = $desc['Abbreviation'];
   		}
   	}
   }
   
   if (strlen($rev) > 0){
   	foreach ($stylesheet as $desc){
   		if ($desc['ORIGINAL'] == $rev){
   			$row['R'] = $desc['Abbreviation'];
   		}
   	}
   }
   
   $csv[] = $row;
}

//var_dump($csv);


//write CSV
$file = fopen('stylesheet.csv', 'w');
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