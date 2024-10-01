<?php 
/*****
 * Author: Ethan Gruber
 * Date: October 2024
 * Function: Reprocess HRC monogram spreadsheets to introduce skos:exactMatch links and dcterms:isReplacedBy links with Nomisma and associated monogram URIs
 *****/

$concordance = generate_json('concordance.csv');

$sheets = array('https://docs.google.com/spreadsheets/d/e/2PACX-1vQwtDwtVbzHmR7keBnmmXEXJjCxxRpP9cW2oNpOaIvwEn8jRkUPQ6Oxk3NJVervb4NISPhagoH95yjN/pub?output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vTbEmgKXqF_jieKQ9zaWeI68mhfGdai_oRuH_OtrokPbuQXQSNI3PW73sTts0_QE8qp_NUH2rvHnKwE/pub?output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vSx_fA9v-jOOcQX24DhSLna5Zyb2GcUBFLKZ3N6Og5OVVwB-I_kaD_Sn7vt0NFW2qGrkG9CAC1FXbAs/pub?output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vR-FNl2QLHs_1UVkcxjEdXJZd_9U-JORGWFFh7FPnd6UUMrfumMmE5E5a7m6wR0U49_PzggXsxVTDLo/pub?output=csv',
    'https://docs.google.com/spreadsheets/d/e/2PACX-1vROXlRvRVhvA-ZAfniXLPjNJ3fd6HJXZVqZMWzIeY35tJ-UgoRLLBucNWf-LOtqa9JVjKxM3PVDAoLl/pub?output=csv'
);

//new concordance array
$new = array();

foreach ($sheets as $sheet) {
    $data = generate_json($sheet);
    
    foreach ($data as $row) {
        $id = $row['ID'];
        
        foreach ($concordance as $con) {
            foreach ($con as $k=>$v) {                
                if (strpos($k, 'exactMatch') !== FALSE && strlen($v) > 0){                    
                    $matchID = array_reverse(explode('/', $v))[0];
                    
                    if ($matchID == $id) {
                        $new[$id]['replacedBy'][] = $con['URI'];
                        
                        //read to see if the other non-matching cells have content, and then add them to matches array
                        foreach ($con as $matchKey=>$matchVal) {
                            if (strpos($matchKey, 'exactMatch') !== FALSE && $matchKey != $k) {
                                if (strlen($matchVal) > 0) {
                                    $new[$id]['matches'][] = $matchVal;
                                }
                            }
                        }
                    }                
                }
            }
        }
    }
}

//write new concordance to CSV
$csv = array();
$csv[] = array('ID', 'replacedBy', 'exactMatch');
foreach ($new as $k=>$row) {
    $field = array($k, implode('|', $row['replacedBy']));
    if (array_key_exists('matches', $row)) {
        $field[] = implode('|', $row['matches']);
    } else {
        $field[] = null;
    }
    $csv[] = $field;
}

$fp = fopen('monogram-matches.csv', 'w');
foreach ($csv as $row) {
    fputcsv($fp, $row);
}
fclose($fp);

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