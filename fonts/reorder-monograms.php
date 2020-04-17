<?php 

/* Date: January 2020
 * Function: order the monogram SVG list into logical order from the literal order from the filesystem `ls`
 */

$files = array();
$ordered = array();

$fh = fopen('files.list','r');
while ($line = fgets($fh)) {
    $files[] = str_replace('monogram.lorber.', '', $line);
}
fclose($fh);

foreach ($files as $id){
    preg_match('/^(\d+).*$/', $id, $matches);
    
    $ordered[$id] = (int)$matches[1];
    
}

asort($ordered);
//var_dump($ordered);

$file = "new.list";
foreach ($ordered as $k=>$v){
    $id = 'monogram.lorber.' . $k;
    file_put_contents($file, $id, FILE_APPEND);
}
?>
