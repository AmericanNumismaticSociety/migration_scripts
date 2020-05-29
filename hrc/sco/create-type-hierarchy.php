<?php 
/*****
 * Author: Ethan Gruber
 * Date: October 2018
 * Function: Read the SC spreadsheet and insert  new rows for parent types, when needed, and insert references to parent types in subtype rows
 *****/

$data = generate_json('sco.csv');
$hier = array();

foreach ($data as $row){
    $id = $row['SC no.'];
    $parentId = null;
    
    $arr = array();
    $arr['type'] = $row['Type Number'];    
    
    if (strlen($row['Subtype']) > 0){
        $arr['subtype'] = $row['Subtype'];
        $parentId = 'sc.1.' . $arr['type'];    
        $arr['parent'] = $parentId;
        
        if (!array_key_exists($parentId, $hier)){
            
            //generate a new typology array, blanking cells for symbol positions            
            $type = generate_type($parentId, $row);            
            
            $typology = array_slice($type, 0, 2, true) +
            array("parent" => "") +
            array_slice($type, 2, count($type) - 1, true) ;
            
            $hier[$parentId] = array('parent'=>'', 'typology'=>$typology);
        }
    }
    
    if (strlen($row['Subtype Letter']) > 0){
        $arr['letter'] = $row['Subtype Letter'];
        
        if (array_key_exists('subtype', $arr)){
            $parentId = 'sc.1.' . $arr['type'] . '.' . $arr['subtype'];
            $arr['parent'] = $parentId;
            
            if (!array_key_exists($parentId, $hier)){
                //generate a new typology array, blanking cells for symbol positions
                $type = generate_type($parentId, $row);
                $grandParent = 'sc.1.' . $arr['type'];
                
                $typology = array_slice($type, 0, 2, true) +
                array("parent" => $grandParent) +
                array_slice($type, 2, count($type) - 1, true) ;
                
                $hier[$parentId] = array('parent'=>$grandParent, 'typology'=>$typology);
            }
            
        } else {
            $parentId = 'sc.1.' . $arr['type'];
            $arr['parent'] = $parentId;
            
            if (!array_key_exists($parentId, $hier)){
                //generate a new typology array, blanking cells for symbol positions
                $type = generate_type($parentId, $row);
                
                $typology = array_slice($type, 0, 2, true) +
                array("parent" => "") +
                array_slice($type, 2, count($type) - 1, true) ;
                
                $hier[$parentId] = array('parent'=>'', 'typology'=>$typology);
            }
        }
        
        //look to see if there's a parent
        
        
    }
    
    //determine parent
    
    if (isset($parentId)){
        $typology = array_slice($row, 0, 2, true) +
        array("parent" => $parentId) +
        array_slice($row, 2, count($row) - 1, true) ;
    } else {
        $typology = array_slice($row, 0, 2, true) +
        array("parent" => "") +
        array_slice($row, 2, count($row) - 1, true) ;
    }
    
   $arr['typology'] = $typology;
   $hier[$id] = $arr;
    
   unset($parentId);
   unset($typology);
}

//var_dump($hier);

//write new hierarchy and typologies to CSV
$fp = fopen('new-sco.csv', 'w');

foreach ($hier as $types) {   
    fputcsv($fp, $types['typology']);
}

fclose($fp);


/***** FUNCTIONS *****/
function generate_type($parentId, $row){
    $type = array();
    
    foreach ($row as $k=>$v){
        if (strpos($k, 'OBV') !== FALSE){
            $type[$k] = '';
        } else if (strpos($k, 'R:') !== FALSE){
            $type[$k] = '';
        } else if ($k == 'SC no.') {
            $type[$k] = $parentId;
        } elseif ($k == 'Subtype' || $k == 'Subtype Letter' ||  $k == 'Deprecated ID') {
            $type[$k] = '';
        } else {
            $type[$k] = trim($v);
        }
    }
    
    return $type;
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