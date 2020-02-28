<?php 

/*****
 * Author: Ethan Gruber
 * Last modified: February 2020
 * Function: Process a Google Spreadsheet of IGCH Archival Records into TEI files of facsimile images, similar to the Newell notebooks
 *****/

ini_set("allow_url_fopen", 1);

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vTRQbc1C_jdWLdV7jehdctKs7_BJG1TrOEGllRQhge-bXkBIeyEVEpS0cS-4vjmAsmqlYsOvaFzWH5J/pub?output=csv');
$objects = array();

foreach($data as $row){    
    //generate object from distinct IDs
    if ($row['Public'] != 'no' && strlen($row['Archival Record ID']) > 0){
        $objects[$row['Archival Record ID']] = generate_object($data, $row);
    }
}

//var_dump($objects);

//iterate through each data object and generate a TEI file
foreach ($objects as $id=>$array){
    generate_tei($id, $array);
}

/***** FUNCTIONS *****/
function generate_tei ($id, $array){
    $types = array();
    $authors = array();
    $recipients = array();
    $dates = array();
    
    foreach($array['files'] as $file){
        if (array_key_exists('type', $file)){
            if (!in_array($file['type'], $types)){
                $types[] = $file['type'];
            }
        }
        if (array_key_exists('author', $file)){
            if (!in_array($file['author'], $authors)){
                $authors[] = $file['author'];
            }
        }
        if (array_key_exists('recipient', $file)){
            if (!in_array($file['recipient'], $recipients)){
                $recipients[] = $file['recipient'];
            }
        }
        if (array_key_exists('date', $file)){
            if (!in_array($file['date'], $dates)){
                $dates[] = $file['date'];
            }
        }
    }
    
    $type = (count($types) == 0 ? 'Misc.' : implode('/', $types));
    
    echo $type . "\n";
}


function generate_object($data, $row){
    $object = array();
    
    $id = $row['Archival Record ID'];
    
    
    $object['hoard'] = "http://coinhoards.org/id/" . $row['Coin Hoard ID'];    
    
    //re-iterate through each row looking for tif files that match the ID pattern
    foreach ($data as $page){
        if (strpos($page['File Name'], $id) !== FALSE){
            $file = array();
            $file['filename'] = $page['File Name'];
            
            if (strlen($page['Document Type']) > 0){
                $file['type'] = $page['Document Type'];
            }
            if (strlen($page['Date (yyyy-mm-dd)']) > 0){
                $file['date'] = $page['Date (yyyy-mm-dd)'];
            }
            if (strlen($page['Document Author']) > 0){
                $file['author'] = $page['Document Author'];
            }
            if (strlen($page['Document Recipient']) > 0){
                $file['recipient'] = $page['Document Recipient'];
            }
            if (strlen($page['Other Person 1']) > 0){
                $file['person1'] = $page['Other Person 1'];
            }
            if (strlen($page['Other Person 2']) > 0){
                $file['person2'] = $page['Other Person 2'];
            }
            if (strlen($page['Other Person 3']) > 0){
                $file['person3'] = $page['Other Person 3'];
            }
            if (strlen($page['Other Person 4']) > 0){
                $file['person4'] = $page['Other Person 4'];
            }
            if (strlen($page['Other Person 5']) > 0){
                $file['person5'] = $page['Other Person 5'];
            }
            if (strlen($page['Other Person 6']) > 0){
                $file['person6'] = $page['Other Person 6'];
            }
            
            $object['files'][] = $file;
            
            unset($file);
        }
    }
    return $object;
}

//handle xsd:date before 1970 (https://stackoverflow.com/questions/33581012/create-date-object-in-php-for-dates-before-1970-in-certain-format)
function safe_strtotime($string) {
    $pattern = "F j, Y";
    
    if(!preg_match("/\d{4}/", $string, $match)) return null; //year must be in YYYY form
    $year = intval($match[0]);//converting the year to integer
    if($year >= 1970) return date($pattern, strtotime($string));//the year is after 1970 - no problems even for Windows
    if(stristr(PHP_OS, "WIN") && !stristr(PHP_OS, "DARWIN")) //OS seems to be Windows, not Unix nor Mac
    {
        $diff = 1975 - $year;//calculating the difference between 1975 and the year
        $new_year = $year + $diff;//year + diff = new_year will be for sure > 1970
        $new_date = date($pattern, strtotime(str_replace($year, $new_year, $string)));//replacing the year with the new_year, try strtotime, rendering the date
        return str_replace($new_year, $year, $new_date);//returning the date with the correct year
    }
    return date($pattern, strtotime($string));//do normal strtotime
}

//CSV processing functions
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