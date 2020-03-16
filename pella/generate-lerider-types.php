<?php 

//CSV arrays
$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQjTTBeyTMrBO0n3uiwVlxDSq1umrDgPCP2XHU7DiW3dcYwg9TXp0RhW2mJzISa5ALp5QTmGwSo_CVP/pub?output=csv');
$deities = generate_json('https://docs.google.com/spreadsheet/pub?hl=en_US&hl=en_US&key=0Avp6BVZhfwHAdHk2ZXBuX0RYMEZzUlNJUkZOLXRUTmc&single=true&gid=0&output=csv');

$objects = array();

$concordance = array();

//evaluate 1:n relations between PELLA and LeRider IDs to use matching or broader
foreach ($data as $row){
    $pellaID = "pella.philip_ii.{$row['PELLA Type no.']}";
    
    $concordance[$pellaID][] = $row['LeRider no.'];
}

//var_dump($concordance);


foreach ($data as $row){
    $pellaID = "pella.philip_ii.{$row['PELLA Type no.']}";
    
    if (!in_array($pellaID, $objects)){
        $pella = array();
        
        $pella['ID'] = $pellaID;
        
        //insert blanks
        $pella['parent'] = '';
        $pella['match'] = (count($concordance[$pellaID]) == 1 ? $concordance[$pellaID][0] : '');
        $pella['replacedBy'] = '';
        $pella['LeRider Group no.'] = '';
        $pella['Die Combo'] = '';
        $pella['Plate, Figure'] = '';
        
        foreach ($row as $k=>$v){
            switch ($k){
                case 'PELLA Type no.':
                case 'LeRider Group no.':
                case 'LeRider no.':
                case 'Die Combo':
                case 'Plate, Figure':
                    break;
                case 'O (en)':
                    $pella[$k] = $v;
                    $pella['Obverse Deity'] = extract_deities($v);
                    break;
                case 'R (en)':
                    $pella[$k] = $v;
                    $pella['Reverse Deity'] = extract_deities($v);
                    break;
                default:
                    $pella[$k] = $v;
                    
            }
        }
        
        $objects[$pellaID] = $pella;
    }
    
    $lerider = array();
    $lerider['ID'] = $row['LeRider no.'];
    
    //determine whether it is a 1:1 match for a PELLA ID or a subtype    
    if (count($concordance[$pellaID]) == 1){
        $lerider['parent'] = '';
        $lerider['match'] = $pellaID;
        $lerider['replacedBy'] = $pellaID;
    } else {
        $lerider['parent'] = $pellaID;
        $lerider['match'] = '';
        $lerider['replacedBy'] = '';
    }
    
    foreach ($row as $k=>$v){
        switch ($k){
            case 'PELLA Type no.':
            case 'LeRider no.':
                break;
            case 'O (en)':
                $lerider[$k] = $v;
                $lerider['Obverse Deity'] = extract_deities($v);
                break;
            case 'R (en)':
                $lerider[$k] = $v;
                $lerider['Reverse Deity'] = extract_deities($v);
                break;
            default:
                $lerider[$k] = $v;
                
        }
    }
    
    $objects[$row['LeRider no.']] = $lerider;
}

$headings = array();

foreach ($objects[array_keys($objects)[0]] as $k=>$v){
    $headings[] = $k;
}

array_unshift($objects, $headings);

//write CSV
$fp = fopen('philip_ii.csv', 'w');
foreach ($objects as $fields) {
    fputcsv($fp, $fields);
}
fclose($fp);

    
/**** FUNCTIONS ****/

function extract_deities($type){
    GLOBAL $deities;
    
    $uris = array();
    
    foreach($deities as $deity){
        if (strstr($deity['name'], ' ') !== FALSE){
            //haystack is string when the deity is multiple words
            $haystack = strtolower(trim($type));
            if (strstr($haystack, strtolower($deity['matches'])) !== FALSE) {
                if (strlen($deity['bm_uri']) > 0){
                    $uris[] = $deity['bm_uri'];
                }
            }
        } else {
            //haystack is array
            $string = preg_replace('/[^a-z]+/i', ' ', trim($type));
            $haystack = explode(' ', $string);
            if (in_array($deity['matches'], $haystack)){
                if (strlen($deity['bm_uri']) > 0){
                    $uris[] = $deity['bm_uri'];
                }
            }
        }
    }
    
    return implode('|', $uris);
}


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