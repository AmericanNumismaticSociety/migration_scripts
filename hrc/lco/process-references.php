<?php 

$data = generate_json('types.csv');
$reference_csv = generate_json('references.csv');
$ref_list = array();
$new_references = array();

//set up array of reference abbreviations
foreach ($reference_csv as $row) {
    $ref_list[] = $row['Abbreviation'];
}

foreach ($data as $row) {
    $id = $row['ID'];
    
    if (strlen($row['Reference']) > 0) {
        $refs = explode(';', $row['Reference']);
        
        foreach ($refs as $ref) {
            $ref = trim($ref);
            
            foreach ($ref_list as $abbr) {
                preg_match('/(' . $abbr . '):?\s(.*)/', $ref, $matches);
                
                if (isset($matches[1])) {
                    $new_references[$id][] = array($matches[1], $matches[2]);
                }
            }
        }
    }
}

//var_dump($new_references);
$fp = fopen('out.csv', 'w');
array_unshift($ref_list, 'ID');

$headings = array();
$headings[] = 'ID';
foreach ($ref_list as $ref) {
    $headings[] = "Reference (" . $ref . ")";
}

fputcsv($fp, $headings);

foreach ($data as $row) {
    $id = $row['ID'];
    $line = array();
    $line[] = $id;
    
    foreach ($ref_list as $ref) {
        foreach ($new_references[$id] as $refs) {
            if ($refs[0] == $ref) {
                $val = $refs[1];
            }
        }
        
        if (isset($val)) {
            $line[] = $val;
        } else {
            $line[] = '';
        }
        
        unset($val);
    }
    
    fputcsv($fp, $line);
    unset ($line);
}
fclose($fp);


//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
    if ($number > 0){
        $gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
    } elseif ($number < 0) {
        $gYear = '-' . str_pad((int) abs($number),$n,"0",STR_PAD_LEFT);
    }
    return $gYear;
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