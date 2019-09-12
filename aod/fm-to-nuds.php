<?php
/************************
AUTHOR: Ethan Gruber
MODIFIED: September, 2013
DESCRIPTION: Receive and interpret escaped CSV sent from Filemaker Pro database
to public server, transform to Numishare-compliant NUDS XML (performing cleanup of data),
post to eXist XML database via cURL, and get Solr add document from Cocoon and post to Solr.
REQUIRED LIBRARIES: php5, php5-curl, php5-cgi
************************/

/************************
 * DEPLOYMENT STEPS
* 1. install required PHP libraries (see above)
* 2. install sendmail (for reporting)
* 3. create /var/log/numishare/error.log and set write permissions
* 4. create /var/log/numishare/success.log and set write permissions
* 5. Create symlink for this script in /usr/lib/cgi-bin (or default cgi folder in Ubuntu or other OS)
* 6. Set Apache configuration in sites-enabled to enable cgi execution:
* <Directory "/usr/lib/cgi-bin">
AllowOverride None
Options +ExecCGI -MultiViews FollowSymLinks
Order deny, allow
Deny from all
Allow from ....
AddHandler cgi-script cgi php py
</Directory>
* 7. Allow from ANS IP address
* 8. Restart Apache
* 9. Increase maxHeaderLength for Tomcat in conf/server.xml
* 10. Script is good to go.
************************/

// Ignore user aborts and allow the script
// to run forever
//error_reporting(0);
ignore_user_abort(true);
set_time_limit(0);

//get unique id of recently uploaded Filemaker CSV from request parameter
//the line below is for passing request parameters from the command line.
//parse_str(implode('&', array_slice($argv, 1)), $_GET);
//$csv_id = $_GET['id'];

$file = file_get_contents("/tmp/fmexport-ww1.csv");
$cleanFile = 'cleaned.csv';
//escape conflicting XML characters
$cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', preg_replace("[\x1D]", "|", str_replace('>', '&gt;', str_replace('<', '&lt;', str_replace('&', 'and', preg_replace("[\x0D]", "\n", $file))))));
file_put_contents($cleanFile, $cleaned);

//create an array with pre-defined labels and values passed from the Filemaker POST
$labels = array("accnum","department","objtype","material","manufacture",
		"shape","weight","measurements","axis","denomination","era","dob",
		"startdate","enddate","refs","published","info","prevcoll","region",
		"locality","series","dynasty","mint","mintabbr","person","issuer",
		"magistrate","maker","artist","sernum","subjevent","subjperson",
		"subjissuer","subjplace","decoration","degree","findspot",
		"obverselegend","obversetype","reverselegend","reversetype","color",
		"edge","undertype","counterstamp","conservation","symbol",
		"obversesymbol","reversesymbol","signature","watermark",
		"imageavailable","acknowledgment","category","imagesponsor",
		"OrigIntenUse","Authenticity","PostManAlt","diameter","height","width","depth","privateinfo");

/*$csv = '"1979.38.312","ME","MEDAL","AE","Cast","","","89","12","","","1919","1919","1919","K.229|||||||||||||||||||","","","","Germany","","","","","","|||||||||||||||||||","","|||||||||","","Goetz","","The Good Samaritan","","","","","","","DER . BARMHERZIGE . SAMARITER! (=""The good Samaritan"")/ in exergue: 1919","Figure of Uncle Sam in center, to l., holding long scroll; figure of ""Michel"" (Germans) laying injured on floor reading the long scroll; to r. of Uncle Sam: mule shown from back packed with suitcases and large sack.   ","ENGLAND\'S . SCHANDTAT (=""England\'s deed of shame"")/ in exergue: AUFGEHOBEN . AM/ 12. JULI 1919! (=Lifted on July 12, 1919"")","5 figures and an infant laying on ground in front of large wall, behind which there is the sea with 7 vessels.","","","","","","","K.GOETZ","","","","","","","",""';
$temp = str_getcsv($csv, ',', '"');
var_dump($temp);*/
//load Google Spreadsheets
//mints
$places_array = generate_json('https://docs.google.com/spreadsheets/d/1uRvvmZbwU9O49B91wICexkvJq9wARd8GkQWRaljZsiM/export?gid=0&format=csv');
$subjects = generate_json('https://docs.google.com/spreadsheets/d/1aSyXYnAVoAc2NaERSVoH68wHHIy3-Xvh0CKHYKUD8dg/export?gid=0&format=csv');
$makers_array = generate_json('https://docs.google.com/spreadsheets/d/1Had27eQ3ziO4ZmlT0aVoHxTZ30Dkb17FEZlwAfPeJSw/export?gid=0&format=csv');
$artists_array = generate_json('https://docs.google.com/spreadsheets/d/1uXNK0AVtV3H2s84iP58bH7ELYZGROuvvgG71DMGmTOg/export?format=csv');
$states_array = generate_json('https://docs.google.com/spreadsheets/d/1D_1uTTWX4jojCmHxXsuNOlKITNAG5IFs75FaEgO4z2c/export?gid=0&format=csv');

//var_dump($artists_array);

$count = 0;
if (($handle = fopen("cleaned.csv", "r")) !== FALSE) {	
	while (($data = fgetcsv($handle, 2500, ',', '"')) !== FALSE) {
		$row = array();
		foreach ($labels as $key=>$label){
			$row[$label] = preg_replace('/\s+/', ' ', $data[$key]);
		}
		
		$citations = array_filter(explode('|', $row['published']));		
		if (!isset($citations[0])){
			echo "{$row['accnum']} does not have an ID.\n";
		} else {
			$id = $citations[0];
			//$pieces = explode('.', $id);
			//$pop =array_pop($pieces);
			//$broader = implode('.', $pieces);
			$xml = generate_nuds($row, $count);
			//load DOMDocument
			$dom = new DOMDocument('1.0', 'UTF-8');
			if ($dom->loadXML($xml) === FALSE){
				echo "{$id} failed to validate.\n";
			} else {
				echo "Processing {$id}\n";
				$dom->preserveWhiteSpace = FALSE;
				$dom->formatOutput = TRUE;
				//echo $dom->saveXML();
				$dom->save('types/' . $id . '.xml');
			}
		}
		$count++;
	}	
}

/****** GENERATE NUDS ******/
function generate_nuds($row, $count){
	GLOBAL $warnings;

	//references; used to check for 'ric.' for pointing typeDesc to OCRE
	$citations = array_filter(explode('|', $row['published']));
	$refs = array_filter(explode('|', $row['refs']));
	$id = $citations[0];
	$pieces = explode('.', $id);
	$pop =array_pop($pieces);
	$broader = implode('.', $pieces);
	
	//control
	$xml = '<?xml version="1.0" encoding="UTF-8"?><nuds xmlns="http://nomisma.org/nuds" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:mets="http://www.loc.gov/METS/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" recordType="conceptual">';
	$xml .= "<control><recordId>{$id}</recordId>";
	$xml .= '<otherRecordId semantic="skos:broader">' . $broader . '</otherRecordId>';
	$xml .= '<publicationStatus>approvedSubtype</publicationStatus>';
	$xml .= '<maintenanceAgency><agencyName>American Numismatic Society</agencyName></maintenanceAgency>';
	$xml .= '<maintenanceStatus>derived</maintenanceStatus>';
	$xml .= '<maintenanceHistory><maintenanceEvent>';
	$xml .= '<eventType>derived</eventType><eventDateTime standardDateTime="' . date(DATE_W3C) . '">' . date(DATE_RFC2822) . '</eventDateTime><agentType>machine</agentType><agent>PHP</agent><eventDescription>Reprocessed medal from Filemaker into type record.</eventDescription>';
	$xml .= '</maintenanceEvent></maintenanceHistory>';
	$xml .= '<rightsStmt><copyrightHolder>American Numismatic Society</copyrightHolder><license xlink:type="simple" xlink:href="http://opendatacommons.org/licenses/odbl/"/></rightsStmt>';
	$xml .= "<semanticDeclaration><prefix>skos</prefix><namespace>http://www.w3.org/2004/02/skos/core#</namespace></semanticDeclaration>";
	$xml .= "</control>";
	$xml .= '<descMeta>';
	$xml .= '<title xml:lang="en">AoD ' . $id . '</title>';
	//subjects
	if (strlen(trim($row['series'])) > 0 || strlen(trim($row['subjevent'])) > 0 || strlen(trim($row['subjissuer'])) > 0 || strlen(trim($row['subjperson'])) > 0 || strlen(trim($row['subjplace'])) > 0){
		$xml .= '<subjectSet>';
		if (strlen(trim($row['series'])) > 0){
			$serieses = array_filter(explode('|', $row['series']));
			foreach ($serieses as $series){
				$xml .= '<subject localType="series">' . trim($series) . '</subject>';
			}
		}
		if (strlen(trim($row['subjevent'])) > 0){
			$subjEvents = array_filter(explode('|', $row['subjevent']));
			foreach ($subjEvents as $subjEvent){
				$xml .= construct_subject(trim($subjEvent), 'subjectEvent');
			}
		}
		if (strlen(trim($row['subjissuer'])) > 0){
			$subjIssuers = array_filter(explode('|', $row['subjissuer']));
			foreach ($subjIssuers as $subjIssuer){
				$xml .= construct_subject(trim($subjIssuer), 'subjectIssuer');
			}
		}
		if (strlen(trim($row['subjperson'])) > 0){
			$subjPersons = array_filter(explode('|', $row['subjperson']));
			foreach ($subjPersons as $subjPerson){
				$xml .= construct_subject(trim($subjPerson), 'subjectPerson');
			}
		}
		if (strlen(trim($row['subjplace'])) > 0){
			$subjPlaces = array_filter(explode('|', $row['subjplace']));
			foreach ($subjPlaces as $subjPlace){
				$xml .= construct_subjectPlace(trim($subjPlace));
			}
		}
		$xml .= '</subjectSet>';
	}
	//notes
	//COMMENT OUT FOR PARENT TYPE
	if (strlen(trim($row['info'])) > 0){
		$infos = array_filter(explode('|', $row['info']));
		$xml .= '<noteSet>';
		foreach ($infos as $info){
			$xml .= '<note>' . trim($info) . '</note>';
		}
		$xml .= '</noteSet>';
	}

	/************ typeDesc ***************/
	$xml .= generate_typeDesc($row);

	/***** BIBLIOGRAPHIC DESCRIPTION *****/	
	if (count($refs) > 0){		
		$xml .= '<refDesc>';
		//reference
		if (count($refs) > 0){
			foreach ($refs as $val){				
				$certainty = substr($val, -1) == '?' ? ' certainty="uncertain"' : '';
				$label = str_replace('?', '', trim($val));
				$xml .= '<reference' . $certainty . '>' . $label . '</reference>';
			}
		}
		$xml .= '</refDesc>';
	}
	
	$xml .= '</descMeta>';
	$xml .= '</nuds>';

	return $xml;
}

function generate_typeDesc($row){
	//facets
	$materials = array_filter(explode('|', $row['material']));
	$mints = array_filter(explode('|', $row['mint']));
	$states = array_filter(explode('|', $row['region']));
	$makers = array_filter(explode('|', $row['maker']));
	$artists = array_filter(explode('|', $row['artist']));
	$manufactures = array_filter(explode('|', $row['manufacture']));
	
	//define obv., rev., and unspecified artists
	$artists_none = array();
	$artists_obv = array();
	$artists_rev = array();
	foreach ($artists as $artist){
		if (strlen(trim($artist)) > 0){
			if (strpos($artist, '(obv.)') !== false && strpos($artist, '(rev.)') !== false){
				$artists_obv[] = trim(str_replace('(rev.)', '', str_replace('(obv.)', '', str_replace('"', '', $artist))));
				$artists_rev[] = trim(str_replace('(rev.)', '', str_replace('(obv.)', '', str_replace('"', '', $artist))));
			} else if (strpos($artist, '(obv.)') !== false && strpos($artist, '(rev.)') !== true){
				$artists_obv[] = trim(str_replace('(rev.)', '', str_replace('(obv.)', '', str_replace('"', '', $artist))));
			} else if (strpos($artist, '(obv.)') !== true && strpos($artist, '(rev.)') !== false){
				$artists_rev[] = trim(str_replace('(rev.)', '', str_replace('(obv.)', '', str_replace('"', '', $artist))));
			} else if (strpos($artist, '(obv.)') !== true && strpos($artist, '(rev.)') !== true){
				$artists_none[] = str_replace('"', '', $artist);
			}
		}
	}
	
	//dates
	$date = '';
	if (trim($row['startdate']) != '' || trim($row['enddate']) != ''){	
	    $startdate_int = trim($row['startdate']) * 1;
	    $enddate_int = trim($row['enddate']) * 1;
	    
		$date = get_date($startdate_int, $enddate_int);
	} elseif (trim($row['dob']) != '') {
	    $dob_int = trim($row['dob']) * 1;
	    if (is_numeric($dob_int)){	        
	        $date = get_date($dob_int, $dob_int);
	    }
	}
	
	//object type
	switch (trim(strtoupper($row['objtype']))) {
		case 'BADGE':
			$objtype = 'Badge';
			$objtype_uri = 'http://vocab.getty.edu/aat/300193994';
			break;
		case 'DECORATION':
			$objtype = 'Decoration';
			$objtype_uri = 'http://nomisma.org/id/decoration_commemorative';
			break;
		case 'ELECTROTYPE':
			$objtype = 'Electrotype';
			$objtype_uri = 'http://vocab.getty.edu/aat/300234322';
			break;
		case 'EPHEMERA':
			$objtype = 'Ephemera';
			$objtype_uri = 'http://vocab.getty.edu/aat/300028881';
			break;
		case 'JETON':
			$objtype = 'Jeton';
			$objtype_uri = 'http://vocab.getty.edu/aat/300191481';
			break;
		case 'ME':
		case 'MEDAL':
			$objtype = 'Medal';
			$objtype_uri = 'http://nomisma.org/id/medal';
			break;
		case 'MEDAL DECORATION':
			$objtype = 'Medal';
			$objtype_uri = 'http://nomisma.org/id/medal';
			break;
		case 'PIN':
			$objtype = 'Pinback';
			$objtype_uri = 'http://nomisma.org/id/pinback';
			break;
		case 'PLAQUE':
			$objtype = 'Plaque';
			$objtype_uri = 'http://nomisma.org/id/plaque';
			break;
		case 'PLAQUETTE':
			$objtype = 'Plaquette';
			$objtype_uri = 'http://vocab.getty.edu/aat/300190507';
			break;			
		default:
			$objtype = ucfirst(strtolower(trim($row['objtype'])));
	}
	$xml = '<typeDesc>';
	
	//COMMENT OUT FOR PARENT TYPE
	if (isset($objtype_uri)) {
		$xml .= '<objectType xlink:type="simple" xlink:href="' . $objtype_uri . '">' . $objtype . '</objectType>';
	} else {
		$xml .= '<objectType>' . $objtype . '</objectType>';
	}
	
	//date
	if (strlen($date) > 0){
		$xml .= $date;
	}
	
	if (strlen(trim($row['dob'])) > 0){
	    $xml .= '<dateOnObject><date>' . trim($row['dob']) . '</date></dateOnObject>';
	}
	
	//manufacture
	//COMMENT OUT FOR PARENT TYPE
	if (count($manufactures) > 0){		
		foreach ($manufactures as $manufacture){
			$result = normalize_manufacture(trim($manufacture));
			$certainty = substr($manufacture, -1) == '?' ? ' certainty="uncertain"' : '';
			if (strlen($result['uri']) > 0){
				$xml .= '<manufacture xlink:type="simple" xlink:href="' . $result['uri'] . '"' . $certainty . '>' . $result['label'] . '</manufacture>';
			} else {
				$xml .= '<manufacture>' . $result['label'] . '</manufacture>';
			}
		}
	}
	//material
	//COMMENT OUT FOR PARENT TYPE
	if (count($materials) > 0){
		foreach ($materials as $material){
			$result = normalize_material(trim($material));
			$certainty = substr($material, -1) == '?' ? ' certainty="uncertain"' : '';
			if (strlen($result['uri']) > 0){
				$xml .= '<material xlink:type="simple" xlink:href="' . $result['uri'] . '"' . $certainty . '>' . $result['label'] . '</material>';
			} else {
				$xml .= '<material>' . $result['label'] . '</material>';
			}
		}
	}
	//obverse
	if (strlen($row['obverselegend']) > 0 || strlen($row['obversesymbol']) > 0 || strlen($row['obversetype']) > 0){
		$xml .= '<obverse>';
		//obverselegend
		if (strlen($row['obverselegend']) > 0){
			$xml .= '<legend>' . trim($row['obverselegend']) . '</legend>';
		}
		//obversesymbol
		if (strlen($row['obversesymbol']) > 0){
			$xml .= '<symbol>' . trim($row['obversesymbol']) . '</symbol>';
		}
		//obversetype
		if (strlen($row['obversetype']) > 0){
			$xml .= '<type><description xml:lang="en">' . trim($row['obversetype']) . '</description></type>';
		}
		//artist
		foreach ($artists_obv as $artist){
			$xml .= construct_artist(trim($artist));
		}
		$xml .= '</obverse>';
	}
	
	//reverse
	if (strlen($row['reverselegend']) > 0 || strlen($row['reversesymbol']) > 0 || strlen($row['reversetype']) > 0){
		$xml .= '<reverse>';
		//reverselegend
		if (strlen($row['reverselegend']) > 0){
			$xml .= '<legend>' . trim($row['reverselegend']) . '</legend>';
		}
		//reversesymbol
		if (strlen($row['reversesymbol']) > 0){
			$xml .= '<symbol>' . trim($row['reversesymbol']) . '</symbol>';
		}
		//reversetype
		if (strlen($row['reversetype']) > 0){
			$xml .= '<type><description xml:lang="en">' . trim($row['reversetype']) . '</description></type>';
		}
		//artist
		foreach ($artists_rev as $artist){
			$xml .= construct_artist(trim($artist));
		}
		$xml .= '</reverse>';
	}
	
	//edge
	if (strlen(trim($row['edge'])) > 0){
		$xml .= '<edge><description>' . trim($row['edge']) . '</description></edge>';
	}
	
	/***** GEOGRAPHICAL LOCATIONS *****/
	if (count($mints) > 0){
		$xml .= '<geographic>';
		if (strlen(trim($row['mint'])) > 0){			
			foreach ($mints as $mint){
				$certainty = substr(trim(str_replace('"', '', $mint)), -1) == '?' ? ' certainty="uncertain"' : '';
				$newVal = explode(',', trim(str_replace('?', '', $mint)));
				$uri = place_lookup(trim(str_replace('?', '', $mint)));
				$href = strlen($uri) > 0 ? ' xlink:href="' . $uri . '"' : '';
				$xml .= '<geogname xlink:type="simple" xlink:role="mint"' . $href . $certainty . '>' . $newVal[0] . '</geogname>';
			}
		}
		$xml .= '</geographic>';
	}
	
	/***** AUTHORITIES AND PERSONS *****/
	if (count($states) > 0 || count($makers) > 0 ||  count($artists) > 0){
		$xml .= '<authority>';
		//artist
		foreach ($artists_none as $artist){
			$xml .= construct_artist(trim($artist));
		}
		//maker
		foreach ($makers as $maker){
			$xml .= construct_maker(trim($maker));
		}	
		foreach ($states as $state){
			$xml .= '<corpname xlink:type="simple" xlink:role="authority">' . str_replace('?', '', $state) . '</corpname>';
		}
		$xml .= '</authority>';
	}
	$xml .= '</typeDesc>';
	return $xml;
}

function construct_subject($value, $localType){
	GLOBAL $subjects;
	
	foreach ($subjects as $subject){
		if ($subject['value'] == $value) {
			return '<subject xlink:type="simple" xlink:href="' . $subject['wikipedia'] . '" localType="' . $localType . '">' . $value . '</subject>';
		}
	}
}

function construct_subjectPlace($value){
	GLOBAL $places_array;

	foreach ($places_array as $place){
		if ($place['value'] == $value) {
			$newVal = explode(',', $value);
			if (strlen($place['uri']) > 0){
				return '<subject xlink:type="simple" xlink:href="' . $place['uri'] . '" localType="subjectPlace">' . $newVal[0] . '</subject>';
			} else {
				return '<subject xlink:type="simple" localType="subjectPlace">' . $newVal[0] . '</subject>';
			}			
		}
	}
}

function construct_artist($value){
	GLOBAL $artists_array;
	
	foreach ($artists_array as $artist){
		if ($artist['value'] == $value) {
			if (strlen($artist['uri']) > 0){
				return '<persname xlink:role="artist" xlink:type="simple" xlink:href="' . $artist['uri'] . '">' . $value . '</persname>';
			} else {
				return '<persname xlink:role="artist" xlink:type="simple">' . $value . '</persname>';
			}
		}
	}
}

function construct_maker($value){
	GLOBAL $makers_array;

	foreach ($makers_array as $maker){
		if ($maker['value'] == $value) {
			if (strlen($maker['type']) > 0){
				if (strlen($maker['uri']) > 0){
					return '<persname xlink:role="maker" xlink:type="simple" xlink:href="' . $maker['uri'] . '">' . $value . '</persname>';
				} else {
					return '<persname xlink:role="maker" xlink:type="simple">' . $value . '</persname>';
				}
			} else {
				if (strlen($maker['uri']) > 0){
					return '<corpname xlink:role="maker" xlink:type="simple" xlink:href="' . $maker['uri'] . '">' . $value . '</corpname>';
				} else {
					return '<corpname xlink:role="maker" xlink:type="simple">' . $value . '</corpname>';
				}
			}		
		}
	}
}

function place_lookup ($value){
	GLOBAL $places_array;
	
	$uri = '';
	foreach ($places_array as $place){
		if ($place['value'] == $value){
			$uri = $place['uri'];	
		}
	}
	return $uri;
}

function normalize_material($material){
	$array = array();
	switch (trim(strtoupper(str_replace('?', '', $material)))) {
		case 'AE':
		case 'B':
		case 'BRONZE':
			$array['label'] = 'Bronze';
			$array['uri'] = 'http://nomisma.org/id/ae';
			break;
		case 'AL':
		case 'ALUMINUM':
			$array['label'] = 'Aluminum';
			$array['uri'] = 'http://nomisma.org/id/al';
			break;
		case 'AV':
		case 'AU':
		case 'GOLD':
			$array['label'] = 'Gold';
			$array['uri'] = 'http://nomisma.org/id/av';
			break;
		case 'AR':
		case 'SILVER':
			$array['label'] = 'Silver';
			$array['uri'] = 'http://nomisma.org/id/ar';
			break;
		case 'BI':
		case 'BIL':
		case 'BILLON':
			$array['label'] = 'Billon';
			$array['uri'] = 'http://nomisma.org/id/billon';
			break;
		case 'BRASS':
			$array['label'] = 'Brass';
			$array['uri'] = 'http://nomisma.org/id/brass';
			break;
		case 'CU':
		case 'COPPER':
			$array['label'] = 'Copper';
			$array['uri'] = 'http://nomisma.org/id/cu';
			break;
		case 'EL':
		case 'ELECTRUM':
			$array['label'] = 'Electrum';
			$array['uri'] = 'http://nomisma.org/id/electrum';
			break;
		case 'ENAMEL':
			$array['label'] = 'Enamel';
			$array['uri'] = 'http://nomisma.org/id/enamel';
			break;
		case 'F':
			$array['label'] = 'Fiber';
			$array['uri'] = 'http://nomisma.org/id/fiber';
			break;
		case 'FE':
		case 'IRON':
			$array['label'] = 'Iron';
			$array['uri'] = 'http://nomisma.org/id/fe';
			break;
		case 'GS':
			$array['label'] = 'Unknown value';
			$array['uri'] = 'http://nomisma.org/id/unknown_value';
			break;
		case 'GLASS':
			$array['label'] = 'Glass';
			$array['uri'] = 'http://nomisma.org/id/glass';
			break;
		case 'NI':
		case 'NICKEL':
			$array['label'] = 'Nickel';
			$array['uri'] = 'http://nomisma.org/id/ni';
			break;
		case 'ORICHALCUM':
			$array['label'] = 'Orichalcum';
			$array['uri'] = 'http://nomisma.org/id/orichalcum';
			break;
		case 'PB':
		case 'LEAD':
			$array['label'] = 'Lead';
			$array['uri'] = 'http://nomisma.org/id/pb';
			break;
		case 'STEEL':
			$array['label'] = 'Steel';
			$array['uri'] = 'http://nomisma.org/id/steel';
			break;
		case 'SN':
		case 'TIN':
			$array['label'] = 'Tin';
			$array['uri'] = 'http://nomisma.org/id/sn';
			break;
		case 'ZN':
		case 'Z':
		case 'ZINC':
			$array['label'] = 'Zinc';
			$array['uri'] = 'http://nomisma.org/id/zn';
			break;
		case 'M':
		case 'MIXED':
		case 'UNKNOWN':
			$array['label'] = 'Unknown value';
			$array['uri'] = 'http://nomisma.org/id/unknown_value';
			break;
		case 'WHITE MEDAL':
		case 'WHITE METAL':
		case 'WHITE METAL?':
			$array['label'] = 'Unknown value';
			$array['uri'] = 'http://nomisma.org/id/unknown_value';
		default:
			$array['label'] = ucfirst(strtolower($material));
			$array['uri'] = '';
	}
	return $array;
}

function normalize_manufacture($manufacture){
	$array = array();
	switch (trim(strtoupper(str_replace('?', '', $manufacture)))) {
		case 'STRUCK':
			$array['label'] = 'Struck';
			$array['uri'] = 'http://nomisma.org/id/struck';
			break;
		case 'CC':
			$array['label'] = 'Pressed';
			$array['uri'] = 'http://nomisma.org/id/cast';
			break;
		case 'CAST':
			$array['label'] = 'Cast';
			$array['uri'] = 'http://nomisma.org/id/cast';
			break;
		case 'ELECTROTYPE':
			$array['label'] = 'Electrotyped';
			$array['uri'] = 'http://nomisma.org/id/electrotyped';
			break;
		case 'GALVANO':
			$array['label'] = 'Plated';
			$array['uri'] = 'http://nomisma.org/id/plated';
			break;
		case 'GILT':
			$array['label'] = 'Gilded';
			$array['uri'] = 'http://nomisma.org/id/gilded';
			break;
		default:
			$array['label'] = ucfirst(strtolower($material));
			$array['uri'] = '';
	}
	return $array;
}

function get_title_date($fromDate, $toDate){
	if ($fromDate == 0 && $toDate != 0){
		return get_date_textual($toDate);
	} elseif ($fromDate != 0 && $toDate == 0) {
		return get_date_textual($fromDate);
	} elseif ($fromDate == $toDate){
		return get_date_textual($toDate);
	} elseif ($fromDate != 0 && $toDate != 0){
		return get_date_textual($fromDate) . ' - ' . get_date_textual($toDate);
	}
}

function get_date_textual($year){
	$textual_date = '';
	//display start date
	if($year < 0){
		$textual_date .= abs($year) . ' BC';
	} elseif ($year > 0) {
		if ($year <= 600){
			$textual_date .= 'AD ';
		}
		$textual_date .= $year;
	}
	return $textual_date;
}

function get_date($startdate, $enddate){
	GLOBAL $warnings;
	$node = '';
	$start_gYear = '';
	$end_gYear = '';
	
	//validate dates
	if ($startdate != 0 && is_int($startdate) && $startdate < 3000 ){
		$start_gYear = number_pad($startdate, 4);
	} 
	if ($enddate != 0 && is_int($enddate) && $enddate < 3000 ){
		$end_gYear = number_pad($enddate, 4);
	}
	
	if ($startdate == 0 && $enddate != 0){
		$node = '<date' . (strlen($end_gYear) > 0 ? ' standardDate="' . $end_gYear . '"' : '') . '>' . get_date_textual($enddate) . '</date>';
	} elseif ($startdate != 0 && $enddate == 0) {
		$node = '<date' . (strlen($start_gYear) > 0 ? ' standardDate="' . $start_gYear . '"' : '') . '>' . get_date_textual($startdate) . '</date>';
	} elseif ($startdate == $enddate){
		$node = '<date' . (strlen($end_gYear) > 0 ? ' standardDate="' . $end_gYear . '"' : '') . '>' . get_date_textual($enddate) . '</date>';
	} elseif ($startdate != 0 && $enddate != 0){
		$node = '<dateRange><fromDate' . (strlen($start_gYear) > 0 ? ' standardDate="' . $start_gYear . '"' : '') . '>' . get_date_textual($startdate) . '</fromDate><toDate' . (strlen($start_gYear) > 0 ? ' standardDate="' . $end_gYear . '"' : '') . '>' . get_date_textual($enddate) . '</toDate></dateRange>';
	}
	return $node;
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
	if ($number > 0){
		$gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
	} elseif ($number < 0) {
		$bcNum = (int)abs($number) - 1;
		$gYear = '-' . str_pad($bcNum,$n,"0",STR_PAD_LEFT);
	}
	return $gYear;
}

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

function url_exists($url) {
	if (!$fp = curl_init($url)) return false;
	return true;
}

class filterGeo {
	private $mv;
	private $rv;
	private $lv;

	function __construct($mv, $rv, $lv) {
		$this->mint = $mv;
		$this->region = $rv;
		$this->locality = $lv;
	}

	function matches($m) {
		return $m['mint'] == $this->mint && $m['region'] == $this->region && $m['locality'] == $this->locality;
	}
}

?>
