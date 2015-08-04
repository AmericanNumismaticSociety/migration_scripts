<?php 

/************************
 AUTHOR: Ethan Gruber
MODIFIED: September, 2012
DESCRIPTION: Process ancientpeople CSV and import data from VIAF and dbpedia
REQUIRED LIBRARIES: php5, php5-curl
************************/

$labels = array('dbpedia','romeemp','pas','nomisma','wikipedia','viaf','name1','name2','name3');
$identityArray = array();
$processed = array();

if (($handle = fopen("test.csv", "r")) !== FALSE) {
	while (($data = fgetcsv($handle, 1000, ",", '"')) !== FALSE) {
		$values_with_labels = array();
		foreach ($labels as $key=>$label){
			$values_with_labels[$label] = $data[$key];
		}
			
		//call function to generate xml
		$xml = generate_eac($values_with_labels);
		$id =  strtolower(substr(strstr($values_with_labels['dbpedia'], 'resource/'), strpos(strstr($values_with_labels['dbpedia'], 'resource/'), '/') + 1));
		$fileName = '/tmp/' . $id . '.xml';
		
		put_to_exist($xml, $id, $fileName);
		//echo $xml . "\n";
	}
}

//process $identityArray
while (list($k, $v) = each($identityArray)){
	if (!in_array($k, $processed)){
		$array = array('dbpedia'=>$k,'romeemp'=>'','pas'=>'','nomisma'=>'','wikipedia'=>'','viaf'=>'','name1'=>'','name2'=>'','name3'=>'');
		$id =  strtolower(substr(strstr($array['dbpedia'], 'resource/'), strpos(strstr($array['dbpedia'], 'resource/'), '/') + 1));
		$fileName = '/tmp/' . $id . '.xml';
		$xml = generate_eac($array);
		
		put_to_exist($xml, $id, $fileName);
	}
}

function generate_eac($array){
	GLOBAL $identityArray;
	GLOBAL $processed;
	$processed[] =  $array['dbpedia'];
	
	$id =  substr(strstr($array['dbpedia'], 'resource/'), strpos(strstr($array['dbpedia'], 'resource/'), '/') + 1);
	
	//load VIAF RDF
	if (strlen($array['viaf']) > 0){
		$viafRDF = new DOMDocument();
		$viafRDF->load($array['viaf'] . '/rdf.xml');
		$vxpath = new DOMXPath($viafRDF);
		$vxpath->registerNamespace('rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
		$vxpath->registerNamespace('owl', "http://www.w3.org/2002/07/owl#");
		$vxpath->registerNamespace('rdaGr2', "http://rdvocab.info/ElementsGr2/");
	}
	
	//load dbpedia RDF
	$dbRDF = new DOMDocument();
	$dbRDF->load('http://dbpedia.org/data/' . $id . '.rdf');
	$dxpath = new DOMXpath($dbRDF);
	$dxpath->registerNamespace('rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
	$dxpath->registerNamespace('dbpedia-owl', "http://dbpedia.org/ontology/");
	$dxpath->registerNamespace('rdfs', "http://www.w3.org/2000/01/rdf-schema#");
	$dxpath->registerNamespace('ns7', "http://live.dbpedia.org/ontology/");
	
	$xml = '<?xml version="1.0" encoding="utf-8"?><eac-cpf xmlns="urn:isbn:1-931666-33-4" xmlns:xlink="http://www.w3.org/1999/xlink">';
	
	/************ CONTROL ************/
	$xml .= '<control>';
	$xml .= '<recordId>' . strtolower($id) . '</recordId>';
	
	//add other records
	$xml .= '<otherRecordId>' . $array['dbpedia'] . '</otherRecordId>';
	if (strlen($array['viaf']) > 0){
		$xml .= '<otherRecordId>' . $array['viaf'] . '</otherRecordId>';		
		foreach ($vxpath->query("//rdf:Description[rdf:type/@rdf:resource='http://xmlns.com/foaf/0.1/Person']/owl:sameAs") as $ele){
			$xml .= '<otherRecordId>' . $ele->getAttribute('rdf:resource') . '</otherRecordId>';
		}
	}
	if (strlen($array['nomisma']) > 0){
		$xml .= '<otherRecordId>' . $array['nomisma'] . '</otherRecordId>';
	}
	
	$xml .= '<maintenanceAgency><agencyName>American Numismatic Society</agencyName></maintenanceAgency>';
	$xml .= '<maintenanceHistory><maintenanceEvent><eventType>created</eventType><eventDateTime standardDateTime="' . date(DATE_W3C) . '"/><agentType>machine</agentType><agent>Ancient Persons PHP</agent></maintenanceEvent></maintenanceHistory>';
	$xml .= '<conventionDeclaration><abbreviation>WIKIPEDIA</abbreviation><citation>Wikipedia/DBpedia</citation></conventionDeclaration>';
	$xml .= '<sources><source xlink:type="simple" xlink:href="' . $array['dbpedia'] . '"/>';
	if (strlen($array['viaf']) > 0){
		$xml .= '<source xlink:type="simple" xlink:href="' . $array['viaf'] . '"/>';
	}
	$xml .= '</sources>';
	$xml .= '</control><cpfDescription>';
	
	/************ IDENTITY ************/
	$xml .= '<identity>';
	
	//gather entityType
	$types = $dxpath->query('//rdf:type[rdf:resource="http://xmlns.com/foaf/0.1/Person"]');
	echo count($types) . "\n\n\n";
	if (count($types) > 0){
		$xml .= '<entityType>person</entityType>';
	} else {
		$xml .= '<entityType>family</entityType>';
	}
	
	foreach ($dxpath->query('//rdfs:label') as $ele){
		$xml .= '<nameEntry xml:lang="' . $ele->getAttribute('xml:lang') . '"><part>' . $ele->nodeValue . '</part>';
		//set English as preferred label, otherwise alternative 
		if ($ele->getAttribute('xml:lang') == 'en'){
			$xml .= '<preferredForm>WIKIPEDIA</preferredForm>';
		} else {
			$xml .= '<alternativeForm>WIKIPEDIA</alternativeForm>';
		}
		$xml .= '</nameEntry>';
	}
	$xml .= '</identity>';
	
	/************ DESCRIPTION ************/
	$xml .= '<description>';
	$xml .= '<biogHist><abstract xml:lang="en" localType="wikipedia">' . $dxpath->query("//dbpedia-owl:abstract[@xml:lang = 'en']")->item(0)->nodeValue . '</abstract></biogHist>';
	
	//get existDates
	if (strlen($array['viaf']) > 0){
		$xml .= getExistDates($vxpath->query('//rdaGr2:dateOfBirth')->item(0)->nodeValue, $vxpath->query('//rdaGr2:dateOfDeath')->item(0)->nodeValue);
	} else {
		$startDates = $dxpath->query('//*[local-name()="birthDate"][@rdf:datatype="http://www.w3.org/2001/XMLSchema#date"]');
		$endDates = $dxpath->query('//*[local-name()="deathDate"][@rdf:datatype="http://www.w3.org/2001/XMLSchema#date"]');
			
		if (count($startDates) > 0 && count($endDates) > 0){
			$gStart = strlen($startDates->item(0)->nodeValue) > 0 ? $startDates->item(0)->nodeValue : '0001';
			$gEnd = strlen($endDates->item(0)->nodeValue) > 0 ? $endDates->item(0)->nodeValue : '0001';			
			$xml .= '<existDates><dateRange>';			
			$xml .= '<fromDate standardDate="' . $gStart . '">' . getDateTextual($gStart) . '</fromDate>';
			$xml .= '<toDate standardDate="' . $gEnd . '">' . getDateTextual($gEnd) . '</toDate>';
			$xml .= '</dateRange></existDates>';
		}		
	}
	
	//get occupations
	$occupations = $dxpath->query('descendant::rdf:Description[@rdf:about="' . $array['dbpedia'] . '"]/dbpedia-owl:occupation');
	foreach ($occupations as $occupation){
		$url = $occupation->getAttribute('rdf:resource');
		$tempId =  substr(strstr($url, 'resource/'), strpos(strstr($url, 'resource/'), '/') + 1);
		if (!array_key_exists($url, $identityArray)) {
			$simplexml = simplexml_load_file('http://dbpedia.org/data/' . $tempId . '.rdf');
			$simplexml->registerXPathNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
			$names =  $simplexml->xpath('//rdfs:label[@xml:lang="en"]');
			$name = $names[0];
		} else {
			$name = $identityArray[$url];
		}
			
		$xml .= '<occupation>';
		$xml .= '<term vocabularySource="' . $url . '">' . $name . '</term>';
		$xml .= '</occupation>';
	}
	//get subjects
	/*$subjects = $dxpath->query('descendant::rdf:Description[@rdf:about="' . $array['dbpedia'] . '"]/dcterms:subject');
	foreach ($subjects as $subject){
		$url = $subject->getAttribute('rdf:resource');
		$tempId =  substr(strstr($url, 'resource/'), strpos(strstr($url, 'resource/'), '/') + 1);
		if (!array_key_exists($url, $identityArray)) {
			$simplexml = simplexml_load_file('http://dbpedia.org/data/' . $tempId . '.rdf');
			$simplexml->registerXPathNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
			$names =  $simplexml->xpath('//rdfs:label[@xml:lang="en"]');
			$name = $names[0];
		} else {
			$name = $identityArray[$url];
		}
			
		$xml .= '<localDescription localType="subject">';
		$xml .= '<term vocabularySource="' . $url . '">' . $name . '</term>';
		$xml .= '</localDescription>';
	}*/
	
	$xml .= '</description>';
	
	/************ RELATIONS ************/
	$xml .= get_relations($dxpath, $array);
	
	//close EAC-CPF
	$xml .= '</cpfDescription></eac-cpf>';
	return $xml;
}

function get_relations($dxpath, $array){
	GLOBAL $identityArray;
	
	$nodeSets = array('descendant::dbpedia-owl:parent', 'descendant::dbpprop:dynasty[@rdf:resource]','descendant::dbpprop:house[@rdf:resource]',
			'descendant::dbpprop:successor','descendant::dbpprop:predecessor', 'descendant::dbpprop:spouse');
	$query = implode('|', $nodeSets);
	$relations = $dxpath->query($query);
	
	$xml = '<relations>';
	foreach ($relations as $relation){
		$localname = $relation->localName;
		
		
		if ($relation->parentNode->getAttribute('rdf:about') == $array['dbpedia'] && ($localname == 'house' || $localname == 'dynasty' || $localname == 'parent')){
			switch ($localname){
				case 'house':
					$xlink = array('arcrole'=>'belongsToDynasty', 'role'=>'');
					break;
				case 'dynasty':
					$xlink = array('arcrole'=>'belongsToDynasty', 'role'=>'');
					break;
				case 'parent':
					$xlink = array('arcrole'=>'childOf', 'role'=>'http://RDVocab.info/uri/schema/FRBRentitiesRDA/Person');
					break;
			}
			
			$url = $relation->getAttribute('rdf:resource');
			$tempId =  substr(strstr($url, 'resource/'), strpos(strstr($url, 'resource/'), '/') + 1);
			if (!array_key_exists($url, $identityArray)) {
				$simplexml = simplexml_load_file('http://dbpedia.org/data/' . $tempId . '.rdf');
				$simplexml->registerXPathNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
				$names =  $simplexml->xpath('//rdfs:label[@xml:lang="en"]');
				if ($array['dbpedia'] != 'http://dbpedia.org/resource/Anastasius_I_(emperor)'){
					$identityArray[$url] = $names[0];
				}
				$name = $names[0];
			} else {
				$name = $identityArray[$url];
			}
			
			$xml .= '<cpfRelation xlink:type="simple" xlink:href="' . strtolower($tempId) . '" xlink:role="' . $xlink['role'] . '" xlink:arcrole="' . $xlink['arcrole'] . '">';
			$xml .= '<relationEntry>' . $name . '</relationEntry>';
			$xml .= '</cpfRelation>';
		} else {
			if ($relation->parentNode->getAttribute('rdf:about') != $array['dbpedia']){
				switch ($localname){
					case 'spouse':
						$xlink = array('arcrole'=>'spouseOf', 'role'=>'http://RDVocab.info/uri/schema/FRBRentitiesRDA/Person');
						break;
					case 'successor':
						$xlink = array('arcrole'=>'successorOf', 'role'=>'http://RDVocab.info/uri/schema/FRBRentitiesRDA/Person');
						break;
					case 'predecessor':
						$xlink = array('arcrole'=>'predecessorOf', 'role'=>'http://RDVocab.info/uri/schema/FRBRentitiesRDA/Person');
						break;
					case 'parent':
						$xlink = array('arcrole'=>'parentOf', 'role'=>'http://RDVocab.info/uri/schema/FRBRentitiesRDA/Person');
						break;
					case 'house':
						$xlink = array('arcrole'=>'dynastyOf', 'role'=>'');
						break;
					case 'dynasty':
						$xlink = array('arcrole'=>'dynastyOf', 'role'=>'');
						break;
				}
				
				$url = $relation->parentNode->getAttribute('rdf:about');
				$tempId =  substr(strstr($url, 'resource/'), strpos(strstr($url, 'resource/'), '/') + 1);
				if (!array_key_exists($url, $identityArray)) {
					$simplexml = simplexml_load_file('http://dbpedia.org/data/' . $tempId . '.rdf');
					$simplexml->registerXPathNamespace('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
					$names =  $simplexml->xpath('//rdfs:label[@xml:lang="en"]');
					if ($array['dbpedia'] != 'http://dbpedia.org/resource/Anastasius_I_(emperor)'){
						$identityArray[$url] = $names[0];
					}
					$name = $names[0];
				} else {
					$name = $identityArray[$url];
				}
				$hrefFrag = strlen($tempId) > 0 ? ' xlink:href="' . strtolower($tempId) . '"' : '';
					
				$xml .= '<cpfRelation xlink:type="simple"' . $hrefFrag . ' xlink:role="' . $xlink['role'] . '" xlink:arcrole="' . $xlink['arcrole'] . '">';
				$xml .= '<relationEntry>' . $name . '</relationEntry>';
				$xml .= '</cpfRelation>';
			}
		}
	}
	
	//get thumbnail resource relation
	$thumbnails = $dxpath->query("//*[local-name()='thumbnail']");
	foreach ($thumbnails as $thumbnail){
		if ($thumbnail->hasAttribute('rdf:resource') === TRUE){
			$xml .= '<resourceRelation xlink:type="simple" xlink:href="' . $thumbnail->getAttribute('rdf:resource') . '" xlink:role="portrait">';
			$xml .= '<relationEntry>Thumbnail</relationEntry>';
			$xml .= '</resourceRelation>';
		}	
	}
	//get resource relatons from dbpedia
	$resources = $dxpath->query('descendant::dbpedia-owl:wikiPageExternalLink');
	foreach($resources as $resource){
		$xml .= '<resourceRelation xlink:type="simple" xlink:href="' . str_replace('&', '&amp;', $resource->getAttribute('rdf:resource')) . '" xlink:role="subjectOf">';
		$xml .= '<relationEntry>' . str_replace('&', '&amp;', $resource->getAttribute('rdf:resource')) . '</relationEntry>';
		$xml .= '</resourceRelation>';
	}
	if (strlen($array['pas']) > 0){
		$xml .= '<resourceRelation xlink:type="simple" xlink:href="' . $array['pas'] . '" xlink:role="subjectOf">';
		$xml .= '<relationEntry>Portable Antiquities Scheme Biography</relationEntry>';
		$xml .= '</resourceRelation>';
	}
	
	$xml .= '</relations>';
	return $xml;
}

function getExistDates($startDate, $endDate){
		$gDateStart = normalizeDate($startDate);
		$gDateEnd = normalizeDate($endDate);
		
		$xml = '<existDates><dateRange>';
		$xml .= '<fromDate standardDate="' . $gDateStart . '">' . getDateTextual($gDateStart) . '</fromDate>';
		$xml .= '<toDate standardDate="' . $gDateEnd . '">' . getDateTextual($gDateEnd) . '</toDate>';
		$xml .= '</dateRange></existDates>';
		
		return $xml;
}

function normalizeDate($date){
	$gDate = '';
	if (strpos($date, '-') !== FALSE){
		$segs = explode('-', $date);
		if (strpos($date, '-') == 0){
			//if BC
			$gDate .= '-';
			foreach ($segs as $k=>$v){
				if ($k == 1){
					$gDate .= str_pad((int) $v,4,"0",STR_PAD_LEFT);
				} elseif ($k > 1) {
					$gDate .= '-' . $v;
				}
			}
		} else {
			//if AD
			foreach ($segs as $k=>$v){
				if ($k == 0){
					$gDate .= str_pad((int) $v,4,"0",STR_PAD_LEFT);
				} else {
					$gDate .= '-' . $v;
				}
			}
		}
	} else {
		$gDate .= str_pad((int) $date,4,"0",STR_PAD_LEFT);
	}
	return $gDate;
}

function getDateTextual ($date){
	$textDate = '';
	if (strpos($date, '-') !== FALSE){
		$segs = explode('-', $date);
		if (strpos($date, '-') == 0){
			//if BC
			//day
			if (strlen($segs[3]) > 0){
				$textDate .= $segs[3] . ' ';
			}
			//month
			if (strlen($segs[2]) > 0){
				$textDate .= normalizeMonth($segs[2]) . ' ';
			}			
			//year
			$textDate .= (int) $segs[1] . ' B.C.';
		} else {
			//if AD
			//day
			if (strlen($segs[2]) > 0){
				$textDate .= $segs[2] . ' ';
			}
			//month
			if (strlen($segs[1]) > 0){
				$textDate .= normalizeMonth($segs[1]) . ' ';
			}			
			//year
			$textDate .= ((int) $segs[0] < 400 ? 'A.D. ' : '') . (int) $segs[0];
		}
	} else {
		$textDate = ((int) $date < 400 ? 'A.D. ' : '') . (int) $date;
	}
	return $textDate;
}

function normalizeMonth($month){
	$monthName = '';
	switch ((int) $month){
		case 1:
			$monthName = 'January';
			break;
		case 2:
			$monthName = 'February';
			break;
		case 3:
			$monthName = 'March';
			break;
		case 4:
			$monthName = 'April';
			break;
		case 5:
			$monthName = 'May';
			break;
		case 6:
			$monthName = 'June';
			break;
		case 7:
			$monthName = 'July';
			break;
		case 8:
			$monthName = 'August';
			break;
		case 9:
			$monthName = 'September';
			break;
		case 10:
			$monthName = 'October';
			break;
		case 11:
			$monthName = 'November';
			break;
		case 12:
			$monthName = 'December';
			break;
	}
	return $monthName;
}

function put_to_exist($xml, $id, $fileName){
	$dom = new DOMDocument;
	$dom->preserveWhiteSpace = FALSE;
	$dom->loadXML($xml);
	if ($dom->loadXML($xml) === FALSE){
		echo "Failed to validate in DOMDocument: {$id}.\n";
	} else {
		$dom->formatOutput = TRUE;
		$dom->save($fileName);
		//echo $dom->saveXML();
			
		if (($readFile = fopen($fileName, 'r')) === FALSE){
			echo "Failed to read file: {$id}.\n";
		} else {
			echo "Wrote {$id}.\n";
			//PUT xml to eXist
			$putToExist=curl_init();
			
			//set curl opts
			curl_setopt($putToExist,CURLOPT_URL,'http://localhost:8080/orbeon/exist/rest/db/xeac/records/' . $id . '.xml');
			curl_setopt($putToExist,CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=utf-8"));
			curl_setopt($putToExist,CURLOPT_CONNECTTIMEOUT,2);
			curl_setopt($putToExist,CURLOPT_RETURNTRANSFER,1);
			curl_setopt($putToExist,CURLOPT_PUT,1);
			curl_setopt($putToExist,CURLOPT_INFILESIZE,filesize($fileName));
			curl_setopt($putToExist,CURLOPT_INFILE,$readFile);
			curl_setopt($putToExist,CURLOPT_USERPWD,"admin:");
			$response = curl_exec($putToExist);
				
			$http_code = curl_getinfo($putToExist,CURLINFO_HTTP_CODE);
				
			//close eXist curl
			curl_close($putToExist);
		}
	}
}

?>