<?php 
/*****
 * Author: Ethan Gruber
 * Date: August 2020
 * Function: Read a directory of CSV files to compile a data object of image ID -> Crawford numbers.
 *****/

$dir = 'csv';
$list = scandir($dir);

$object = array();
$coinTypes = array();
$errors = array();

//var_dump($files);

$count = 0;
foreach ($list as $item){
    if (strpos($item, '.csv') !== FALSE){
        //only process Excel files
        $data = generate_json($dir . '/' . $item);
        
        foreach ($data as $row){
            //if ($count <= 5000){
                parse_row($row);
                $count++;
            //}
            
        }
    }
}

//write JSON
$fp = fopen('page-crro-concordance.json', 'w');
fwrite($fp, json_encode($object));
fclose($fp);
//var_dump($object);

$text = implode("\n", $errors);
file_put_contents("errors.txt", $text);

/**** FUNCTIONS ****/
function parse_row($row){
    GLOBAL $object;
    GLOBAL $coinTypes;
    GLOBAL $errors;
    
    $types = array();
    //extract crawford number
    foreach ($row as $k=>$v){
        if (preg_match('/^[C|c]rawford/', $k)){
            $ref = trim($v);
            // /(^\d[^\s]+)/
            if (preg_match('/(^\d$|^\d[^\s]+)/', $ref, $matches)){         
                
                if (isset($matches[1])){
                    $ref = $matches[1];
                    
                    //evaluate RRC IDs that are integers. first check the CRRO URI, and then try to append .1 to it
                    if (is_numeric($matches[1])){
                        $num = $matches[1];
                        
                        //check to see if the plain number exists
                        $uri = "http://numismatics.org/crro/id/rrc-{$num}";
                        
                        //if the URI is not already in the errors array, then perform the lookup
                        if (!in_array($ref, $errors)){
                            if (array_key_exists($uri, $coinTypes)){
                                //echo "Matched {$uri}\n";
                                $types[] = $coinTypes[$uri];
                            } else {
                                $file_headers = @get_headers($uri);
                                if ($file_headers[0] == 'HTTP/1.1 200 OK'){
                                    echo "Found {$uri}\n";
                                    $coinTypes[$uri] = $uri;
                                    $types[] = $uri;
                                } else {
                                    $uri = "http://numismatics.org/crro/id/rrc-{$num}.2";
                                    $file_headers = @get_headers($uri);
                                    //ensure there is no .2 subtype, so that means the integer values refers only to .1
                                    if ($file_headers[0] != 'HTTP/1.1 200 OK'){
                                        $uri = "http://numismatics.org/crro/id/rrc-{$num}.1";
                                        $file_headers = @get_headers($uri);
                                        if ($file_headers[0] == 'HTTP/1.1 200 OK'){
                                            echo "Found {$uri}\n";
                                            $coinTypes[$uri] = $uri;
                                            $types[] = $uri;
                                            
                                            //ensure the lookup is not performed again
                                            $coinTypes["http://numismatics.org/crro/id/rrc-{$num}"] = $uri;
                                        } else {
                                            //report original entry as the error
                                            echo "Not found: http://numismatics.org/crro/id/rrc-{$num}\n";
                                            $errors[] = $ref;
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $pieces = explode('-', $ref);
                        
                        //if there aren't two fragments
                        if (count($pieces) == 2){
                            $subtypes = explode('/', $pieces[1]);
                            
                            //var_dump($matches);
                            
                            foreach ($subtypes as $subtype){
                                $uri = "http://numismatics.org/crro/id/rrc-{$pieces[0]}.{$subtype}";
                                
                                if (array_key_exists($uri, $coinTypes)){
                                    //echo "Matched {$uri}\n";
                                    $types[] = $coinTypes[$uri];
                                } else {
                                    //if the URI is not already in the errors array, then perform the lookup
                                    if (!in_array($ref, $errors)){
                                        $file_headers = @get_headers($uri);
                                        if ($file_headers[0] == 'HTTP/1.1 200 OK'){
                                            echo "Found {$uri}\n";
                                            $coinTypes[$uri] = $uri;
                                            $types[] = $uri;
                                            
                                        } else {
                                            echo "Not found {$uri}\n";
                                            $errors[] = $ref;
                                        }
                                    }
                                }
                            }
                        } else {
                            $errors[] = $ref;
                        }                        
                    }                 
                }
            }            
        }
    }        
    
    //Process binder columns for image files (may have jpg or pdf extension). There may be more than one column for images
    foreach ($row as $k=>$v){
        //read the filenames from the binder columns only. There could be more than one in a spreadsheet
        if (preg_match('/^binder/', $k) || preg_match('/^clipping/', $k)){
            $v = trim($v);
            
            if (strlen($v) > 0){
                $files = explode("\n", $v);
                
                foreach ($files as $v){
                    //isolate the $filename ID number
                    if (strpos($v, '/') !== FALSE) {
                        $pieces = explode('/', $v);
                        $file = $pieces[count($pieces) - 1];
                        $id = explode('.', $file)[0];
                    } elseif (strpos($v, '\\') !== FALSE) {
                        $pieces = explode('\\', $v);
                        $file = $pieces[count($pieces) - 1];
                        $id = explode('.', $file)[0];
                    } else {
                        $id = explode('.', $v)[0];
                    }
                }
                
                //replace white space with underscore in filenames
                $id = str_replace(' ', '_', $id);
                
                //if the ID is not in the overall $objects array
                if (!array_key_exists($id, $object)){
                    //activate an array with the $id key
                    $object[$id] = array();
                    foreach ($types as $type){
                        if (!in_array($type, $object[$id])){
                            $object[$id][] = $type;
                        }
                    }
                } else {
                    //if the array key already exists, then add new type URIs
                    foreach ($types as $type){
                        if (!in_array($type, $object[$id])){
                            $object[$id][] = $type;
                        }
                    }
                }
            }           
        }
    }
    
    unset($types);
    
    //var_dump($object);
}

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