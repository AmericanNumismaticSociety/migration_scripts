<?php 

$json = file_get_contents('provinces.json');
$data = json_decode($json);

foreach ($data->features as $feature){
    $id = str_replace(' ', '_', $feature->properties->NAME);
    $geometry = $feature->geometry;
    
    $file = "polygons/{$id}.json";
    file_put_contents($file, json_encode($geometry));
}

?>