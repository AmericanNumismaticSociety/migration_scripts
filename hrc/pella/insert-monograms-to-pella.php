<?php 

/***
 * Author: Ethan Gruber
 * Date: May 2020
 * Replace the word monogram with the appropriate monogram URI (if there's a corresponding SVG file), and return a list of errors for missing files
 ***/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTeZzir8n5rwe8TAHQ5wuAvCUpVfEZVInkTHb90UkgPJgjApj1sawz7PIw7J0-dXJ_9lIPokKdH5Bax/pub?output=csv');
$concordance = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vSXsS1RSt4IIe5EQ-D1OG77UQzg68giPSJM0b9k3BWWyDKeJ7GUnq7dFrBMad1imoHl04HjYjhGyoMz/pub?output=csv');
$files = scandir('/usr/local/projects/migration_scripts/fonts/svg/Price');

//var_dump($files);
$objects = array();
$errors = array();

foreach ($data as $row){
    $object = array();
    $id = $row['Price no.'];    
    
    //if ($id == '606'){
        foreach ($row as $k=>$v){
            $v = trim($v);
            
            //read symbols
            if (strpos($k, 'R:') !== FALSE || strpos($k, 'O:') !== FALSE){
                
                //attempt to replace the monogram (if there's an SVG file on disk) with the correct ID
                if ($v == 'monogram'){
                    //echo "{$id}: {$v}\n";
                    
                    foreach ($concordance as $m){
                        if ($m['priceno'] == $id){
                            
                            //skip blanks
                            if (strlen($m['position']) > 0 && strlen($m['monogram']) > 0){
                                $monogram = "monogram.price.{$m['monogram']}";
                                $file = $monogram . ".svg";
                                
                                if (!in_array($file, $files)){                                   
                                    $message = "{$id}: {$k} - no file for {$monogram}";
                                    $errors[] = array($id, $monogram, $k);
                                    echo "{$message}\n";
                                    
                                    //reset value of the cell to 'monogram'
                                    $object[$m['position']][] = 'monogram';
                                    break;
                                } else {
                                    $monogram = "http://numismatics.org/pella/symbol/" . $monogram;
                                    
                                    //insert the monogram ID into the array, only if it's not already in it (prevents duplicates when the position contains more than 1 monogram
                                    if (array_key_exists($m['position'], $object)){
                                        if (!in_array($monogram, $object[$m['position']])){
                                            $object[$m['position']][] = $monogram;
                                            break;
                                        }                                        
                                    } else {
                                        $object[$m['position']][] = $monogram;
                                        break;
                                    }
                                }
                            } else {
                                //read the key and insert the value into an array pertaining to the position
                                $position = get_position($k);
                                $object[$position][] = $v;
                                break;
                            }
                        }
                    }
                } else {
                    //read the key and insert the value into an array pertaining to the position
                    $position = get_position($k);
                    $object[$position][] = $v;
                }
            } else {
               $object[$k] = $v;
            }
        }       
        $line = array();
        foreach ($object as $k=>$v){
            if (is_array($v)){
                foreach ($v as $symbol){
                    $line[] = $symbol;
                }
            } else {
                $line[] = $v;
            }
        }
        
        $objects[] = $line;
        //var_dump($line);
    //}
}


//output csv

//create header
$header = array();
foreach ($data[0] as $k=>$v){
    $header[] = $k;
}

array_unshift($objects, $header);

$fp = fopen('price-new.csv', 'w');
foreach ($objects as $line) {
    fputcsv($fp, $line);
}
fclose($fp);
/*
$fp = fopen('monogram-errors.csv', 'w');
array_unshift($errors, array('Price no.', 'Monogram ID', 'Position'));
foreach ($errors as $line) {
    fputcsv($fp, $line);
}
fclose($fp);
*/
//var_dump($errors);

function get_position($k){
    
    switch($k){
        case 'R: <LF>1':
        case 'R: <LF>2':
        case 'R: <LF>3':
        case 'R: <LF>4':
            $position = 'lf';
            break;
        case 'R: <TH>1':
        case 'R: <TH>2':
        case 'R: <TH>3':
            $position = 'th';
            break;
        case 'R: <EX>1':
        case 'R: <EX>2':
        case 'R: <EX>3':
            $position = 'ex';
            break;
        case 'R: <RF>1':
        case 'R: <RF>2':
            $position = 'rf';
            break;
        case 'R: Above1':
        case 'R: Above2':
            $position = 'above';
            break;
        case 'R: Below1':
        case 'R: Below2':
            $position = 'below';
            break;
        case 'R: <LW>1':
        case 'R: <LW>2':
            $position = 'lw';
            break;
        case 'R: <RW>1':
        case 'R: <RW>2':
            $position = 'rw';
            break;
        default:
            $position = $k;
    }
    
    return $position;    
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