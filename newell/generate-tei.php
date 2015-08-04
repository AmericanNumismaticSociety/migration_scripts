<?php 

$records = array('187744','188323','188324','188557','188524','188555','189144','189145','189146','189148','189149','189213','189216','189217','189218','189219','189220','189224','189225','189227');

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