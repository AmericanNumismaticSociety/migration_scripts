<?php 

$writer = new XMLWriter();
$writer->openURI("monograms.xml");
//$writer->openURI('php://output');
$writer->startDocument('1.0','UTF-8');
$writer->setIndent(true);
//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
$writer->setIndentString("    ");
$writer->startElement('monograms');

foreach(glob('svg/*', GLOB_ONLYDIR) as $dir) {    
    $files = scandir($dir);
    $writer->startElement('folder');    
        $writer->writeAttribute('name', basename($dir));
        foreach ($files as $file){
            if (strpos($file, '.svg') !== FALSE){
                $writer->startElement('file');
                    $writer->writeAttribute('name', $file);
                    $writer->writeAttribute('editor', '');
                    $writer->writeAttribute('letters', '');
                $writer->endElement();
            }          
        }
    $writer->endElement();
}

$writer->endElement(); 
$writer->flush();

//var_dump($dir);

?>