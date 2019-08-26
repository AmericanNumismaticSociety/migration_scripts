<?php 

/************************
 AUTHOR: Ethan Gruber
 MODIFIED: August, 2019
 DESCRIPTION: Merge the current Nomisma spreadsheet with deities extracted from Corpus Nummorum Thracorum
 ************************/

$data = generate_json('nomisma_deities.csv');
$cnt = generate_json("cnt_deities.csv");

var_dump($cnt);

foreach ($data as $row){
    //echo "{$k}\n";
    
    $label = $row['prefLabel_en'];
    $id = $row['ID'];
    
    //locate matching column, if available
    foreach ($cnt as $k=>$deity){
        if (strlen($deity['sub_instance']) > 0){
            $match = $deity['sub_instance'];
        } else {
            $match = $deity['instance'];
        }
        
        if ($match == $label){
            //insert the Nomisma ID back into the CNT array
            $cnt[$k]['Nomisma ID'] = $id;
            //echo "{$deity['internID']}\n";
            break;
        }
    }
}

//insert metadata into Nomisma sheet
foreach ($data as $k=>$row){
    $id = $row['ID'];
    
    foreach ($cnt as $deity){
        if (array_key_exists('Nomisma ID', $deity) && $deity['Nomisma ID'] == $id){
            $data[$k]['sex'] = $deity['cat_i'];
            $data[$k]['role1'] = $deity['cat_ii'];
            
            if (strlen($deity['cat_iii']) > 0){
                $data[$k]['role2'] = $deity['cat_iii'];
            }
            if (strlen($deity['cat_iv']) > 0){
                $data[$k]['role3'] = $deity['cat_iv'];
            }
        }
    }
}

var_dump($data);

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