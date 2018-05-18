<?php 

//requires mbstring package for PHP

$records = array('189221','189222','189223','189215');

foreach ($records as $record){
	$url = 'http://donum.numismatics.org/cgi-bin/koha/opac-export.pl?format=mods&op=export&bib=' . $record;
	
	$string = file_get_contents($url);
	$string = mb_convert_encoding($string, 'utf-8', mb_detect_encoding($string));
	// if you have not escaped entities use
	$string = mb_convert_encoding($string, 'html-entities', 'utf-8');
	
	//get MODS file from DONUM
	$dom = new DOMDocument('1.0', 'UTF-8');
	if ($dom->loadXML($string) === FALSE){
		echo "Failed to load MODS file: {$record}.\n";
	} else {
		$dom->save('mods/' . $record . '.xml');
	}
}

?>