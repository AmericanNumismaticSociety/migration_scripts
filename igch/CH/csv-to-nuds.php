<?php 
/*****
 * Author: Ethan Gruber
 * Date: December 2024
 * Function: Convert CH spreadsheets into NUDS/XML for coinhoards.org
 *****/

$data = generate_json('Asia_Minor_Hoards.csv');
$findspots = generate_json('Findspots_CH.csv');
$counts_csv = generate_json('Hoard_Total_Counts_CH.csv');
$contents_csv = generate_json('Hoard_contents_CH.csv');

//generate an array of duplicate hoards to exclude
$duplicates = find_duplicates($data);

//generate concordance structure of hoards between CH volumes and IGCH
$hoards = generate_concordance($data, $duplicates);

foreach ($hoards as $id=>$hoard) {
    
    //iterate through findspots CSV and look for the CH ID that matches the $hoard ID
    foreach ($findspots as $row) {
        foreach ($row as $k=>$v) {
            if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                if ($v == $id) {
                    
                    if (strlen($row['Period']) > 0) {
                        $hoards[$id]['period'] = $row['Period'];
                    }
                    
                    $findspot = array();
                    $findspot['name'] = $row['Name'];
                    
                    if (strlen($row['Canonical Geonames URI'] > 0)) {
                        $findspot['geonames'] = trim($row['Canonical Geonames URI']);
                    }
                    if (strlen($row['Pleiades URI'] > 0)) {
                        $findspot['pleiades'] = trim($row['Pleiades URI']);
                    }
                    
                    //discovery
                    if (strlen($row['Discovery Date Start']) > 0) {
                        $findspot['discovery']['start'] = $row['Discovery Date Start'];
                    }
                    if (strlen($row['Discovery Date End']) > 0) {
                        $findspot['discovery']['end'] = $row['Discovery Date End'];
                    }
                    if ($row['Discovery Date Uncertain'] == 'true') {
                        $findspot['discovery']['uncertain'] = true;
                    }
                    
                    //burial
                    if (strlen($row['Burial Date Start']) > 0) {
                        $findspot['burial']['start'] = $row['Burial Date Start'];
                    }
                    if (strlen($row['Burial Date End']) > 0) {
                        $findspot['burial']['end'] = $row['Burial Date End'];
                    }
                    
                    $hoards[$id]['findspot'] = $findspot;
                    unset($findspot);
                }
            }
        }
    } // end findspots
    
    //process total counts    
    foreach ($counts_csv as $row) {
        foreach ($row as $k=>$v) {
            if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                if ($v == $id) {
                    $counts = array();
                    
                    if (strlen($row['Contents']) > 0) {
                        $counts['description'] = trim($row['Contents']);
                    }
                    
                    if (is_numeric($row['Total count'])) {
                        $counts['count'] = trim($row['Total count']);
                    }                    
                    if (is_numeric($row['Min. count'])) {
                        $counts['minCount'] = trim($row['Min. count']);
                    }
                    if (is_numeric($row['Max. count'])) {
                        $counts['maxCount'] = trim($row['Max. count']);
                    }
                    if ($row['Approximate'] == 'yes') {
                        $counts['approximate'] = true;
                    }
                    
                    
                    $hoards[$id]['counts'] = $counts;
                }
            }
        }
    } //end counts
    
    //contents
    $contents = array();
    foreach ($contents_csv as $row) {
        foreach ($row as $k=>$v) {
            if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                if ($v == $id) {
                    $group = array();
                    //counts
                    if (is_numeric($row['Coin count'])) {
                        //echo $row['Coin count'] . "\n";
                        $group['count'] = trim($row['Coin count']);
                    }
                    if (is_numeric($row['min coin count'])) {
                        $group['minCount'] = trim($row['min coin count']);
                    }
                    if (is_numeric($row['max coin count'])) {
                        $group['maxCount'] = trim($row['max coin count']);
                    }
                    //uncertainty
                    if ($row['coin count approximate'] == 'TRUE') {
                        $group['approximate'] = true;
                    }
                    if ($row['coin count uncertain'] == 'TRUE') {
                        $group['uncertain'] = true;
                    }
                    
                    //Nomisma types
                    //geographic
                    if (strpos($row['Mint 1 URI'], 'nomisma.org') !== FALSE) {
                        $group['mint'][] = trim($row['Mint 1 URI']);
                    }
                    if (strpos($row['Mint 2 URI'], 'nomisma.org') !== FALSE) {
                        $group['mint'][] = trim($row['Mint 2 URI']);
                    }
                    if ($row['mint uncertain'] == 'TRUE') {
                        $group['mint_uncertain'] = true;
                    }
                    if (strpos($row['Region 1 URI'], 'nomisma.org') !== FALSE) {
                        $group['region'][] = trim($row['Region 1 URI']);
                    }
                    if (strpos($row['Region 2 URI'], 'nomisma.org') !== FALSE) {
                        $group['region'][] = trim($row['Region 2 URI']);
                    }
                    if (strpos($row['Region 3 URI'], 'nomisma.org') !== FALSE) {
                        $group['region'][] = trim($row['Region 3 URI']);
                    }
                    
                    //authority
                    if (strpos($row['Authority 1 URI'], 'nomisma.org') !== FALSE) {
                        $group['authority'][] = trim($row['Authority 1 URI']);
                    }
                    if (strpos($row['Authority 2 URI'], 'nomisma.org') !== FALSE) {
                        $group['authority'][] = trim($row['Authority 2 URI']);
                    }
                    if ($row['authority uncertain'] == 'TRUE') {
                        $group['authority_uncertain'] = true;
                    }
                    
                    if (strpos($row['Stated authority'], 'nomisma.org') !== FALSE) {
                        $group['authority'][] = trim($row['Stated authority']);
                    }
                    if (strpos($row['Dynasty URI'], 'nomisma.org') !== FALSE) {
                        $group['authority'][] = trim($row['Dynasty URI']);
                    }
                    
                    //material
                    if (strpos($row['Material 1 URI'], 'nomisma.org') !== FALSE) {
                        $group['material'][] = trim($row['Material 1 URI']);
                    }
                    if (strpos($row['Material 2 URI'], 'nomisma.org') !== FALSE) {
                        $group['material'][] = trim($row['Material 2 URI']);
                    }
                    if ($row['material uncertain'] == 'TRUE') {
                        $group['material_uncertain'] = true;
                    }
                    
                    //denomination
                    if (strpos($row['Denomination 1 URI'], 'nomisma.org') !== FALSE) {
                        $group['denomination'][] = trim($row['Denomination 1 URI']);
                    }
                    if (strpos($row['Denomination 2 URI'], 'nomisma.org') !== FALSE) {
                        $group['denomination'][] = trim($row['Denomination 2 URI']);
                    }
                    if ($row['denomination uncertain'] == 'TRUE') {
                        $group['denomination_uncertain'] = true;
                    }
                    
                    if (strpos($row['Authenticity'], 'nomisma.org') !== FALSE) {
                        $group['authenticity'] = trim($row['Authenticity']);
                    }
                    
                    //add coinGrp into $contents array
                    $contents[] = $group;
                    
                }
            }
        }
    } //end contents
    
    $hoards[$id]['contents'] = $contents;
    
    unset($contents);
}

var_dump($hoards);



/*****
 * FUNCTIONS *
*****/

//generate concordance structure between hoards
function generate_concordance($data, $duplicates) {
    
    $hoards = array();
    
    foreach ($data as $row) {
        foreach ($row as $k=>$v) {
            if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                $id = trim($v);
                
                //create ID records only for unique IDs and generate concordances
                if (strpos($id, '|') !== FALSE) {
                    $ids = explode('|', $id);
                    
                    foreach ($ids as $item) {
                        if (!in_array($item, $duplicates)) {
                            $concordance = match_hoard($id, $row, $duplicates);
                            
                            if (array_key_exists('replaces', $concordance)) {
                                $hoards[$item]['replaces'] = $concordance['replaces'];
                            }
                            if (array_key_exists('replacedBy', $concordance)) {
                                $hoards[$item]['replacedBy'] = $concordance['replacedBy'];
                            }
                        }
                    }
                } else {
                    if (!in_array($id, $duplicates)) {
                        $concordance = match_hoard($id, $row, $duplicates);
                        
                        if (array_key_exists('replaces', $concordance)) {
                            $hoards[$id]['replaces'] = $concordance['replaces'];
                        }
                        if (array_key_exists('replacedBy', $concordance)) {
                            $hoards[$id]['replacedBy'] = $concordance['replacedBy'];
                        }
                    }
                }
                
                if (strlen($row['IGCH']) > 0) {
                    $igch_nums = explode(',', $row['IGCH']);
                    
                    foreach ($igch_nums as $igch_num) {
                        $hoards[$id]['igch'][] = 'igch' . trim($igch_num);
                    }
                }
            }
        }
    }
    
    return $hoards;
}

//find CH hoards which appear more than once in the spreadsheet, suggesting single bibliographic entries citing multiple hoards
function find_duplicates($data) {    
    $dup = array();
    
    foreach ($data as $row) {
        foreach ($row as $k=>$v) {
            if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
                $id = trim($v);
                
                if (strpos($id, '|') !== FALSE) {
                    $ids = explode('|', $id);
                    
                    foreach ($ids as $item) {
                        if (!array_key_exists($item, $dup)) {
                            $dup[$item] = 1;
                        } else {
                            $dup[$item] = $dup[$item] + 1;
                        }                    }
                        
                } else {
                    if (!array_key_exists($id, $dup)) {
                        $dup[$id] = 1;
                    } else {
                        $dup[$id] = $dup[$id] + 1;
                    }
                }
            }
        }
    }
    
    foreach ($dup as $k=>$v) {
        if ($v > 1) {
            $duplicates[] = $k;
        }
    }
    
    return $duplicates;
    
}

function match_hoard($id, $row, $duplicates) {
    $concordance = array();   
        
    foreach ($row as $k=>$v) {
        if (substr($k, 0, 2) == 'CH' && strlen(trim($v)) > 0) {
            
            $oid = trim($v);
            
            if (strpos($oid, '|') !== FALSE) {
                $pieces = explode('|', $oid);
                
                foreach ($pieces as $piece) {
                    $concordance['replaces'][] = $piece;
                }
            } else {
                if ($oid < $id) {
                    $concordance['replaces'][] = $oid;
                } elseif ($oid > $id) {
                    $concordance['replacedBy'][] = $oid;
                }
            }
        }
    }
    
    return $concordance;
}

/*****
 * Date Parsing
 *****/

//generate human-readable date based on the integer value
function get_date_textual($year){
    $textual_date = '';
    //display start date
    if($year < 0){
        $textual_date .= abs($year) . ' BCE';
    } elseif ($year > 0) {
        if ($year <= 600){
            $textual_date .= $year . ' CE';
        }
        $textual_date .= $year;
    }
    return $textual_date;
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
    if ($number > 0){
        $gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
    } elseif ($number < 0) {
        $bcNum = (int)abs($number);
        $gYear = '-' . str_pad($bcNum,$n,"0",STR_PAD_LEFT);
    }
    return $gYear;
}

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