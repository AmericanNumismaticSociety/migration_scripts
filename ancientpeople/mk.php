<?php 

$tl = array('dbpedia','romeemp','pas','nomisma','wikipedia','viaf','name1','name2','name3');
$lookup = array();

//generate array from CSV
if (($handle = fopen("test.csv", "r")) !== FALSE) {
	while (($data = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
		$row = array();
		foreach ($tl as $key=>$label){
			$row[$label] = $data[$key];
		}
		
		$viafId = str_replace('http://viaf.org/viaf/', '', $row['viaf']);
		$lookup[$viafId] = array('dbpedia'=>$row['dbpedia'], 'pas'=>$row['pas'], 'nomisma'=>$row['nomisma'], 'viaf'=>$row['viaf']);
	}
}

$ml = array('ID','Nachname','GND-ID','VIAF','bemerkung');
$csv = '"ID","Nachname","GND-ID","VIAF","dbpedia_uri","pas_uri","nomisma_uri","viaf_uri","gnd_uri","bnf_uri","bemerkung"' . "\n";
if (($handle = fopen("mk_list.csv", "r")) !== FALSE) {
	while (($data = fgetcsv($handle, 1000, "|")) !== FALSE) {
		$row = array();
		foreach ($ml as $key=>$label){
			$row[$label] = $data[$key];
		}

		if ($row['VIAF'] > 0){
			$viafRDF = new DOMDocument();
			$viafRDF->load('http://viaf.org/viaf/' . $row['VIAF'] . '/rdf.xml');
			$vxpath = new DOMXPath($viafRDF);
			$vxpath->registerNamespace('rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
			$vxpath->registerNamespace('owl', "http://www.w3.org/2002/07/owl#");
			$vxpath->registerNamespace('rdaGr2', "http://rdvocab.info/ElementsGr2/");
			
			$sameAs = array();
			foreach ($vxpath->query("//rdf:Description[rdf:type/@rdf:resource='http://xmlns.com/foaf/0.1/Person']/owl:sameAs") as $ele){
				$val = $ele->getAttribute('rdf:resource');
				if (strrpos($val, 'dbpedia') !== false){
					$sameAs['dbpedia_uri'] = $val;
				} elseif (strrpos($val, 'd-nb') !== false){
					$sameAs['gnd_uri'] = $val;
				} elseif (strrpos($val, 'idref') !== false){
					$sameAs['bnf_uri'] = $val;
				}
			}
			
			$array = $lookup[$row['VIAF']];
			$csv .= '"' . $row['ID'] . '","' . $row['Nachname'] . '","' . $row['GND-ID'] . '","' . $row['VIAF'] . '","' . $sameAs['dbpedia_uri'] . '","' . $array['pas'] . '","' . $array['nomisma'] . '","' . 'http://viaf.org/viaf/' . $row['VIAF'] . '/","' . $sameAs['gnd_uri'] . '","' . $sameAs['bnf_uri'] . '","' . $row['bemerkung'] . '"' . "\n";
		} else {
			$csv .= '"' . $row['ID'] . '","' . $row['Nachname'] . '","' . $row['GND-ID'] . '","' . $row['VIAF'] . '","","","","","","","' . $row['bemerkung'] . '"' . "\n";
		}
		
	}
}

//write file 
$filename = "person_list.csv";
$fh = fopen($filename, 'w') or die("can't open file");
fwrite($fh, $csv);
fclose($fh);

//echo $csv;

?>