<?php 

/************************
 AUTHOR: Ethan Gruber
 MODIFIED: August, 2019
 DESCRIPTION: Compare CSV originally exported fromthe Boston MFA to the CSV exported from OpenRefine in order to locate
 accession numbers that had trailing zeros removed when loading the file in LibreOffice Calc
 ************************/
$old = generate_json("MFARomanCoinage-fixed.csv");
$new = generate_json("mfa-complete.csv");
$ids = generate_json('ids.csv');

$fixes = array();
//var_dump($old[519]);

foreach ($new as $row){
    $acc = (string) $row['Object No.'];
    foreach ($old as $oldrow) {
        $oldacc = (string) $oldrow['Object No.'];
        
        if (strpos($oldacc, $acc) !== FALSE) {
            //echo "{$oldacc}\n";
            if ($oldacc !== $acc){
                $newacc = $acc . "0";                   
                if ($newacc === $oldacc){                    
                    $fixes[$acc] = $newacc;
                } else {
                    $newacc = $acc . "00";
                    if ($newacc === $oldacc){
                        $fixes[$acc] = $newacc;
                    } else {
                        $newacc = $acc . "000";
                        if ($newacc === $oldacc){
                            $fixes[$acc] = $newacc;
                        }
                    }
                }                    
            }
        }
    }
}

$fp = fopen('corrected.csv', 'w');
foreach ($new as $row) {
    $acc = (string) $row['Object No.'];
    
    //add ID
    foreach ($ids as $id){
        if ($id['Object Number'] == $acc){
            $row['ObjectID'] = $id['ObjectID'];
        }
    }
    
    if (array_key_exists($acc, $fixes)){
        echo "{$fixes[$acc]}\n";
        $row['Object No.'] = $fixes[$acc];        
        fputcsv($fp, $row);
    } else {
        fputcsv($fp, $row);
    }
   
}

fclose($fp);



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