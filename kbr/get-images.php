<?php 

$data = generate_json("Roman_KBR.csv");
$matches = array();

foreach ($data as $row){
    $image = $row['head1'];
    
    $file_headers = @get_headers($image);
    if (strpos($file_headers[16], 'location:') !== FALSE){
        $url = trim(str_replace('location:', '', $file_headers[16]));
        echo "{$url}\n";       
        $matches[] = array($image, $file_headers[16]);
    } else {
        echo "{$image}: not found\n";
    }
    //var_dump($file_headers);
    
}


$fp = fopen('matches.csv', 'w');
fputcsv($fp, array('head1', 'jpg'));
foreach ($matches as $row) {
    fputcsv($fp, $row);
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