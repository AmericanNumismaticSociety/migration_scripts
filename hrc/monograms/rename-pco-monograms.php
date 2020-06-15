<?php 

/*****
 * Author: Ethan Gruber
 * Date: June 2020
 * Function Rename the Seleucid Part I monograms into the new distinct filenames. This will also generate a new XML block for the XForms engine
 *****/

$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vSSnVspLIFcgyv3WfN-Eubys_y8-sDZyMs-G0_C3CDetDSRgcdOxE4W8MZp8RwyfMeC44a48UqU2n3s/pub?output=csv');
$xml = simplexml_load_file('/usr/local/projects/migration_scripts/fonts/xforms/xml/monograms.xml');

$files = array();

$doc = new XMLWriter();
$doc->openUri('php://output');
//$doc->openUri('lorber.xml');
$doc->setIndent(true);
//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
$doc->setIndentString("    ");
$doc->startDocument('1.0','UTF-8');
$doc->startElement('folder');
    $doc->writeAttribute('name', "HoughtonI");

foreach ($data as $row){
    $newFile = $row['New Filename'];
    
    if (strlen($newFile) > 0){
        $oldFile = $row['Old Filename'];
        
        $letters = '';
        
        if (!in_array($newFile, $files)){
            foreach ($xml->folder[1]->children() as $file){
                if (trim($file['name']) == "{$newFile}.svg" ){
                    $letters = trim($file['letters']);
                }
            }
            
            $doc->startElement('file');
                $doc->writeAttribute('name', $newFile . '.svg');
                $doc->writeAttribute('editor', 'pvalfen');
                $doc->writeAttribute('letters', $letters);
            $doc->endElement();
            
            $files[] = $newFile;
        }
        
        
        /*if (!copy("/home/komet/ans_migration/fonts/svg/seleucids_rename/{$oldFile}.svg", "/home/komet/ans_migration/fonts/svg/seleucids_out/{$newFile}.svg")) {
            echo "failed to copy {$oldFile}...\n";
        }*/
    }
}

$doc->endElement();
//close file
$doc->endDocument();
$doc->flush();

/* FUNCTIONS */
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