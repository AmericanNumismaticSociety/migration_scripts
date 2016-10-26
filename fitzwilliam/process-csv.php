<?php 

$data = generate_json('ric.csv');
//store successful hits
$types = array();

//generate an array of records for outputting
$records = array();

$project = 'ocre';

foreach($data as $row){	
	if ($row["alternative_number.type"] == 'RIC' && strlen($row['alternative_number']) > 0){
		$reference = $row['alternative_number'];
		
		//ignore var and cf
		if (strpos($reference, 'var') === FALSE && strpos($reference, 'cf') === FALSE){
			$nomismaId = array();
			
			//clean reference
			$reference = str_replace(' ', '', str_replace(',', '.', $reference));
			$pieces = explode('.', $reference);
			
			//if there are 3 pieces then the volume is included.
			if (count($pieces) == 3){
				echo "Parsing {$row['priref']}: {$reference}\n";
				$volume = parse_volume($pieces[0], $row['creator']);
					
				//only process the authority if the array is not null
				if (isset($volume)){
					$page = $pieces[1];
					$num = $pieces[2];
			
					//only evaluate if the page is an integer
					if (is_numeric($page)){
						$authority = get_authority($volume['printVol'], (int)$page);
							
						if (isset($authority)){
							$nomismaId['volume'] = $volume['ocreVol'];
							$nomismaId['auth'] = $authority;
			
							//ignore uncertain attribution
							if (substr($num, -1) != '?'){
								$cointype = query_ocre($nomismaId, $num);
									
								if (isset($cointype)){
									echo "Matched {$cointype}\n";
									generate_record($row, $cointype);
								}
							}
						}
					}
				}
			} elseif (count($pieces) == 2){
				echo "Parsing {$row['priref']}: {$reference}\n";
					
				preg_match('/.*\((\d+)/', $row['creator'], $matches);
					
				//only process if the start year can be ascertained
				if (isset($matches[1])){
					$year = (int)$matches[1];
			
					if ($year > 395 || ($year == 395 && (strpos($row['creator'], 'Honorius') !== FALSE || strpos($row['creator'], 'Arcadius') !== FALSE))){
						$nomismaId['volume'] = 'ric.10';
						$vol = '10';
						//echo "{$vol}: {$row['creator']}\n";
					} elseif ($year >= 364 && $year <= 395){
						$nomismaId['volume'] = 'ric.9';
						$vol = '9';
						//echo "{$vol}: {$row['creator']}\n";
					} elseif (strpos($row['creator'], 'Constantius II') !== FALSE || strpos($row['creator'], 'Decentius') !== FALSE || strpos($row['creator'], 'Constans') !== FALSE || strpos($row['creator'], 'Constantine II') !== FALSE || strpos($row['creator'], 'Julianus') !== FALSE || strpos($row['creator'], 'Jovian') !== FALSE || strpos($row['creator'], 'Magnentius') !== FALSE){
						$nomismaId['volume'] = 'ric.8';
						$vol = '8';
					}
				}
				$page = $pieces[0];
				$num = $pieces[1];
					
				//only evaluate if the page is an integer
				if (isset($vol) && is_numeric($page)){
					$authority = get_authority($vol, (int)$page);
						
					if (isset($authority)){
						$nomismaId['auth'] = $authority;
							
						//ignore uncertain attribution
						if (substr($num, -1) != '?'){
							$cointype = query_ocre($nomismaId, $num);
								
							if (isset($cointype)){
								echo "Matched {$cointype}\n";
								generate_record($row, $cointype);
							}
						}
					}
				}
			}
		}
	}
}

//after the spreadsheet has been processed, create outputs
generate_csv($records, $project);
generate_rdf($records, $project);


/******* FUNCTIONS *********/
function generate_record($row, $cointype){
	GLOBAL $records;
	
	$record = array();
	
	$record['id'] = $row['priref'];
	$record['uri'] = "http://data.fitzmuseum.cam.ac.uk/id/object/{$row['priref']}";
	$record['objectnumber'] = $row['object_number'];
	$record['title'] = "Fitzwilliam Museum - Object {$row['object_number']}";
	$record['obv_image'] = $row['reproduction.reference'];
	$record['rev_image'] = str_replace('(1)', '(2)', $row['reproduction.reference']);
	$record['reference'] = "{$row['alternative_number.type']} {$row['alternative_number']} ({$row['creator']})";
	$record['cointype'] = $cointype;
	
	//screen scrape measurements (not consistent in CSV
	$url = "http://webapps.fitzmuseum.cam.ac.uk/explorer/index.php?oid={$row['priref']}";
	$fields = parse_html($url);
	if (isset($fields['weight'])){
		$record['weight'] = $fields['weight'];
	}
	
	if (isset($fields['axis'])){
		$record['axis'] = $fields['axis'];
	}
	
	$records[] = $record;	
}

function parse_html($url){
	echo "Scraping {$url}\n";

	//create an array of items to be parsed from HTML
	$fields = array();

	$html = file_get_contents($url);

	$dom = new DOMDocument();
	@$dom->loadHTML($html);
	$xpath = new DOMXpath($dom);

	//parse measurements
	$measurementsNode = $xpath->query("//p[@class='ttag'][contains(text(), 'Dimension')]/following-sibling::p[1]");
	if ($measurementsNode->length > 0){
		$text = $dom->saveHTML($measurementsNode->item(0));
		$text = str_replace('<p class="vtag">', '', str_replace('</p>', '', $text));

		$measurements = explode('<br>', trim($text));

		foreach ($measurements as $measurement){
			if (strpos($measurement, 'weight') !== FALSE){
				preg_match('/(\d+\.\d+)/', $measurement, $matches);

				if (is_numeric($matches[1])){
					$fields['weight'] = (float)$matches[1];
				}
			} elseif (strpos($measurement, 'axis') !== FALSE){
				preg_match('/(d+)/', $measurement, $matches);

				$axis360 = $matches[1];

				//round the 360 degree axis to the nearest clock integer
				$axis = round(($axis360 / 360) * 12);

				//if axis is between 0 and 12, reset 0 to 12 and return the value
				if ($axis <= 12 && $axis >= 0){
					if ($axis == 0){
						$axis = 12;
					}
						
					$fields['axis'] = $axis;
				}
			}
		}
	}
	return $fields;
}

//parse the OCRE ID; perform lookups as necessary and store successful matches in an array to improve efficiency
function query_ocre ($nomismaId, $id){
	GLOBAL $types;
	
	$prefix = implode('.', $nomismaId) . ".";
	
	//if there are letters:
	if (preg_match('/[a-zA-Z]/', $id)){
		//attempt to parse subtypes from RIC 9
		if ($nomismaId['volume'] == 'ric.9'){
			//first evaluate if the subtypes are in parentheses. subtype would be a number.
			if (preg_match('/([a-z0-9]+)\((\d+)\)/', $id)){				
				preg_match('/([a-z0-9]+)\((\d+)\)/', $id, $matches);
				
				//var_dump($matches);
				//first try to match on the subtype URI
				if (isset($matches[1]) and isset($matches[2])){
					$cointype = "http://numismatics.org/ocre/id/{$prefix}" . strtoupper($matches[1]) . '.' . $matches[2];
					
					if (in_array($cointype, $types)){
						return $cointype;
					} else {
						$file_headers = @get_headers($cointype);
						if ($file_headers[0] == 'HTTP/1.1 200 OK'){
							$types[] = $cointype;
							return $cointype;
						}
					}
				}
			} elseif (preg_match('/([0-9]+)\(([a-z])\)/', $id)){
				//next, evaluate whether the character in the parentheses is a letter. this is part of the type number
				preg_match('/([0-9]+)\(([a-z])\)/', $id, $matches);
				//first try to match on the subtype URI
				if (isset($matches[1]) and isset($matches[2])){
					$cointype = "http://numismatics.org/ocre/id/{$prefix}" . $matches[1] . strtoupper($matches[2]);
						
					if (in_array($cointype, $types)){
						return $cointype;
					} else {
						$file_headers = @get_headers($cointype);
						if ($file_headers[0] == 'HTTP/1.1 200 OK'){
							$types[] = $cointype;
							return $cointype;
						}
					}
				}
				
			} elseif (preg_match('/\d+([a-z])([a-z+])/', $id)){
				//now look for matches where the subtype is concatenated into the parent type ID number, e.g., 24bxia
				
				preg_match('/\d+([a-z])([a-z+])/', $id, $matches);
				if (isset($matches[1]) and isset($matches[2])){
					$cointype = "http://numismatics.org/ocre/id/{$prefix}" . strtoupper($matches[1]) . '.' . $matches[2];
					
					if (in_array($cointype, $types)){
						return $cointype;
					} else {
						$file_headers = @get_headers($cointype);
						if ($file_headers[0] == 'HTTP/1.1 200 OK'){
							$types[] = $cointype;
							return $cointype;
						} else {
							//if it can't find the subtype, just try to match on parent type
							$cointype = "http://numismatics.org/ocre/id/{$prefix}" . strtoupper($matches[1]);
							if (in_array($cointype, $types)){
								return $cointype;
							} else {
								$file_headers = @get_headers($cointype);
								if ($file_headers[0] == 'HTTP/1.1 200 OK'){
									$types[] = $cointype;
									return $cointype;
								}
							}
						}
					}
				}
				
			} else {
				$upper = strtoupper($id);
				$cointype = "http://numismatics.org/ocre/id/{$prefix}" . urlencode($upper);
				if (in_array($cointype, $types)){
					return $cointype;
				} else {
					$file_headers = @get_headers($cointype);
					if ($file_headers[0] == 'HTTP/1.1 200 OK'){
						$types[] = $cointype;
						return $cointype;
					}
				}
			}
		} else {
			//otherwise, process non-RIC 9 volumes normally
			
			//try uppercase first
			$upper = strtoupper($id);
			$cointype = "http://numismatics.org/ocre/id/{$prefix}" . urlencode($upper);
			
			if (in_array($cointype, $types)){
				return $cointype;
			} else {
				$file_headers = @get_headers($cointype);
				if ($file_headers[0] == 'HTTP/1.1 200 OK'){
					$types[] = $cointype;
					return $cointype;
				} else {
					//then try default
					$cointype = "http://numismatics.org/ocre/id/{$prefix}" . urlencode($id);
					if (in_array($cointype, $types)){
						return $cointype;
					} else {
						$file_headers = @get_headers($cointype);
						if ($file_headers[0] == 'HTTP/1.1 200 OK'){
							$types[] = $cointype;
							return $cointype;
						}
					}					
					
				}
			}			
		}
	} else {
		$cointype = "http://numismatics.org/ocre/id/{$prefix}" . urlencode($id);
		
		if (in_array($cointype, $types)){
			return $cointype;
		} else {
			$file_headers = @get_headers($cointype);
			if ($file_headers[0] == 'HTTP/1.1 200 OK'){
				$types[] = $cointype;
				return $cointype;
			}
		}		
	}	
}

function parse_volume($vol, $creator){
	switch($vol){
		case 'I':
		case '1(2)':
			$parsedVolume = '1';
			$volume = 'ric.1(2)';
			break;
		case 'II':
			$parsedVolume = '2';
			$volume = 'ric.2';
			break;
		case '2(2)-1':
			$parsedVolume = '2_1';
			$volume = 'ric.2_1(2)';
			break;
		case 'III':
		case '3':
		case '[3]':
			$parsedVolume = '3';
			$volume = 'ric.3';
			break;
		case '4-1':
			$parsedVolume = '4-1';
			$volume = 'ric.4';
			break;
		case '4-2':
			$parsedVolume = '4-2';
			$volume = 'ric.4';
		case 'IV':
		case 'iv':
		case '4-3':
			$parsedVolume = '4-3';
			$volume = 'ric.4';
			break;
		case 'V':
			if (strpos($creator, 'Postumus') === FALSE){
				$parsedVolume = '5-1';
				$volume = 'ric.5';
			}
			break;
		case '5-1':
			$parsedVolume = '5-1';
			$volume = 'ric.5';
			break;
		case '5-2':
			$parsedVolume = '5-2';
			$volume = 'ric.5';
			break;
		case 'VI':
		case '6':
			$parsedVolume = '6';
			$volume = 'ric.6';
			break;			
		case 'VII':
		case '7':
			$parsedVolume = '7';
			$volume = 'ric.7';
			break;
		case 'VIII':
		case '8':
			$parsedVolume = '8';
			$volume = 'ric.8';
			break;
		case 'IX':
		case '9':
			$parsedVolume = '9';
			$volume = 'ric.9';
			break;
		case 'X':
		case '10':
			$parsedVolume = '10';
			$volume = 'ric.10';
			break;
		default:
			$parsedVolume = null;
			$volume = null;
	}
	
	if (isset($parsedVolume) && isset($volume)){
		return array('printVol'=>$parsedVolume, 'ocreVol'=>$volume);
	} else {
		return null;
	}
}

//use the volume and page numbers to determine the authority code
function get_authority($volume, $p){
	$authority = null;

	if ($volume == '1'){
		if ($p >= 41 && $p <= 86){
			$authority = 'aug';
		} elseif ($p >= 93 && $p <= 101){
			$authority = 'tib';
		} elseif ($p >= 108 && $p <= 113){
			$authority = 'gai';
		} elseif ($p >= 119 && $p <= 132){
			$authority = 'cl';
		} elseif ($p >= 150 && $p <= 186){
			$authority = 'ner';
		} elseif ($p >= 191 && $p <= 196){
			$authority = 'clm';
		} elseif ($p >= 201 && $p <= 215){
			$authority = 'cw';
		} elseif ($p >= 232 && $p <= 257){
			$authority = 'gal';
		} elseif ($p >= 260 && $p <= 261){
			$authority = 'ot';
		} elseif ($p >= 268 && $p <= 277){
			$authority = 'vit';
		}

	} elseif ($volume == '2_1') {
		if ($p >= 58 && $p <= 180){
			$authority = 'ves';
		} elseif ($p >= 199 && $p <= 236){
			$authority = 'tit';
		} elseif ($p >= 266 && $p <= 331){
			$authority = 'dom';
		}
	} elseif ($volume == '2'){
		if ($p >= 216 && $p <= 219){
			$authority = 'anys';
		} elseif ($p >= 223 && $p <= 233){
			$authority = 'ner';
		} elseif ($p >= 245 && $p <= 313){
			$authority = 'tr';
		} elseif ($p >= 338 && $p <= 485){
			$authority = 'hdn';
		}
	} elseif ($volume == '3'){
		if ($p >= 25 && $p <= 194){
			$authority = 'ant';
		} elseif ($p >= 214 && $p <= 355){
			$authority = 'm_aur';
		} elseif ($p >= 366 && $p <= 443){
			$authority = 'com';
		}
	} elseif ($volume == '4-1'){
		if ($p >= 7 && $p <= 12){
			$authority = 'pert';
		} elseif ($p >= 15 && $p <= 18){
			$authority = 'dj';
		} elseif ($p >= 22 && $p <= 39){
			$authority = 'pn';
		} elseif ($p >= 44 && $p <= 53){
			$authority = 'ca';
		} elseif ($p >= 92 && $p <= 211){
			$authority = 'ss';
		} elseif ($p >= 212 && $p <= 313){
			$authority = 'crl';
		} elseif ($p >= 314 && $p <= 343){
			$authority = 'ge';
		}
	} elseif ($volume == '4-2'){
		if ($p >= 5 && $p <= 22){
			$authority = 'mcs';
		} elseif ($p >= 28 && $p <= 61){
			$authority = 'el';
		} elseif ($p >= 70 && $p <= 128){
			$authority = 'sa';
		} elseif ($p >= 138 && $p <= 157){
			$authority = 'max_i';
		} elseif ($p >= 160 && $p <= 162){
			$authority = 'gor_i';
		} elseif ($p >= 163 && $p <= 164){
			$authority = 'gor_ii';
		} elseif ($p >= 169 && $p <= 171){
			$authority = 'balb';
		} elseif ($p >= 173 && $p <= 177){
			$authority = 'pup';
		}
	} elseif ($volume == '4-3'){
		if ($p >= 15 && $p <= 53){
			$authority = 'gor_iii';
		} elseif ($p >= 68 && $p <= 106){
			$authority = 'ph_i';
		} elseif ($p >= 120 && $p <= 150){
			$authority = 'tr_d';
		} elseif ($p >= 159 && $p <= 173){
			$authority = 'tr_g';
		} elseif ($p >= 173 && $p <= 189){
			$authority = 'vo';
		} elseif ($p >= 194 && $p <= 202){
			$authority = 'aem';
		} elseif ($p >= 205 && $p <= 206){
			$authority = 'uran_an';
		}
	} elseif ($volume == '5-1'){
		if ($p >= 37 && $p <= 60){
			$authority = 'val_i';
		} else if ($p >= 61 && $p <= 62){
			$authority = 'val_i-gall';
		} else if ($p == 63){
			$authority = 'val_i-gall-val_ii-sala';
		} else if ($p >= 64 && $p <= 65){
			$authority = 'mar';
		} else if ($p >= 66 && $p <= 104){
			$authority = 'gall(1)';
		} else if ($p == 105){
			$authority = 'gall_sala(1)';
		} else if ($p == 106){
			$authority = 'gall_sals';
		} else if ($p >= 107 && $p <= 115){
			$authority = 'sala(1)';
		} else if ($p >= 116 && $p <= 122){
			$authority = 'val_ii';
		} else if ($p >= 123 && $p <= 127){
			$authority = 'sals';
		} else if ($p == 128){
			$authority = 'qjg';
		} else if ($p >= 129 && $p <= 190){
			$authority = 'gall(2)';
		} else if ($p == 191){
			$authority = 'gall_sala(2)';
		} else if ($p >= 192 && $p <= 200){
			$authority = 'sala(2)';
		} elseif ($p >= 211 && $p <= 237){
			$authority = 'cg';
		} elseif ($p >= 239 && $p <= 247){
			$authority = 'qu';
		} elseif ($p >= 263 && $p <= 312){
			$authority = 'aur';
		} elseif ($p == 313){
			$authority = 'aur_seva';
		} elseif ($p >= 314 && $p <= 318){
			$authority = 'seva';
		} elseif ($p >= 327 && $p <= 348){
			$authority = 'tac';
		} elseif ($p >= 350 && $p <= 360){
			$authority = 'fl';
		}
	} elseif ($volume == '5-2'){
		if ($p >= 20 && $p <= 121){
			$authority = 'pro';
		}
	} elseif ($volume == '6'){
		if ($p >= 123 && $p <= 140){
			$authority = 'lon';
		}
		elseif ($p >= 163 && $p <= 228){
			$authority = 'tri';
		}
		elseif ($p >= 241 && $p <= 265){
			$authority = 'lug';
		}
		elseif ($p >= 279 && $p <= 298){
			$authority = 'tic';
		}
		elseif ($p >= 310 && $p <= 328){
			$authority = 'aq';
		}
		elseif ($p >= 350 && $p <= 392){
			$authority = 'rom';
		}
		elseif ($p >= 400 && $p <= 410){
			$authority = 'ost';
		}
		elseif ($p >= 422 && $p <= 435){
			$authority = 'carth';
		}
		elseif ($p >= 455 && $p <= 485){
			$authority = 'sis';
		}
		elseif ($p >= 491 && $p <= 500){
			$authority = 'serd';
		}
		elseif ($p >= 509 && $p <= 519){
			$authority = 'thes';
		}
		elseif ($p >= 529 && $p <= 542){
			$authority = 'her';
		}
		elseif ($p >= 553 && $p <= 568){
			$authority = 'nic';
		}
		elseif ($p >= 578 && $p <= 595){
			$authority = 'cyz';
		}
		elseif ($p >= 612 && $p <= 644){
			$authority = 'anch';
		}
		elseif ($p >= 660 && $p <= 686){
			$authority = 'alex';
		}
	} elseif ($volume == '7'){
		if ($p >= 97 && $p <= 116){
			$authority = 'lon';
		}
		elseif ($p >= 122 && $p <= 142){
			$authority = 'lug';
		}
		elseif ($p >= 162 && $p <= 223){
			$authority = 'tri';
		}
		elseif ($p >= 234 && $p <= 279){
			$authority = 'ar';
		}
		elseif ($p >= 296 && $p <= 347){
			$authority = 'rom';
		}
		elseif ($p >= 360 && $p <= 387){
			$authority = 'tic';
		}
		elseif ($p >= 392 && $p <= 147){
			$authority = 'aq';
		}
		elseif ($p >= 422 && $p <= 460){
			$authority = 'sis';
		}
		elseif ($p >= 467 && $p <= 477){
			$authority = 'sir';
		}
		elseif ($p >= 479 && $p <= 480){
			$authority = 'serd';
		}
		elseif ($p >= 498 && $p <= 530){
			$authority = 'thes';
		}
		elseif ($p >= 541 && $p <= 561){
			$authority = 'her';
		}
		elseif ($p >= 569 && $p <= 590){
			$authority = 'cnp';
		}
		elseif ($p >= 597 && $p <= 635){
			$authority = 'nic';
		}
		elseif ($p >= 643 && $p <= 660){
			$authority = 'cyz';
		}
		elseif ($p >= 675 && $p <= 697){
			$authority = 'anch';
		}
		elseif ($p >= 702 && $p <= 712){
			$authority = 'alex';
		}
	} elseif ($volume == '8'){
		if ($p >= 121 && $p <= 124){
			$authority = 'amb';
		}
		elseif ($p >= 138 && $p <= 169){
			$authority = 'tri';
		}
		elseif ($p >= 177 && $p <= 196){
			$authority = 'lug';
		}
		elseif ($p >= 204 && $p <= 231){
			$authority = 'ar';
		}
		elseif ($p >= 233 && $p <= 233){
			$authority = 'med';
		}
		elseif ($p >= 248 && $p <= 305){
			$authority = 'rom';
		}
		elseif ($p >= 314 && $p <= 338){
			$authority = 'aq';
		}
		elseif ($p >= 348 && $p <= 381){
			$authority = 'sis';
		}
		elseif ($p >= 384 && $p <= 394){
			$authority = 'sir';
		}
		elseif ($p >= 401 && $p <= 425){
			$authority = 'thes';
		}
		elseif ($p >= 429 && $p <= 439){
			$authority = 'her';
		}
		elseif ($p >= 446 && $p <= 465){
			$authority = 'cnp';
		}
		elseif ($p >= 470 && $p <= 485){
			$authority = 'nic';
		}
		elseif ($p >= 489 && $p <= 501){
			$authority = 'cyz';
		}
		elseif ($p >= 511 && $p <= 534){
			$authority = 'anch';
		}
		elseif ($p >= 538 && $p <= 546){
			$authority = 'alex';
		}
	} elseif ($volume == '9'){
		if ($p == 2){
			$authority = 'lon';
		}
		elseif ($p >= 13 && $p <= 34){
			$authority = 'tri';
		}
		elseif ($p >= 42 && $p <= 53){
			$authority = 'lug';
		}
		elseif ($p >= 61 && $p <= 70){
			$authority = 'ar';
		}
		elseif ($p >= 75 && $p <= 84){
			$authority = 'med';
		}
		elseif ($p >= 94 && $p <= 107){
			$authority = 'aq';
		}
		elseif ($p >= 116 && $p <= 136){
			$authority = 'rom';
		}
		elseif ($p >= 145 && $p <= 155){
			$authority = 'sis';
		}
		elseif ($p >= 158 && $p <= 162){
			$authority = 'sir';
		}
		elseif ($p >= 173 && $p <= 188){
			$authority = 'thes';
		}
		elseif ($p >= 191 && $p <= 199){
			$authority = 'her';
		}
		elseif ($p >= 209 && $p <= 236){
			$authority = 'cnp';
		}
		elseif ($p >= 239 && $p <= 247){
			$authority = 'cyz';
		}
		elseif ($p >= 250 && $p <= 263){
			$authority = 'nic';
		}
		elseif ($p >= 272 && $p <= 295){
			$authority = 'anch';
		}
		elseif ($p >= 298 && $p <= 304){
			$authority = 'alex';
		}
	} elseif ($volume == '10'){
		if ($p >= 239 && $p <= 252){
			$authority = 'arc_e';
		}
		elseif ($p >= 253 && $p <= 277){
			$authority = 'theo_ii_e';
		}
		elseif ($p >= 278 && $p <= 283){
			$authority = 'marc_e';
		}
		elseif ($p >= 284 && $p <= 296){
			$authority = 'leo_i_e';
		}
		elseif ($p >= 267 && $p <= 297){
			$authority = 'leo_ii_e';
		}
		elseif ($p >= 297 && $p <= 298){
			$authority = 'leo_ii-zen_e';
		}
		elseif ($p >= 299 && $p <= 300){
			$authority = 'zeno(1)_e';
		}
		elseif ($p >= 301 && $p <= 303){
			$authority = 'bas_e';
		}
		elseif ($p >= 303 && $p <= 305){
			$authority = 'bas-mar_e';
		}
		elseif ($p >= 306 && $p <= 315){
			$authority = 'zeno(2)_e';
		}
		elseif ($p >= 316 && $p <= 316){
			$authority = 'leon_e';
		}
		elseif ($p >= 317 && $p <= 342){
			$authority = 'hon_w';
		}
		elseif ($p >= 343 && $p <= 346){
			$authority = 'pr_att_w';
		}
		elseif ($p >= 347 && $p <= 350){
			$authority = 'con_iii_w';
		}
		elseif ($p >= 351 && $p <= 351){
			$authority = 'max_barc_w';
		}
		elseif ($p >= 352 && $p <= 354){
			$authority = 'jov_w';
		}
		elseif ($p >= 355 && $p <= 358){
			$authority = 'theo_ii_w';
		}
		elseif ($p >= 359 && $p <= 361){
			$authority = 'joh_w';
		}
		elseif ($p >= 363 && $p <= 384){
			$authority = 'valt_iii_w';
		}
		elseif ($p >= 385 && $p <= 385){
			$authority = 'pet_max_w';
		}
		elseif ($p >= 386 && $p <= 387){
			$authority = 'marc_w';
		}
		elseif ($p >= 388 && $p <= 390){
			$authority = 'av_w';
		}
		elseif ($p >= 392 && $p <= 398){
			$authority = 'leo_i_w';
		}
		elseif ($p >= 399 && $p <= 405){
			$authority = 'maj_w';
		}
		elseif ($p >= 406 && $p <= 409){
			$authority = 'lib_sev_w';
		}
		elseif ($p >= 411 && $p <= 421){
			$authority = 'anth_w';
		}
		elseif ($p >= 422 && $p <= 423){
			$authority = 'oly_w';
		}
		elseif ($p >= 424 && $p <= 426){
			$authority = 'glyc_w';
		}
		elseif ($p >= 427 && $p <= 434){
			$authority = 'jul_nep_w';
		}
		elseif ($p >= 435 && $p <= 437){
			$authority = 'bas_w';
		}
		elseif ($p >= 438 && $p <= 441){
			$authority = 'rom_aug_w';
		}
		elseif ($p >= 442 && $p <= 442){
			$authority = 'odo_w';
		}
		elseif ($p >= 443 && $p <= 449){
			$authority = 'zeno_w';
		}
		elseif ($p >= 450 && $p <= 462){
			$authority = 'visi';
		}
		elseif ($p >= 463 && $p <= 464){
			$authority = 'gallia';
		}
		elseif ($p >= 465 && $p <= 466){
			$authority = 'spa';
		}
		elseif ($p >= 467 && $p <= 470){
			$authority = 'afr';
		}
	}

	return $authority;
}

function generate_rdf($records, $project){
	//start RDF/XML file
	//use XML writer to generate RDF
	$writer = new XMLWriter();
	$writer->openURI("fitzwilliam-{$project}.rdf");
	//$writer->openURI('php://output');
	$writer->startDocument('1.0','UTF-8');
	$writer->setIndent(true);
	//now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
	$writer->setIndentString("    ");

	$writer->startElement('rdf:RDF');
	$writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema#');
	$writer->writeAttribute('xmlns:nm', "http://nomisma.org/id/");
	$writer->writeAttribute('xmlns:nmo', "http://nomisma.org/ontology#");
	$writer->writeAttribute('xmlns:dcterms', "http://purl.org/dc/terms/");
	$writer->writeAttribute('xmlns:foaf', "http://xmlns.com/foaf/0.1/");
	$writer->writeAttribute('xmlns:geo', "http://www.w3.org/2003/01/geo/wgs84_pos#");
	$writer->writeAttribute('xmlns:rdf', "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
	$writer->writeAttribute('xmlns:void', "http://rdfs.org/ns/void#");

	foreach ($records as $record){
		if (isset($record['cointype'])){
			$writer->startElement('nmo:NumismaticObject');
			$writer->writeAttribute('rdf:about', $record['uri']);
			$writer->startElement('dcterms:title');
			$writer->writeAttribute('xml:lang', 'en');
			$writer->text($record['title']);
			$writer->endElement();
			$writer->writeElement('dcterms:identifier', $record['objectnumber']);
			$writer->startElement('dcterms:publisher');
			$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/fitzwilliam');
			$writer->endElement();
			$writer->startElement('nmo:hasCollection');
			$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/fitzwilliam');
			$writer->endElement();
			$writer->startElement('nmo:hasTypeSeriesItem');
			$writer->writeAttribute('rdf:resource', $record['cointype']);
			$writer->endElement();

			//conditional measurement data
			if (isset($record['weight'])){
				$writer->startElement('nmo:hasWeight');
				$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#decimal');
				$writer->text($record['weight']);
				$writer->endElement();
			}
			if (isset($record['axis'])){
				$writer->startElement('nmo:hasAxis');
				$writer->writeAttribute('rdf:datatype', 'http://www.w3.org/2001/XMLSchema#integer');
				$writer->text($record['axis']);
				$writer->endElement();
			}

			//conditional images
			if (isset($record['obv_image'])){
				$writer->startElement('nmo:hasObverse');
				$writer->startElement('rdf:Description');
				$writer->startElement('foaf:depiction');
				$writer->writeAttribute('rdf:resource', $record['obv_image']);
				$writer->endElement();
				$writer->endElement();
				$writer->endElement();
			}
			if (isset($record['rev_image'])){
				$writer->startElement('nmo:hasReverse');
				$writer->startElement('rdf:Description');
				$writer->startElement('foaf:depiction');
				$writer->writeAttribute('rdf:resource', $record['rev_image']);
				$writer->endElement();
				$writer->endElement();
				$writer->endElement();
			}

			//void:inDataset
			$writer->startElement('void:inDataset');
			$writer->writeAttribute('rdf:resource', 'http://www.fitzmuseum.cam.ac.uk/');
			$writer->endElement();

			//end nmo:NumismaticObject
			$writer->endElement();
		}
	}

	//end RDF file
	$writer->endElement();
	$writer->flush();
}

//generate csv
function generate_csv($records, $project){
	$csv = '"objectnumber","title","uri","reference","type"' . "\n";

	foreach ($records as $record){
		$csv .= '"' . $record['objectnumber'] . '","' . $record['title'] . '","' . $record['uri'] . '","' . (isset($record['reference']) ? $record['reference'] : '') . '","' . (isset($record['cointype']) ? $record['cointype'] : '') . '"' . "\n";
	}

	file_put_contents("concordances-{$project}.csv", $csv);
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