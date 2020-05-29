<?php 
 /*****
 * Author: Ethan Gruber
 * Last modified: December 2018
 * Function: Generate NUDS documents from the Google spreadsheet of parsed Svoronos data, and deprecate the IDs (optional redirect)
 *****/

//svoronos spreadsheet
$data = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vRf52HzPeW0XE4jNVg0mL296k8yfKp0D43_faM907lYt5XZzUloYzZwe2o68IXXO3GjiLtQgp7XeJBL/pub?output=csv');

//cpe spreadsheet
$pco = generate_json('https://docs.google.com/spreadsheets/d/e/2PACX-1vQFHZHiQCr1MNrnZC9Rca1UbpawQDC86E-laoySz4cADKWgQxL0gOGCDJy531HiQQ82xbSroyZpldsl/pub?output=csv');

$nomismaUris = array();
//$records = array();

foreach($data as $row){	
    $id = $row['ID'];
    $cpeID = null;
    foreach($pco as $type){
        if (strlen($type['Svoronos Nr.']) > 0){
            if (strpos($type['Svoronos Nr.'], 'Addenda') === FALSE && strpos($type['Svoronos Nr.'], '(1890)') === FALSE){
                $svoronosIDs = explode('|', trim($type['Svoronos Nr.']));
                if (in_array($id, $svoronosIDs)){
                    $cpeID = $type['Lorber no.'];
                }
            }            
        }
    }
    
    generate_nuds($row, $cpeID);
	
	unset($cpeID);
}

//functions
function generate_nuds($row, $cpeID){
	
	$uri_space = 'http://numismatics.org/pco/id/';
	
	//use URI column to set the ID
	$pieces = explode('/', $row['URI']);
	$recordId = str_replace('1904-', '1904.', $pieces[4]);
	
	if (strlen($recordId) > 0){
		echo "Processing {$recordId}\n";
		$doc = new XMLWriter();
		
		//$doc->openUri('php://output');
		$doc->openUri('nuds/' . $recordId . '.xml');
		$doc->setIndent(true);
		//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
		$doc->setIndentString("    ");
		
		$doc->startDocument('1.0','UTF-8');
		
		$doc->startElement('nuds');
			$doc->writeAttribute('xmlns', 'http://nomisma.org/nuds');
				$doc->writeAttribute('xmlns:xs', 'http://www.w3.org/2001/XMLSchema');
				$doc->writeAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
				$doc->writeAttribute('xmlns:tei', 'http://www.tei-c.org/ns/1.0');	
				$doc->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
				$doc->writeAttribute('xsi:schemaLocation', 'http://nomisma.org/nuds http://nomisma.org/nuds.xsd');
				$doc->writeAttribute('recordType', 'conceptual');
			
			//control
			$doc->startElement('control');
				$doc->writeElement('recordId', $recordId);
				
				//handle semantic relation with other record				
				if (isset($cpeID)){
					$doc->startElement('otherRecordId');
						$doc->writeAttribute('semantic', 'dcterms:isReplacedBy');
						$doc->text($cpeID);
					$doc->endElement();
					$doc->startElement('otherRecordId');
						$doc->writeAttribute('semantic', 'skos:exactMatch');
						$doc->text($uri_space . $cpeID);
					$doc->endElement();
				}
				
				if (strlen($row['Parent ID']) > 0){
				    $doc->startElement('otherRecordId');
				        $doc->writeAttribute('semantic', 'skos:broader');
				        $doc->text(trim($row['Parent ID']));
				    $doc->endElement();
				}
				
				$doc->writeElement('publicationStatus', 'deprecatedType');
				
				if (isset($cpeID)){
				    $doc->writeElement('maintenanceStatus', 'cancelledReplaced');
				} else {
				    $doc->writeElement('maintenanceStatus', 'cancelled');				    
				}
				
				$doc->startElement('maintenanceAgency');
					$doc->writeElement('agencyName', 'American Numismatic Society');
				$doc->endElement();				
				
				//maintenanceHistory
				$doc->startElement('maintenanceHistory');
					$doc->startElement('maintenanceEvent');
						$doc->writeElement('eventType', 'derived');
						$doc->startElement('eventDateTime');
							$doc->writeAttribute('standardDateTime', date(DATE_W3C));
							$doc->text(date(DATE_RFC2822));
						$doc->endElement();
						$doc->writeElement('agentType', 'machine');
						$doc->writeElement('agent', 'PHP');
						$doc->writeElement('eventDescription', 'Generated Svoronos type NUDS document for Ptolemaic Coinage Online from the source Nomisma.org RDF. See Github repository for further revision history.');
					$doc->endElement();
				$doc->endElement();
				
				//rightsStmt
				$doc->startElement('rightsStmt');
					$doc->startElement('license');
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', 'http://opendatacommons.org/licenses/odbl/');
						$doc->writeAttribute('for', 'data');
						$doc->text('Open Data Commons Open Database License (ODbL)');
					$doc->endElement();
					$doc->startElement('license');
    					$doc->writeAttribute('xlink:type', 'simple');
    					$doc->writeAttribute('xlink:href', 'http://rightsstatements.org/vocab/NoC-US/1.0/');
    					$doc->text('No Copyright - United States');
					$doc->endElement();
				$doc->endElement();
				
				//semanticDeclaration
				$doc->startElement('semanticDeclaration');
					$doc->writeElement('prefix', 'dcterms');
					$doc->writeElement('namespace', 'http://purl.org/dc/terms/');
				$doc->endElement();
				
				$doc->startElement('semanticDeclaration');
					$doc->writeElement('prefix', 'nmo');
					$doc->writeElement('namespace', 'http://nomisma.org/ontology#');
				$doc->endElement();
				
				$doc->startElement('semanticDeclaration');
					$doc->writeElement('prefix', 'skos');
					$doc->writeElement('namespace', 'http://www.w3.org/2004/02/skos/core#');
				$doc->endElement();
			//end control
			$doc->endElement();
		
			//start descMeta
			$doc->startElement('descMeta');
		
			//title
			$doc->startElement('title');
    			$doc->writeAttribute('xml:lang', 'en');
    			$doc->text('Svoronos (1904-1908) no. ' . $row['ID']);
			$doc->endElement();
			
			/***** NOTES *****/
			$doc->startElement('noteSet');
    			$doc->startElement('note');
        			$doc->writeAttribute('xml:lang', 'en');
        			$doc->writeAttribute('semantic', 'skos:definition');
        			$doc->text('Coin type "Svoronos ' . $row['ID'] . '" as defined in the publication Svoronos, Joannes N. Ta nomismata tou kratous ton Ptolemaion. Athens, 1904-1908.');
			    $doc->endElement();
    			$doc->startElement('note');
        			$doc->writeAttribute('xml:lang', 'en');
        			$doc->writeAttribute('semantic', 'skos:note');
        			$doc->text('Derived label is "' . $row['note'] . '", though this may not reflect latest opinion on this type.');
    			$doc->endElement();
			$doc->endElement();
			
			/***** TYPEDESC *****/
			$doc->startElement('typeDesc');
			
				//objectType
				$doc->startElement('objectType');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/coin');
					$doc->text('Coin');
				$doc->endElement();
				
				//sort dates
				if (strlen($row['fromDate']) > 0 || strlen($row['toDate']) > 0){
					if (($row['fromDate'] == $row['toDate']) || (strlen($row['fromDate']) > 0 && strlen($row['toDate']) == 0)){
						//ascertain whether or not the date is a range
						$fromDate = intval(trim($row['fromDate']));
						
						$doc->startElement('date');
							$doc->writeAttribute('standardDate', number_pad($fromDate, 4));
							$doc->text(get_date_textual($fromDate));
						$doc->endElement();
					} else {
						$fromDate = intval(trim($row['fromDate']));
						$toDate= intval(trim($row['toDate']));
						
						//only write date if both are integers
						if (is_int($fromDate) && is_int($toDate)){
							$doc->startElement('dateRange');
								$doc->startElement('fromDate');
									$doc->writeAttribute('standardDate', number_pad($fromDate, 4));
									$doc->text(get_date_textual($fromDate));
								$doc->endElement();
								$doc->startElement('toDate');
									$doc->writeAttribute('standardDate', number_pad($toDate, 4));
									$doc->text(get_date_textual($toDate));
								$doc->endElement();
							$doc->endElement();
						}
					}
				}
				
				if (strlen($row['Denomination URI']) > 0){
					$vals = explode('|', $row['Denomination URI']);
					foreach ($vals as $val){
					    $uri =  $val;
					    $uncertainty = false;
					    $content = processUri($uri);
						
						$doc->startElement($content['element']);
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', $uri);
						if($uncertainty == true){
							$doc->writeAttribute('certainty', 'uncertain');
						}
						$doc->text($content['label']);
						$doc->endElement();
					}
				}
				
				//manufacture: if the SC no. includes P, it is plated, otherwise struck
				$doc->startElement('manufacture');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/struck');
					$doc->text('Struck');
				$doc->endElement();				
				
				if (strlen($row['Material URI']) > 0){
					$vals = explode('|', $row['Material URI']);
					foreach ($vals as $val){
						if (substr($val, -1) == '?'){
							$uri = substr($val, 0, -1);
							$uncertainty = true;
							$content = processUri($uri);
						} else {
							$uri =  $val;
							$uncertainty = false;
							$content = processUri($uri);
						}
						
						$doc->startElement($content['element']);
						$doc->writeAttribute('xlink:type', 'simple');
						$doc->writeAttribute('xlink:href', $uri);
						if($uncertainty == true){
							$doc->writeAttribute('certainty', 'uncertain');
						}
						$doc->text($content['label']);
						$doc->endElement();
					}
				}
				
				//authority
				if (strlen($row['Authority URI']) > 0 || strlen($row['Stated Authority URI']) > 0 || strlen($row['Issuer URI']) > 0){
					$doc->startElement('authority');
						if (strlen($row['Authority URI']) > 0){
						    
							$vals = explode('|', $row['Authority URI']);
							foreach ($vals as $val){
								if (substr($val, -1) == '?'){
									$uri = substr($val, 0, -1);
									$uncertainty = true;
									$content = processUri($uri);
								} else {
									$uri =  $val;
									$uncertainty = false;
									$content = processUri($uri);
								}
								$role = 'authority';
								
								$doc->startElement($content['element']);
									$doc->writeAttribute('xlink:type', 'simple');
									$doc->writeAttribute('xlink:role', $role);
									$doc->writeAttribute('xlink:href', $uri);
									if($uncertainty == true){
										$doc->writeAttribute('certainty', 'uncertain');
									}
									$doc->text($content['label']);
								$doc->endElement();
							}
						}
						if (strlen($row['Stated Authority URI']) > 0){
							$vals = explode('|', $row['Stated Authority URI']);
							foreach ($vals as $val){
								if (substr($val, -1) == '?'){
									$uri = substr($val, 0, -1);
									$uncertainty = true;
									$content = processUri($uri);
								} else {
									$uri =  $val;
									$uncertainty = false;
									$content = processUri($uri);
								}
								$role = 'statedAuthority';
								
								$doc->startElement($content['element']);
									$doc->writeAttribute('xlink:type', 'simple');
									$doc->writeAttribute('xlink:role', $role);
									$doc->writeAttribute('xlink:href', $uri);
									if($uncertainty == true){
										$doc->writeAttribute('certainty', 'uncertain');
									}
									$doc->text($content['label']);
								$doc->endElement();
							}
						}
						if (strlen($row['Issuer URI']) > 0){
							$vals = explode('|', $row['Issuer URI']);
							foreach ($vals as $val){
								if (substr($val, -1) == '?'){
									$uri = substr($val, 0, -1);
									$uncertainty = true;
									$content = processUri($uri);
								} else {
									$uri =  $val;
									$uncertainty = false;
									$content = processUri($uri);
								}
								$role = 'issuer';
								
								$doc->startElement($content['element']);
									$doc->writeAttribute('xlink:type', 'simple');
									$doc->writeAttribute('xlink:role', $role);
									$doc->writeAttribute('xlink:href', $uri);
									if($uncertainty == true){
										$doc->writeAttribute('certainty', 'uncertain');
									}
									$doc->text($content['label']);
								$doc->endElement();
							}
						}
					$doc->endElement();
				}
				
				//geography
				//mint
				if (strlen($row['Mint URI']) > 0 || strlen($row['Region URI']) > 0){
					$doc->startElement('geographic');
					if (strlen($row['Mint URI']) > 0){
					    $vals = explode('|', $row['Mint URI']);
					    foreach ($vals as $val){
					        $uri =  $val;
					        $uncertainty = false;
					        $content = processUri($uri);
					        
					        $doc->startElement('geogname');
					        $doc->writeAttribute('xlink:type', 'simple');
					        $doc->writeAttribute('xlink:role', 'mint');
					        $doc->writeAttribute('xlink:href', $uri);
					        if($uncertainty == true){
					            $doc->writeAttribute('certainty', 'uncertain');
					        }
					        $doc->text($content['label']);
					        $doc->endElement();
					        
					        unset($uncertainty);
					    }
					}
					
					if (strlen($row['Region URI']) > 0){
						$vals = explode('|', $row['Region URI']);
						foreach ($vals as $val){
						    $uri =  $val;
						    $uncertainty = ($row['Region Uncertain']) == 'TRUE' ? 'http://nomisma.org/id/uncertain_value' : null;
						    $content = processUri($uri);
							
							$doc->startElement('geogname');
								$doc->writeAttribute('xlink:type', 'simple');
								$doc->writeAttribute('xlink:role', 'region');
								$doc->writeAttribute('xlink:href', $uri);
								if(isset($uncertainty)){
									$doc->writeAttribute('certainty', $uncertainty);
								}
								$doc->text($content['label']);
							$doc->endElement();
							
							unset($uncertainty);
						}
					}
					
					$doc->endElement();
				}
				
				//Type Series should be explicit
				$doc->startElement('typeSeries');
					$doc->writeAttribute('xlink:type', 'simple');
					$doc->writeAttribute('xlink:href', 'http://nomisma.org/id/svoronos-1904');
					$doc->text('Svoronos (1904-1908)');
				$doc->endElement();
				
				//end typeDesc
				$doc->endElement();
			//end descMeta
			$doc->endElement();		
		//close NUDS
		$doc->endElement();
		
		//close file
		$doc->endDocument();
		$doc->flush();
	} else {
		echo "No number for {$row['ID']}.\n";
	}
}


 /***** FUNCTIONS *****/
function processUri($uri){
	GLOBAL $nomismaUris;
	$content = array();
	$uri = trim($uri);
	$type = '';
	$label = '';
	$node = '';
	
	//if the key exists, then formulate the XML response
	if (array_key_exists($uri, $nomismaUris)){
		$type = $nomismaUris[$uri]['type'];
		$label = $nomismaUris[$uri]['label'];
		if (isset($nomismaUris[$uri]['parent'])){
			$parent = $nomismaUris[$uri]['parent'];
		}
	} else {
		//if the key does not exist, look the URI up in Nomisma
		$pieces = explode('/', $uri);
		$id = $pieces[4];
		if (strlen($id) > 0){
			$uri = 'http://nomisma.org/id/' . $id;
			$file_headers = @get_headers($uri);
			
			//only get RDF if the ID exists
			if ($file_headers[0] == 'HTTP/1.1 200 OK'){
				$xmlDoc = new DOMDocument();
				$xmlDoc->load('http://nomisma.org/id/' . $id . '.rdf');
				$xpath = new DOMXpath($xmlDoc);
				$xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
				$xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
				$type = $xpath->query("/rdf:RDF/*")->item(0)->nodeName;
				$label = $xpath->query("descendant::skos:prefLabel[@xml:lang='en']")->item(0)->nodeValue;
				
				if (!isset($label)){
					echo "Error with {$id}\n";
				}
				
				//get the parent, if applicable
				$parents = $xpath->query("descendant::org:organization");
				if ($parents->length > 0){
					$nomismaUris[$uri] = array('label'=>$label,'type'=>$type, 'parent'=>$parents->item(0)->getAttribute('rdf:resource'));
					$parent = $parents->item(0)->getAttribute('rdf:resource');
				} else {
					$nomismaUris[$uri] = array('label'=>$label,'type'=>$type);
				}
			} else {
				//otherwise output the error
				echo "Error: {$uri} not found.\n";
				$nomismaUris[$uri] = array('label'=>$uri,'type'=>'nmo:Mint');
			}
		}
	}
	switch($type){
		case 'nmo:Mint':
		case 'nmo:Region':
			$content['element'] = 'geogname';
			$content['label'] = $label;
			if (isset($parent)){
				$content['parent'] = $parent;
			}
			break;
		case 'nmo:Material':
			$content['element'] = 'material';
			$content['label'] = $label;
			break;
		case 'nmo:Denomination':
			$content['element'] = 'denomination';
			$content['label'] = $label;
			break;
		case 'nmo:Manufacture':
			$content['element'] = 'manufacture';
			$content['label'] = $label;
			break;
		case 'nmo:ObjectType':
			$content['element'] = 'objectType';
			$content['label'] = $label;
			break;
		case 'rdac:Family':
			$content['element'] = 'famname';
			$content['label'] = $label;
			break;
		case 'foaf:Organization':
		case 'foaf:Group':
		case 'nmo:Ethnic':
			$content['element'] = 'corpname';
			$content['label'] = $label;
			break;
		case 'foaf:Person':
			$content['element'] = 'persname';
			$content['label'] = $label;
			if (isset($parent)){
				$content['parent'] = $parent;
			}
			break;
		case 'crm:E4_Period':
			$content['element'] = 'periodname';
			$content['label'] = $label;
			break;
		default:
			$content['element'] = 'ERR';
			$content['label'] = $label;
	}
	return $content;
}

//normalize integer into human-readable date
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

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
	if ($number > 0){
		$gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
	} elseif ($number < 0) {
		$gYear = '-' . str_pad((int) abs($number),$n,"0",STR_PAD_LEFT);
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

?>