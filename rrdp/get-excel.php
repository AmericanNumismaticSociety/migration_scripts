<?php 
/*****
 * Author: Ethan Gruber
 * Date: July 2020
 * Function: Read mounted Google Drive for RRDP completed and get all of the Excel spreadsheets
 *****/

$dir = '/home/komet/GoogleDrive';

$list = scandir($dir);

//var_dump($files);

foreach ($list as $item){
    if (strpos($item, '.') !== FALSE){
        //only process Excel files
        if (strpos($item, 'xlsx') !== FALSE){
            copy ( $dir . '/' . $item , '/home/komet/ans_migration/rrdp/excel/' . $item );
        } 
    } else {
        
        if ($handle = opendir($dir . '/' . $item)) {
            while (false !== ($entry = readdir($handle))) {
                if (strpos($entry, 'xlsx') !== FALSE){
                    copy ( $dir . '/' . $item . '/' . $entry , '/home/komet/ans_migration/rrdp/excel/' . $entry );
                }
            }
            closedir($handle);
        }
    }
}

?>