<?php 

/******
 * Author: Ethan Gruber
 * Date: May 2020
 * Function: Using a list of BM coins extracted from Nomisma SPARQL, perform lookup on regno in order to derive the new BM Collections 
 * Online URI. Perform JSON API lookup to get new image URLs 
******/

//error_reporting(0);
ini_set("allow_url_fopen", 1);

$data = generate_json('bm-coins-new.csv');
$objects = array();

$count = 1;
foreach ($data as $row){    
    $id = "C_" . str_replace('.', '-', str_replace(',', '-', $row['id']));
    $uri = "https://www.britishmuseum.org/collection/object/{$id}";
    
    if (strlen($row['new_uri']) == 0){
        echo "{$count}: ";
        
        $file_headers = @get_headers($uri);
        if ($file_headers[0] == 'HTTP/1.1 200 OK'){
            $row['new_uri'] = $uri;
            echo $uri . "\n";
            
            //load JSON from API
            $string = file_get_contents("https://www.britishmuseum.org/api/_object?id=" . $id);
            $json = json_decode($string, true);
            
            if (isset($json['hits']['hits'][0]['_source']['multimedia'])){
                
                $images = array();
                
                foreach ($json['hits']['hits'][0]['_source']['multimedia'] as $media){
                    $view = $media['view'];
                    
                    //echo "{$view}\n";
                    
                    foreach ($media['processed'] as $k=>$image){
                        if ($k == 'small' || $k == 'max'){
                            $images[$view . ' ' . $k] = "https://media.britishmuseum.org/media/" . $image['location'];
                        }
                    }
                }
                
                //examine the images and insert into the $objects array
                if (array_key_exists('Obverse small', $images)){
                    $row['obv_thumb'] = $images['Obverse small'];
                } else {
                    $row['obv_thumb'] = '';
                }
                
                if (array_key_exists('Obverse max', $images)){
                    $row['obv_depiction'] = $images['Obverse max'];
                } else {
                    $row['obv_depiction'] = '';
                }
                
                if (array_key_exists('Reverse small', $images)){
                    $row['rev_thumb'] = $images['Reverse small'];
                } else {
                    $row['rev_thumb'] = '';
                }
                
                if (array_key_exists('Reverse max', $images)){
                    $row['rev_depiction'] = $images['Reverse max'];
                } else {
                    $row['rev_depiction'] = '';
                }
                
                if (array_key_exists('Obverse & Reverse small', $images)){
                    $row['combined_thumb'] = $images['Obverse & Reverse small'];
                } else {
                    $row['combined_thumb'] = '';
                }
                
                if (array_key_exists('Obverse & Reverse max', $images)){
                    $row['combined_depiction'] = $images['Obverse & Reverse max'];
                } else {
                    $row['combined_depiction'] = '';
                }
            }
            
        } else {
            "{$row['coin']} does not resolve.\n";
        }
    }
    
    $objects[] = $row;
    $count++;
}

//write CSV

//var_dump($objects);
$header = array();
foreach ($objects[0] as $k=>$v){
    $header[] = $k;
}

$fp = fopen('bm-coins-3.csv', 'w');
fputcsv($fp, $header);
foreach ($objects as $line) {
    fputcsv($fp, $line);
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