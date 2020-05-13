<?php 

/***
 * Author: Ethan Gruber
 * Date: May 2020
 * Process a list of ANS coins with Hadrian ed. 1 type URIs, and map to ed. 1 URIs
 ***/

//$coins = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vScAnnTwVYZWS-fLGSZN_T4zGb-f8-pEvaKT4tI2I_fZnX0xd2rLlWQdu6CRQKnb7bkA0bHhtdr0yJx/pub?output=csv');
$coins = generate_json('all-hadrian.csv');
$types = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQvfKQcmqOyIGhGzfWqVGUka8Lx3gx6923h3CZRVkzd9demKe3zzJqIYv-M8XxjIhAPMBXsw994E13O/pub?output=csv');

$concordance = array();
$objects = array();

//generate a 1:n RIC II.3 to II concordance
foreach ($types as $row){
    if (strlen($row['New Nomisma.org id']) > 0){
        if (strpos($row['New Nomisma.org id'], '|') !== FALSE){
            $ids = explode('|', trim($row['New Nomisma.org id']));
            
            if (strlen($row['Old Nomisma.org id']) > 0){
                $concord = parse_concord(trim($row['Old Nomisma.org id']));
                
                foreach ($ids as $id){
                    $concordance[$id] = $concord;    
                }
                unset($concord);
            }
           
        } else {
            $id = trim($row['New Nomisma.org id']);
            if (strlen($row['Old Nomisma.org id']) > 0){
                $concord = parse_concord(trim($row['Old Nomisma.org id']));
                $concordance[$id] = $concord;    
                unset($concord);
            }
        }
    }
}

//var_dump($concordance);

//iterate through coin list
foreach ($coins as $row){
    $typeID = explode('/', $row['type'])[5];
    
    $object = array($row['coin'], $row['identifier'], $row['collection'], $row['dataset'], $typeID);    
    
    
    $newType = array();
    
    foreach ($concordance as $k=>$v){
        if (in_array($typeID, $v)){            
            $newType[] = $k;
        }
    }
    
    $object[] = implode('|', $newType);
    $objects[] = $object;
}

//var_dump($objects);

array_unshift($objects, array('coin', 'id', 'collection', 'dataset', 'old', 'new'));

$fp = fopen('concordance.csv', 'w');
foreach ($objects as $line) {
    fputcsv($fp, $line);
}
fclose($fp);

/***** FUNCTIONS *****/
function parse_concord($val){
    if (strpos($val, '|') !== FALSE){
        return explode('|', $val);
    } else {
        return array($val);
    }
}

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

?>