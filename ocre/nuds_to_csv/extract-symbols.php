<?php 

$handle = opendir('Excel');

$array = array();

if ($handle) {
    while (($file = readdir($handle)) !== FALSE) {
        if (strpos($file, '.csv') !== FALSE){
            $data = generate_json('Excel/' . $file);
            
            foreach ($data as $row){
                $line = array();
                
                $line['id'] = $row['Nomisma.org id'];
                
                //obverse control mark
                if (array_key_exists('Control Mark', $row)){
                    if (strlen($row['Control Mark']) > 0){
                       $line['obvSymbol'] = trim($row['Control Mark']);
                    } else {
                        $line['obvSymbol'] = '';
                    }
                } elseif (array_key_exists('Parent Control Mark', $row)){
                    if (strlen($row['Parent Control Mark']) > 0){
                        $line['obvSymbol'] = trim($row['Parent Control Mark']);
                    } else {
                        $line['obvSymbol'] = '';
                    }
                } else {
                    $line['obvSymbol'] = '';
                }
                
                //Officina Mark
                if (array_key_exists('Officina Mark', $row)){
                    if (strlen($row['Officina Mark']) > 0){
                        $line['R: officinaMark'] = trim($row['Officina Mark']);
                    } else {
                        $line['R: officinaMark'] = '';
                    }
                } else {
                    $line['R: officinaMark'] = '';
                }
                
                //Parent Mint Mark
                if (array_key_exists('Parent Mint Mark', $row)){
                    if (strlen($row['Parent Mint Mark']) > 0){
                        $line['R: mintMark'] = trim($row['Parent Mint Mark']);
                    } else {
                        $line['R: mintMark'] = '';
                    }
                } else {
                    $line['R: mintMark'] = '';
                }
                
                //leftField
                if (array_key_exists('Mint Mark(s) Left', $row)){
                    if (strlen($row['Mint Mark(s) Left']) > 0){
                        $line['R: leftField'] = trim($row['Mint Mark(s) Left']);
                    } else {
                        $line['R: leftField'] = '';
                    }
                } else {
                    $line['R: leftField'] = '';
                }
                
                //center
                if (array_key_exists('Mint Mark(s) Center', $row)){
                    if (strlen($row['Mint Mark(s) Center']) > 0){
                        $line['R: center'] = trim($row['Mint Mark(s) Center']);
                    } else {
                        $line['R: center'] = '';
                    }
                } else {
                    $line['R: center'] = '';
                }
                
                //rightField
                if (array_key_exists('Mint Mark(s) Right', $row)){
                    if (strlen($row['Mint Mark(s) Right']) > 0){
                        $line['R: rightField'] = trim($row['Mint Mark(s) Right']);
                    } else {
                        $line['R: rightField'] = '';
                    }
                } else {
                    $line['R: rightField'] = '';
                }
                
                //exergue
                if (array_key_exists('Mint Mark(s) Exergue', $row)){
                    if (strlen($row['Mint Mark(s) Exergue']) > 0){
                        $line['R: exergue'] = trim($row['Mint Mark(s) Exergue']);
                    } else {
                        $line['R: exergue'] = '';
                    }
                } else {
                    $line['R: exergue'] = '';
                }
                
                if (array_key_exists('Monogram URI', $row)){
                    if (strlen($row['Monogram URI']) > 0){
                       $line['monogram'] =  trim($row['Monogram URI']);
                    } else {
                        $line['monogram'] = '';
                    }
                } else {
                    $line['monogram'] = '';
                }
                
                if (array_key_exists('Obverse notes', $row)){
                    if (strlen($row['Obverse notes']) > 0){
                        $line['Obverse notes'] =  trim($row['Obverse notes']);
                    } else {
                        $line['Obverse notes'] = '';
                    }
                } else {
                    $line['Obverse notes'] = '';
                }
                
                if (array_key_exists('Reverse notes', $row)){
                    if (strlen($row['Reverse notes']) > 0){
                        $line['Reverse notes'] =  trim($row['Reverse notes']);
                    } else {
                        $line['Reverse notes'] = '';
                    }
                } else {
                    $line['Reverse notes'] = '';
                }
                
                $array[] = $line;
            }
        }       
    }
}

closedir($handle);

//write CSV

$fp = fopen('ric-symbols.csv', 'w');

fputcsv($fp, array('id', 'obvSymbol', 'R: officinaMark', 'R: mintMark', 'R: leftField', 'R: center', 'R: rightField', 'R: exergue', 'monogram', 'obvNotes', 'revNotes'));

foreach ($array as $row) {    
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