<?php 

$page = 1;
$apiKey = '';
$records = array();

//parse each page of the API call
parse_page($page, $apiKey);

//following the parsing of each page, construct CSV;

//generate exports
generate_csv($records);
generate_rdf($records);

/********* FUNCTIONS **********/
function parse_page($page, $apiKey){
	GLOBAL $records;
	
	echo "Page {$page}\n";
	$service = 'http://api.harvardartmuseums.org/object?size=100&classification=Coins&q=(standardreferencenumber:RRC+OR+standardreferencenumber:RIC+OR+standardreferencenumber:Price)+AND+department:"Department%20of%20Ancient%20and%20Byzantine%20Art%20%26%20Numismatics"&apikey=' . $apiKey . '&page=' . $page;
	
	$json = file_get_contents($service);
	$data = json_decode($json);
	
	foreach ($data->records as $record){
		$references = explode(';', $record->standardreferencenumber);
		
		foreach ($references as $reference){
			if (preg_match('/RRC/', $reference)){
				$num = explode(' ', $reference);
				$row = array();
					
				//generate record metadata array
				$row = construct_metadata($record);
				$row['reference'] = $reference;
				
				//ignore uncertain coins
				if (substr($num[1], -1) != '?'){
					$cointype = 'http://numismatics.org/crro/id/rrc-' . str_replace('/', '.', $num[1]);
				
					$file_headers = @get_headers($cointype);
					if ($file_headers[0] == 'HTTP/1.1 200 OK'){
						echo "{$row['objectnumber']}: {$cointype}\n";
						$row['cointype'] = $cointype;
					} 
				}
				$records[] = $row;
				//end RRC processing
			} elseif (preg_match('/Price/', $reference)){
				$num = explode(' ', $reference);
				$row = array();
				
				//generate record metadata array
				$row = construct_metadata($record);
				$row['reference'] = $reference;
				
				//ignore uncertain coins
				if (substr($num[1], -1) != '?'){
					$cointype = 'http://numismatics.org/pella/id/price.' . $num[1];
					$file_headers = @get_headers($cointype);
					if ($file_headers[0] == 'HTTP/1.1 200 OK'){
						echo "{$row['objectnumber']}: {$cointype}\n";
						$row['cointype'] = $cointype;
					}
				}
				$records[] = $row;
				//end PELLA processing				
			} elseif (preg_match('/RIC/', $reference)){
				$originalReference = $reference;
				
				//1. get volume number and then strip from reference. 2. trim leading or trailing commas. 3. trim spaces
				if (strpos($reference, 'RIC I 2') !== FALSE){
					$parsedVolume = 'RIC I 2';					
					$volume = 'ric.1(2)';					
				} elseif (strpos($reference, 'RIC II.1 2') !== FALSE){
					$parsedVolume = 'RIC II.1 2';
					$volume = 'ric.2_1(2)';										
				} elseif (strpos($reference, 'RIC II (2)') !== FALSE){
					$parsedVolume = 'RIC II (2)';
					$volume = 'ric.2_1(2)';					
				} elseif (strpos($reference, 'RIC III') !== FALSE){
					$parsedVolume = 'RIC III';
					$volume = 'ric.3';					
				} elseif (strpos($reference, 'RIC II') !== FALSE){
					$parsedVolume = 'RIC II';
					$volume = 'ric.2';					
				} elseif (strpos($reference, 'RIC IV(B)') !== FALSE){
					$parsedVolume = 'RIC IV(B)';
					$volume = 'ric.4';					
				} elseif (strpos($reference, 'RIC IV(C)') !== FALSE){
					$parsedVolume = 'RIC IV(C)';
					$volume = 'ric.4';					
				} elseif (strpos($reference, 'RIC IV') !== FALSE){
					$parsedVolume = 'RIC IV';
					$volume = 'ric.4';					
				}elseif (strpos($reference, 'RIC V(1)') !== FALSE){
					$parsedVolume = 'RIC V(1)';
					$volume = 'ric.5';					
				} elseif (strpos($reference, 'RIC V(2)') !== FALSE){
					$parsedVolume = 'RIC V(2)';
					$volume = 'ric.5';
				} elseif (strpos($reference, 'RIC VIII') !== FALSE){
					$parsedVolume = 'RIC VIII';
					$volume = 'ric.8';					
				} elseif (strpos($reference, 'RIC VII') !== FALSE){
					$parsedVolume = 'RIC VII';
					$volume = 'ric.7';					
				} elseif (strpos($reference, 'RIC VI') !== FALSE){
					$parsedVolume = 'RIC VI';
					$volume = 'ric.6';
				} elseif (strpos($reference, 'RIC V') !== FALSE){
					$parsedVolume = 'RIC V';
					$volume = 'ric.5';					
				} elseif (strpos($reference, 'RIC X') !== FALSE){					
					$parsedVolume = 'RIC X';
					$volume = 'ric.10';
				} else {
					$volume = null;
				}
				
				echo "{$originalReference}\n";
					
				//strip parsed Volume and trailing/leading commas and whitespace
				$reference = trim(trim(str_replace($parsedVolume, '', $reference), ','));
				
				//strip whitespace following the p.
				$reference = str_replace('p. ', 'p.', $reference);

				//only process currently published volumes
				if ($volume != null && strlen($reference) > 0){
					$row = array();
					
					//generate record metadata array
					$row = construct_metadata($record);
					$row['reference'] = $originalReference;
					
					//parse remaining numbers of the reference, determine page number based on 'p.'
					$arr = explode(' ', $reference);
					$pageNumber = '';
					$id = '';
					foreach ($arr as $item){					
						if (strpos($item, 'p.') !== FALSE){
							$pageNumber = trim(trim(str_replace('p.', '', $item), ','));
						} else {
							$id = trim(trim($item, ','));
						}
					}
						
					if (strlen($id) > 0 && strlen($pageNumber) > 0){
						//ignore uncertain coins
						if (substr($id, -1) != '?'){
							$authority = get_authority($parsedVolume, $pageNumber);
								
							if ($authority != null){
								//if $authority is properly resolved, try OCRE lookups
								$prefix = "{$volume}.{$authority}.";
								if (preg_match('/[a-zA-Z]/', $id)){
									//try uppercase first
									$upper = strtoupper($id);
									$cointype = "http://numismatics.org/ocre/id/{$prefix}" . urlencode($upper);
									$file_headers = @get_headers($cointype);
									if ($file_headers[0] == 'HTTP/1.1 200 OK'){
										echo "{$row['objectnumber']}: {$cointype}\n";
										$row['cointype'] = $cointype;
									} else {
										//then try default
										$cointype = "http://numismatics.org/ocre/id/{$prefix}" . urlencode($id);
										$file_headers = @get_headers($cointype);
										if ($file_headers[0] == 'HTTP/1.1 200 OK'){												
											echo "{$row['objectnumber']}: {$cointype}\n";
											$row['cointype'] = $cointype;
										}
									}
								} else {
									$cointype = "http://numismatics.org/ocre/id/{$prefix}" . urlencode($id);
									$file_headers = @get_headers($cointype);
									if ($file_headers[0] == 'HTTP/1.1 200 OK'){												
										echo "{$row['objectnumber']}: {$cointype}\n";
										$row['cointype'] = $cointype;
									} 
								}									
							}								
						}					
					}
					$records[] = $row;
				}				
				//end RIC processing
			}
		}
	}
	
	//if the page isn't the last, then move forward to the next one.
	if ($page < $data->info->pages){
		$page++;
		parse_page($page, $apiKey);
	}
}

//this function will create an array of values that will form both the concordance list and RDF dump
function construct_metadata ($record){
	$row = array();
	
	$row['objectnumber'] = $record->objectnumber;
	$row['title'] = str_replace('"', "'", $record->title);
	$row['uri'] = $record->url;
	
	//image
	if (property_exists($record, 'primaryimageurl')){
		$row['comThumb'] = str_replace('dlvr', 'dynmc', $record->primaryimageurl) . '?width=240';
		$row['comRef'] = $record->primaryimageurl;
	}
	
	//physical attributes
	if (property_exists($record, 'dimensions')){
		preg_match('/(.*)\sg/', $record->dimensions, $matches);
		if (isset($matches[1]) && is_numeric($matches[1])){
			$row['weight'] = $matches[1];
		}
	}
	if (isset($record->details->coins->dieaxis)){
		$row['axis'] = $record->details->coins->dieaxis;
	}	
	
	return $row;
}

//generate csv
function generate_csv($records){
	$csv = '"objectnumber","title","uri","reference","type"' . "\n";
	
	foreach ($records as $record){
		$csv .= '"' . $record['objectnumber'] . '","' . $record['title'] . '","' . $record['uri'] . '","' . $record['reference'] . '","' . (isset($record['cointype']) ? $record['cointype'] : '') . '"' . "\n";
	}
	
	file_put_contents('concordances.csv', $csv);
}

function generate_rdf($records){
	//start RDF/XML file
	//use XML writer to generate RDF
	$writer = new XMLWriter();
	$writer->openURI("harvard.rdf");
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
					$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/harvard');
				$writer->endElement();
				$writer->startElement('nmo:hasCollection');
					$writer->writeAttribute('rdf:resource', 'http://nomisma.org/id/harvard');
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
				if (isset($record['comRef'])){
					$writer->startElement('foaf:thumbnail');
						$writer->writeAttribute('rdf:resource', $record['comThumb']);
					$writer->endElement();
					$writer->startElement('foaf:depiction');
						$writer->writeAttribute('rdf:resource', $record['comRef']);
					$writer->endElement();
				}
				
				//void:inDataset
				$writer->startElement('void:inDataset');
					$writer->writeAttribute('rdf:resource', 'http://www.harvardartmuseums.org/');
				$writer->endElement();
				
			//end nmo:NumismaticObject
			$writer->endElement();
		}
	}
	
	//end RDF file
	$writer->endElement();
	$writer->flush();
}

//use the volume and page numbers to determine the authority code
function get_authority($volume, $p){
	$authority = null;
	
	if ($volume == 'RIC I 2'){
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
		
	} elseif ($volume == 'RIC II.1 2' || $volume == 'RIC II (2)') {
		if ($p >= 58 && $p <= 180){
			$authority = 'ves';
		} elseif ($p >= 199 && $p <= 236){
			$authority = 'tit';
		} elseif ($p >= 266 && $p <= 331){
			$authority = 'dom';
		}
	} elseif ($volume == 'RIC II'){
		if ($p >= 216 && $p <= 219){
			$authority = 'anys';
		} elseif ($p >= 223 && $p <= 233){
			$authority = 'ner';
		} elseif ($p >= 245 && $p <= 313){
			$authority = 'tr';
		} elseif ($p >= 338 && $p <= 485){
			$authority = 'hdn';
		}
	} elseif ($volume == 'RIC III'){
		if ($p >= 25 && $p <= 194){
			$authority = 'ant';
		} elseif ($p >= 214 && $p <= 355){
			$authority = 'm_aur';
		} elseif ($p >= 366 && $p <= 443){
			$authority = 'com';
		}
	} elseif ($volume == 'RIC IV'){
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
	} elseif ($volume == 'RIC IV(B)'){
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
	} elseif ($volume == 'RIC IV(C)'){
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
	} elseif ($volume == 'RIC V' || $volume == 'RIC V(1)'){
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
	} elseif ($volume == 'RIC V(2)'){
		if ($p >= 20 && $p <= 121){
			$authority = 'pro';
		}
	} elseif ($volume == 'RIC VI'){
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
	} elseif ($volume == 'RIC VII'){
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
	} elseif ($volume == 'RIC VIII'){
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
	} elseif ($volume == 'RIC X'){
		if ($p >= 239 && $p <= 252){
			$authority = 'arc_e';
		}
		if ($p >= 253 && $p <= 277){
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

?>