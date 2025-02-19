<?php 

$data = generate_json('CHRE.csv');
$hoards = array();

foreach ($data as $row) {
    $id = $row['Hoard URI'];
    
    //insert hoard metadata into $hoards array
    if (!array_key_exists($id, $hoards)) {
        $hoards[$id] = array($id, $row['find_spot_name'], $row['Findspot URI'], $row['fcl'], $row['fcode']);
    }
}

$fp = fopen('geonames.csv', 'w');
fputcsv($fp, array('URI', 'find_spot_name', 'Findspot URI', 'fcl', 'fcode'));
foreach ($hoards as $hoard) {
    fputcsv($fp, $hoard, ',', '"', '');
}

fclose($fp);

/*****
 * Functions
 *****/

/*****
 * CSV Parsing
 *****/

//parse CSV
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