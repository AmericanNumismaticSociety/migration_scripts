<?php 
 /*****
 * Date: March 2018
 * Function: Take the CSV concordance lists exported from OpenRefine and process
 * into XML with lookups for preferred labels in order to integrate URIs into TEI files
 *****/
ini_set('user_agent', 'American Numismatic Society (http://numismatics.org)');

$wikidata = array();
$nomisma = array();
$viaf = array();
$geonames = array();

//array for the key-pair lookup list
$list = array();

//first process the spreadsheets into arrays of distinct entities with preferred labels and URIs
$people = generate_json('people-concordance.csv');
$places = generate_json('place-concordance.csv');

//perform all necessary API lookups in order to populate arrays of URIs and preferred labels
generateLabels($people);
generateLabels($places);

//var_dump($nomisma);

//iterate through spreadsheets to generate definitive output list
foreach ($people as $row){
	addLookupToList($row);
}

foreach ($places as $row){
	addLookupToList($row);
}

//var_dump($list);

$fp = fopen('lookup.csv', 'w');
fputcsv($fp, array('id','key','type','label','uri'));
foreach ($list as $fields) {
	fputcsv($fp, $fields);
}
fclose($fp);


/***** FUNCTIONS *****/
function addLookupToList ($row){	
	GLOBAL $wikidata;
	GLOBAL $nomisma;
	GLOBAL $viaf;
	GLOBAL $geonames;
	
	GLOBAL $list;
	
	$filename = str_replace('.xml', '', $row['file']);
	
	$obj = array();
	$obj['id'] = $filename;
	$obj['key'] = $row['key'];
	$obj['type'] = $row['type'];
	
	//prefer Wikidata ID over Nomisma when both are used
	if (strlen($row['Wikidata ID']) > 0){
		$id = $row['Wikidata ID'];
		
		$obj['label'] = $wikidata[$id]['label'];
		$obj['uri'] = $wikidata[$id]['uri'];
		
	} elseif (strlen($row['Nomisma ID']) > 0){
		$id = $row['Nomisma ID'];
		
		$obj['label'] = $nomisma[$id]['label'];
		$obj['uri'] = $nomisma[$id]['uri'];
	} elseif (array_key_exists('VIAF ID', $row) && strlen($row['VIAF ID']) > 0){
		$id = (string) $row['VIAF ID'];
		
		$obj['label'] = $viaf[$id]['label'];
		$obj['uri'] = $viaf[$id]['uri'];
	} elseif (array_key_exists('Geonames URI', $row) && strlen($row['Geonames URI']) > 0){
		$pieces = explode('/', $row['Geonames URI']);
		$id = (string) $pieces[3];
		
		$obj['label'] = $geonames[$id]['label'];
		$obj['uri'] = $geonames[$id]['uri'];
	} else {
		$obj['label'] = '';
		$obj['uri'] = '';
	}
	
	$list[] = $obj;
}

//popuplate the arrays of normalized preferred labels and URIs
function generateLabels($data){
	GLOBAL $wikidata;
	GLOBAL $nomisma;
	GLOBAL $viaf;
	GLOBAL $geonames;
	
	$count = 0;
	foreach ($data as $row){
		//perform lookups
		
		//parse Wikidata ID and evaluate related VIAF or Geonames IDs, if applicable
		if (strlen($row['Wikidata ID']) > 0){
			$id = $row['Wikidata ID'];
			
			if (!array_key_exists($id, $wikidata)){
				$service = "https://www.wikidata.org/w/api.php?action=wbgetentities&ids={$id}&languages=en&format=json";
				$json = json_decode(file_get_contents($service), true);
				
				//get the English label if available, otherwise get the first available label
				if (isset($json['entities'][$id]['labels']['en'])){
					$label = $json['entities'][$id]['labels']['en']['value'];
				} else {					
					$label = $json['entities'][$id]['labels']{0}['value'];
				}
				
				if ($row['type'] == 'place'){
					if (isset($json['entities'][$id]['claims']['P1566'])){
						$geonamesID = (string) $json['entities'][$id]['claims']['P1566'][0]['mainsnak']['datavalue']['value'];
						
						//see if the Geonames ID is in the $geonames array first
						if (!array_key_exists($geonamesID, $geonames)){
							//call geonames API in order to generate an AACR2 label
							$label = parseGeonames($geonamesID);
							
							if (isset($label)){
								//add the metadata for the Wikidata ID
								$uri = 'http://www.geonames.org/' . $geonamesID;
								$wikidata[$id] = array('uri'=>$uri, 'label'=>$label);
								
								//add these into the $geonames array also
								$geonames[$geonamesID] = array('uri'=>$uri, 'label'=>$label);
							}							
						} else {
							//if the geonames ID is already in the $geonames array, then fetch the metadata and insert into $wikidata
							$wikidata[$id] = $geonames[$geonamesID];
						}
					} else {
						//if there is no Geonames ID, then just use the Wikidata label
						$uri = 'https://www.wikidata.org/entity/' . $id;
						$wikidata[$id] = array('uri'=>$uri, 'label'=>$label);
					}
				} elseif ($row['type'] == 'person'){
					//look for VIAF ID
					if (isset($json['entities'][$id]['claims']['P214'])){
						$viafID = (string) $json['entities'][$id]['claims']['P214'][0]['mainsnak']['datavalue']['value'];
						
						//see if the VIAF ID is in the $viaf array first
						if (!array_key_exists($viafID, $viaf)){
							//call VIAF RDF in order to extract preferred labels
							$label = parseVIAF($viafID);
							
							//add the metadata for the Wikidata ID
							$uri = 'http://viaf.org/viaf/' . $viafID;
							$wikidata[$id] = array('uri'=>$uri, 'label'=>$label);
							
							//add these into the $viaf array also
							$viaf[$viafID] = array('uri'=>$uri, 'label'=>$label);
						} else {
							//if the geonames ID is already in the $geonames array, then fetch the metadata and insert into $wikidata
							$wikidata[$id] = $viaf[$viafID];
						}
					} else {
						//if there is no VIAF ID, then just use the Wikidata label
						$uri = 'http://viaf.org/viaf/' . $id;
						$wikidata[$id] = array('uri'=>$uri, 'label'=>$label);
					}
				} else {
					//add the metadata for the Wikidata ID
					$uri = 'https://www.wikidata.org/entity/' . $id;
					$wikidata[$id] = array('uri'=>$uri, 'label'=>$label);
				}
			}
		}
		
		//Geonames URI
		if (strlen($row['Geonames URI']) > 0){
			$pieces = explode('/', $row['Geonames URI']);
			$geonamesID = (string) $pieces[3];
			if (!array_key_exists($geonamesID, $geonames)){
				//call geonames API in order to generate an AACR2 label
				$label = parseGeonames($geonamesID);
				
				if (isset($label)){
					//add the metadata for the Wikidata ID
					$uri = 'http://www.geonames.org/' . $geonamesID;
					$geonames[$geonamesID] = array('uri'=>$uri, 'label'=>$label);
				}
			}
		}
		
		//VIAF: column only appears in people spreadsheet
		if (array_key_exists('VIAF ID', $row)){
			if (strlen($row['VIAF ID']) > 0){
				
				$viafID = (string) $row['VIAF ID'];
				
				if (!array_key_exists($viafID, $viaf)){
					$label = parseVIAF($viafID);
					$uri = 'http://viaf.org/viaf/' . $viafID;
					$viaf[$viafID] = array('uri'=>$uri, 'label'=>$label);
				}
			}
		}
		
		//Nomisma IDs
		if(array_key_exists('Nomisma ID', $row)){
			$id = $row['Nomisma ID'];
			if (strlen($id) > 0){
				if (!array_key_exists($id, $nomisma)){
					$uri = 'http://nomisma.org/id/' . $id;
					
					//use getLabel service
					$service = "http://nomisma.org/apis/getLabel?uri=http://nomisma.org/id/{$id}&format=json";
					$json = json_decode(file_get_contents($service), true);
					
					$label = $json['label'];
					
					echo "Nomisma {$id}: {$label}\n";
					$nomisma[$id] = array('uri'=>$uri, 'label'=>$label);
				}				
			}
		}
	}
}

//parse RDF from VIAF
function parseVIAF($viafID){
	$service = "https://viaf.org/viaf/{$viafID}/rdf.xml";
	
	$doc = new DOMDocument();
	if ($doc->load($service) === FALSE){
		return "FAIL";
	} else {
		$xpath = new DOMXpath($doc);
		$xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
		$xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
		
		//parse in the following order: LC, Getty, ISNI, GND, then the first label that occurs
		if ($xpath->query("descendant::rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/LC']")->length == 1){
			$label = $xpath->query("descendant::rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/LC']/skos:prefLabel")->item(0)->nodeValue;
		} elseif ($xpath->query("descendant::rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/JPG']")->length == 1){
			$label = $xpath->query("descendant::rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/JPG']/skos:prefLabel")->item(0)->nodeValue;
		} elseif ($xpath->query("descendant::rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/ISNI']")->length == 1){
			$label = $xpath->query("descendant::rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/ISNI']/skos:prefLabel")->item(0)->nodeValue;
		} elseif ($xpath->query("descendant::rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/DNB']")->length == 1){
			$label = $xpath->query("descendant::rdf:Description[skos:inScheme/@rdf:resource='http://viaf.org/authorityScheme/DNB']/skos:prefLabel")->item(0)->nodeValue;
		} else {
			$label = $xpath->query("descendant::rdf:Description[skos:inScheme][1]/skos:prefLabel")->item(0)->nodeValue;
		}
		
		echo "VIAF {$viafID}: {$label}\n";
		return $label;
	}
}

//parse Geonames API (used in Wikidata and Geonames columns)
function parseGeonames($geonamesID){
	$service = "http://api.geonames.org/getJSON?formatted=true&geonameId={$geonamesID}&username=anscoins&style=full";
	$geonamesJSON = json_decode(file_get_contents($service), true);
	
	if (array_key_exists('toponymName', $geonamesJSON)){
		$toponymName = $geonamesJSON['toponymName'];
		if (isset($geonamesJSON['countryCode'])){
			$countryCode = $geonamesJSON['countryCode'];
			$fcode = $geonamesJSON['fcode'];
			
			if ($countryCode == 'US' || $countryCode == 'AU' || $countryCode == 'CA'){
				if ($fcode == 'ADM1' || $fcode == 'PCLI'){
					$label = $toponymName;
				} else {
					$adminName = $geonamesJSON['adminName1'];
					$region = normalizeAdminName($countryCode, $adminName);
					
					//if the region isn't normalized, then just output the toponym
					if ($region == 'other'){
						$label = $toponymName;
					} else {
						$label = $toponymName . ' (' . $region . ')';
					}
					
				}
			} elseif ($countryCode == 'GB'){
				if ($fcode == 'ADM1'){
					$label = $toponymName;
				} else {
					$label = $toponymName . ' (' . $geonamesJSON['adminName1'] . ')';
				}
			} else {
				if ($fcode == 'PCLI'){
					$label = $toponymName;
				} else {
					if (strlen($geonamesJSON['countryName']) > 0){
						$label = $toponymName . ' (' . $geonamesJSON['countryName'] . ')';
					} else {
						$label = $toponymName;
					}
				}
			}
		} else {
			$label = $toponymName;
		}
		
		echo "{$geonamesID}: {$label}\n";
	} else {
		//if there isn't a toponymName, then there is some problem
		$label = null;
		echo "No toponymName\n";
	}
	
	return $label;
}

//normalize adminName to AACR2 compatible abbreviations
function normalizeAdminName($countryCode, $adminName){
	if ($countryCode == 'US'){
		switch ($adminName){
			case "Alabama":
				$region = "Ala.";
				break;
			case "Alaska":
				$region = "Alaska";
				break;
			case "Arizona":
				$region = "Ariz.";
				break;
			case "Arkansas":
				$region = "Ark.";
				break;
			case "California":
				$region = "Calif.";
				break;
			case "Colorado":
				$region = "Colo.";
				break;
			case "Connecticut":
				$region = "Conn.";
				break;
			case "Delaware":
				$region = "Del.";
				break;
			case "Washington, D.C.":
				$region = "D.C.";
				break;
			case "Florida":
				$region = "Fla.";
				break;
			case "Georgia":
				$region = "Ga.";
				break;
			case "Hawaii":
				$region = "Hawaii";
				break;
			case "Idaho":
				$region = "Idaho";
				break;
			case "Illinois":
				$region = "Ill.";
				break;
			case "Indiana":
				$region = "Ind.";
				break;
			case "Iowa":
				$region = "Iowa";
				break;
			case "Kansas":
				$region = "Kans.";
				break;
			case "Kentucky":
				$region = "Ky.";
				break;
			case "Louisiana":
				$region = "La.";
				break;
			case "Maine":
				$region = "Maine";
				break;
			case "Maryland":
				$region = "Md.";
				break;
			case "Massachusetts":
				$region = "Mass.";
				break;
			case "Michigan":
				$region = "Mich.";
				break;
			case "Minnesota":
				$region = "Minn.";
				break;
			case "Mississippi":
				$region = "Miss.";
				break;
			case "Missouri":
				$region = "Mo.";
				break;
			case "Montana":
				$region = "Mont.";
				break;
			case "Nebraska":
				$region = "Nebr.";
				break;
			case "Nevada":
				$region = "Nev.";
				break;
			case "New Hampshire":
				$region = "N.H.";
				break;
			case "New Jersey":
				$region = "N.J.";
				break;
			case "New Mexico":
				$region = "N.M.";
				break;
			case "New York":
				$region = "N.Y.";
				break;
			case "North Carolina":
				$region = "N.C.";
				break;
			case "North Dakota":
				$region = "N.D.";
				break;
			case "Ohio":
				$region = "Ohio";
				break;
			case "Oklahoma":
				$region = "Okla.";
				break;
			case "Oregon":
				$region = "Oreg.";
				break;
			case "Pennsylvania":
				$region = "Pa.";
				break;
			case "Rhode Island":
				$region = "R.I.";
				break;
			case "South Carolina":
				$region = "S.C.";
				break;
			case "South Dakota":
				$region = "S.D.";
				break;
			case "Tennessee":
				$region = "Tenn.";
				break;
			case "Texas":
				$region = "Tex.";
				break;
			case "Utah":
				$region = "Utah";
				break;
			case "Vermont":
				$region = "Vt.";
				break;
			case "Virginia":
				$region = "Va.";
				break;
			case "Washington":
				$region = "Wash.";
				break;
			case "West Virginia":
				$region = "W.Va.";
				break;
			case "Wisconsin":
				$region = "Wis.";
				break;
			case "Wyoming":
				$region = "Wyo.";
				break;
			case "American Samoa":
				$region = "A.S.";
				break;
			case "Guam":
				$region = "Guam";
				break;
			case "Northern Mariana Islands":
				$region = "M.P.";
				break;
			case "Puerto Rico":
				$region = "P.R.";
				break;
			case "U.S. Virgin Islands":
				$region = "V.I.";
				break;
			default:
				$region = 'other';
		}
	} elseif ($countryCode == 'AU'){
		switch ($adminName){
			case "Australian Capital Territory":
				$region = "A.C.T.";
				break;
			case "Jervis Bay Territory":
				$region = "J.B.T.";
				break;
			case "New South Wales":
				$region = "N.S.W.";
				break;
			case "Northern Territory":
				$region = "N.T.";
				break;
			case "Queensland":
				$region = "Qld.";
				break;
			case "South Australia":
				$region = "S.A.";
				break;
			case "Tasmania":
				$region = "Tas.";
				break;
			case "Victoria":
				$region = "Vic.";
				break;
			case "Western Australia":
				$region = "W.A.";
				break;
			default:
				$region = 'other';
		}
	} elseif ($countryCode == 'CA'){
		switch ($adminName){
			case "Alberta":
				$region = "Alta.";
				break;
			case "British Columbia":
				$region = "B.C.";
				break;
			case "Manitoba":
				$region = "Alta.";
				break;
			case "Alberta":
				$region = "Man.";
				break;
			case "New Brunswick":
				$region = "N.B.";
				break;
			case "Newfoundland and Labrador":
				$region = "Nfld.";
				break;
			case "Northwest Territories":
				$region = "N.W.T.";
				break;
			case "Nova Scotia":
				$region = "N.S.";
				break;
			case "Nunavut":
				$region = "NU";
				break;
			case "Ontario":
				$region = "Ont.";
				break;
			case "Prince Edward Island":
				$region = "P.E.I.";
				break;
			case "Quebec":
				$region = "Que.";
				break;
			case "Saskatchewan":
				$region = "Sask.";
				break;
			case "Yukon":
				$region = "Y.T.";
				break;
			default:
				$region = 'other';
		}
	}
	
	return $region;
}


//JSON Functions
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