<?php 
/*** Compare the filenames from the binder images downloaded from the server with filenames that had 
 * previously been parsed by PHP in order to generate the TEI file. There is a difference in some 30 files
 */

$data = generate_json('concordance.csv');

$myFile = "files.list";
$fh = fopen($myFile, "r");
if ( $fh ) {
    while ( !feof($fh) ) {
        $line = fgets($fh);
        $line = str_replace("\n", "", $line);
        $exists = false;
        foreach ($data as $row){
            if ($row['old_file'] == $line){
                $exists = true;
                break;
            }
        }
        
       if ($exists == false){
            echo $line . "\n";
        }
        
    }
    fclose($fh);
}


/***** CSV FUNCTIONS *****/
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

function url_exists($url) {
    if (!$fp = curl_init($url)) return false;
    return true;
}


?>