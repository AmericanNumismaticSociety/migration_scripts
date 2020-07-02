<?php 
/*****
 * Author: Ethan Gruber
 * Date: July 2020
 * Function: Process a list of JPG files from an imagemagick identify of a folder for the Roman Republican Die Project.
 * Parse the filenames into 14 binders, reordered by page, and insert subjects for CRRO URIs
 *****/

$filename = "identify.list";

//parse filename listing and pixel dimensions into a data object for processing into TEI
$object = parse_files($filename);

//generate_tei($object);


/***** FUNCTIONS *****/
function generate_tei($object){
    foreach ($object as $id=>$binder){
        if ($id == 'b01'){
            var_dump($binder);
        }
    }
}


function parse_files($filename){
    $handle = fopen($filename, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            preg_match('/(.*\.jpg).*\sJPEG\s(\d+)x(\d+)/', $line, $matches);
            
            $image = $matches[1];
            $width = $matches[2];
            $height = $matches[3];
            
            //parse image
            //echo $image . "\n";
            $pieces = explode('_', str_replace('.jpg', '', $image));
            
            //insert page metadata into each object
            $page = array('filename'=>$image, 'ref'=>$pieces[1], 'page'=>$pieces[3], 'height'=>$height, 'width'=>$width);
            $object[$pieces[2]][$pieces[3]] = $page;
            
            if ( strpos($pieces[3], 'p') !== 0){
                echo $image . "\n";
            }
            
            
            
            //var_dump($pieces);
            
            
            
        }
        fclose($handle);
    }
    
    //sort array in the order of binder numbers
    ksort($object);
    
    //then sort each binder by page number (as a string)
    foreach ($object as $k=>$array){
        ksort($array);
        $object[$k] = $array;
    }
    
    return $object;
}

?>