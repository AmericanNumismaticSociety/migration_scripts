<?php 
/*****
 * Author: Ethan Gruber
 * Date: September 2020
 * Function: Read the facsimile elements for the RRDP binder TEI files in order to generate a concordance between the old filenames and new,
 * sequentially ordered ones. The concordance will be uploaded into the ANS Google Drive for the RRPD images
 */

$files = array();

$csv = fopen("concordance.csv", "a") or die("Unable to open file!");

if ($handle = opendir('tei')) {
    
    /* This is the correct way to loop over the directory. */
    while (false !== ($entry = readdir($handle))) {
        if (strpos($entry, 'schaefer.rrdp.b') !== FALSE){
            $files[] = $entry;
        }
    }
    
    closedir($handle);
}

//perform concordance on filenames in order
asort($files);

foreach ($files as $file){
    $doc = new DOMDocument('1.0', 'UTF-8');
    
    if ($doc->load("tei/{$file}") !== FALSE){
        $recordId = str_replace('.xml', '', $file);
        
        $xpath = new DOMXpath($doc);
        $xpath->registerNamespace('tei', 'http://www.tei-c.org/ns/1.0');
        
        $images = $xpath->query("descendant::tei:facsimile");
        
       
        $count = 0;
        foreach ($images as $image){
            $old = $image->getAttribute('xml:id');
            $new = $recordId . "_" . str_pad($count, 4, '0', STR_PAD_LEFT);
            
            fwrite($csv, $old . "," . $new . "\n");
            
            $count++;
        }
    }
}

fclose($csv);

?>