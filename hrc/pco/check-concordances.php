<?php 

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQ6KOO3NMCVf8YebxKYXF1g5x-r3n1mDoSXkz7RPecj-UFWkezPnmDS6UzkLqGdAMZuJGo4FgoiYHug/pub?output=csv');
$ans_sv = generate_json('svoronos-concordances/svoronos-nos.csv');
$concordance = generate_json('svoronos-concordances/concordance.csv');

$numbers = array();

foreach ($ans_sv as $ref){
    $num = str_replace('Sv ', '', $ref['Number']);
    
    $found = false;
    foreach ($concordance as $row){
        $id = str_replace('svoronos.', '', $row['Svoronos']);
        
       
        if ($num == $id){
           $found = true;
        }
    }
    
    $numbers[$num] = $found;
    unset($found);
}

foreach ($numbers as $k=>$v){
    if ($v == false){
        echo "{$k}\n";
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