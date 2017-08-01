<?php 

$data = generate_json('SardisCoins.csv');
$records = array();
//$volumes = array();

//iterate through CSV
foreach ($data as $record){
	$row = array();	
	//generate record metadata array
	$row = construct_metadata($record);
	
	$references = array();
	$explodeSemicolon = explode(';', $record['References']);
	foreach ($explodeSemicolon as $piece){
		$piece = trim($piece);
		$explodeComma = explode(',', $piece);
		foreach ($explodeComma as $reference){
			//trim and normalize reference
			$references[] = str_replace('no. ', 'no.', str_replace('p. ', 'p.', trim($reference)));
		}
	}
	
	//add normalized original reference into array
	$row['originalReference'] = implode('; ', $references);
	
	foreach ($references as $reference){
		if (preg_match('/RRC/', $reference)){
			$num = explode(' ', $reference);
			
			$row['parsedReference'] = $reference;
			//ignore uncertain coins
			if (substr($num[2], -1) != '?'){
				$cointype = 'http://numismatics.org/crro/id/rrc-' . str_replace('/', '.', $num[2]);
				$file_headers = @get_headers($cointype);
				if ($file_headers[0] == 'HTTP/1.1 200 OK'){
					echo "{$row['objectnumber']}: {$cointype}\n";
					$row['cointype'] = $cointype;
				}
			}
			//end RRC processing
		} elseif (preg_match('/^Price\s1991/', $reference)){
			preg_match('/no\.\s?([A-Z0-9]+)$/', $reference, $matches);
			$row['parsedReference'] = $reference;
			if (isset($matches[1])){
				$num = $matches[1];
				//ignore uncertain coins
				if (substr($num, -1) != '?'){
					$cointype = 'http://numismatics.org/pella/id/price.' . $num;
					$file_headers = @get_headers($cointype);
					if ($file_headers[0] == 'HTTP/1.1 200 OK'){
						echo "{$row['objectnumber']}: {$cointype}\n";
						$row['cointype'] = $cointype;
					}
				}
			}
			//end PELLA processing
		} elseif (preg_match('/^(RIC\s[^\s]+)\sp\.(\d+)\sno\.([^\s]+)$/', $reference, $matches)){
			$row['parsedReference'] = $reference;
			$originalReference = $reference;
			
			if (isset($matches[0])){				
				$volNum = trim($matches[1]);
				$pageNumber = $matches[2];
				$id = trim($matches[3]);
				
				/*if (!in_array($volNum, $volumes)){
					$volumes[] = $volNum;
				}*/
				
				//1. get volume number and then strip from reference. 2. trim leading or trailing commas. 3. trim spaces
				switch($volNum){
					case 'RIC II':
						$parsedVolume = $volNum;
						$volume = 'ric.2';
						break;
					case 'RIC III':
						$parsedVolume = $volNum;
						$volume = 'ric.3';
						break;
					case 'RIC IV.I':
					case 'RIC IV.2':
					case 'RIC IV.3':
						$parsedVolume = $volNum;
						$volume = 'ric.4';
						break;
					case 'RIC V.1':
					case 'RIC V.I':
					case 'RIC V.2':
						$parsedVolume = $volNum;
						$volume = 'ric.5';
						break;
					case 'RIC VI':
						$parsedVolume = $volNum;
						$volume = 'ric.6';
						break;
					case 'RIC VII':
						$parsedVolume = $volNum;
						$volume = 'ric.7';
						break;
					case 'RIC VIII':
						$parsedVolume = $volNum;
						$volume = 'ric.8';
						break;
					case 'RIC IX':
						$parsedVolume = $volNum;
						$volume = 'ric.9';
						break;
					case 'RIC X':
						$parsedVolume = $volNum;
						$volume = 'ric.10';
						break;
					default:
						$volume = null;
				}
				
				//echo "{$volume}\n";
				
				echo "{$originalReference}\n";
				
				if (isset($parsedVolume) && isset($id) && isset($pageNumber)){
					//ignore uncertain coins
					if (substr($id, -1) != '?'){
						$authority = get_authority($parsedVolume, $pageNumber);
						
						if ($authority != null){
							//if $authority is properly resolved, try OCRE lookups
							$prefix = "{$volume}.{$authority}.";
							if (preg_match('/[a-zA-Z]/', $id)){
								//if it is volume 9, attempt to parse subtype numbers
								if ($volume == 'ric.9'){
									preg_match('/(\d+[a-z]?)([0-9]?)/', $id, $pieces);
									//first try to match on the subtype URI
									if (isset($pieces[1]) and isset($pieces[2])){
										$cointype = "http://numismatics.org/ocre/id/{$prefix}" . strtoupper($pieces[1]) . '.' . $pieces[2];
										$file_headers = @get_headers($cointype);
										if ($file_headers[0] == 'HTTP/1.1 200 OK'){
											echo "{$row['objectnumber']}: {$cointype}\n";
											$row['cointype'] = $cointype;
										} else {
											//if it can't find the subtype, just try to match on parent type
											$cointype = "http://numismatics.org/ocre/id/{$prefix}" . strtoupper($pieces[1]);
											$file_headers = @get_headers($cointype);
											if ($file_headers[0] == 'HTTP/1.1 200 OK'){
												echo "{$row['objectnumber']}: {$cointype}\n";
												$row['cointype'] = $cointype;
											}
										}
									}
								} else {
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
				
			}
			//end RIC processing
		}
	}
	$records[] = $row;
}

//generate exports
generate_csv($records);
//generate_rdf($records);


/******************** FUNCTIONS ***********************/
//generate csv
function generate_csv($records){
	$csv = '"objectnumber","parsedReference","originalReference","type"' . "\n";
	
	foreach ($records as $record){
		$csv .= '"' . $record['objectnumber'] . '","' . (isset($record['parsedReference']) ? $record['parsedReference'] : '') . '","' . $record['originalReference'] . '","' . (isset($record['cointype']) ? $record['cointype'] : '') . '"' . "\n";
	}
	
	file_put_contents('sardis-concordances.csv', $csv);
}

//this function will create an array of values that will form both the concordance list and RDF dump
function construct_metadata ($record){
	$row = array();
	
	$row['objectnumber'] = $record['Coin_Number'];
	//$row['title'] = $record['Coin_Number'];
	//$row['uri'] = $record->url;
	
	//image
	/*if (property_exists($record, 'primaryimageurl')){
		$row['comThumb'] = str_replace('dlvr', 'dynmc', $record->primaryimageurl) . '?width=240';
		$row['comRef'] = $record->primaryimageurl;
	}
	
	//look for IIIF service
	if (property_exists($record, 'images')){
		foreach ($record->images as $image){
			if (property_exists($image, 'iiifbaseuri')){
				//echo "{$image->iiifbaseuri}\n";
				$row['service'] = $image->iiifbaseuri;
			}
		}
	}
	*/
	
	//measurments
	if (is_numeric($record['Diameter'])){
		$row['diameter'] = $record['Diameter'];
	}
	
	if (is_numeric($record['Weight'])){
		$row['weight'] = $record['Weight'];
	}
	
	return $row;
}

//use the volume and page numbers to determine the authority code
function get_authority($volume, $p){
	$authority = null;
	
	if ($volume == 'RIC I'){
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
	} elseif ($volume == 'RIC IV.I' || $volume == 'RIC IV.1'){
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
	} elseif ($volume == 'RIC IV.2'){
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
	} elseif ($volume == 'RIC IV.3'){
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
	} elseif ($volume == 'RIC V.I' || $volume == 'RIC V.1'){
		if ($p >= 37 && $p <= 60){
			$authority = 'val_i';
		} else if ($p >= 61 && $p <= 62){
			$authority = 'val_i-gall';
		} else if ($p == 63){
			$authority = 'val_i-gall-val_ii-sala';
		} else if ($p >= 64 && $p <= 65){
			$authority = 'marin';
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
	} elseif ($volume == 'RIC V.2'){
		if ($p >= 20 && $p <= 121){
			$authority = 'pro';
		} elseif ($p >= 135 && $p <= 203){
			$authority = 'car';
		} elseif ($p >= 221 && $p <= 309){
			$authority = 'dio';
		} elseif ($p >= 336 && $p <= 368){
			$authority = 'post';
		} elseif ($p >= 372 && $p <= 373){
			$authority = 'lae';
		} elseif ($p >= 377 && $p <= 378){
			$authority = 'mar';
		} elseif ($p >= 387 && $p <= 398){
			$authority = 'vict';
		} elseif ($p >= 402 && $p <= 425){
			$authority = 'tet_i';
		} elseif ($p >= 463 && $p <= 549){
			$authority = 'cara';
		} elseif ($p >= 550 && $p <= 556){
			$authority = 'cara-dio-max_her';
		} elseif ($p >= 558 && $p <= 570){
			$authority = 'all';
		} elseif ($p >= 580 && $p <= 581){
			$authority = 'mac_ii';
		} elseif ($p >= 582 && $p <= 583){
			$authority = 'quit';
		} elseif ($p == 584){
			$authority = 'zen';
		} elseif ($p == 585){
			$authority = 'vab';
		} elseif ($p >= 586 && $p <= 587){
			$authority = 'reg';
		} elseif ($p == 588){
			$authority = 'dry';
		} elseif ($p == 589 ){
			$authority = 'aurl';
		} elseif ($p == 590 ){
			$authority = 'dom_g';
		} elseif ($p == 591){
			$authority = 'sat';
		} elseif ($p == 592){
			$authority = 'bon';
		} elseif ($p >= 593 && $p <= 594){
			$authority = 'jul_i';
		} elseif ($p == 595){
			$authority = 'ama';
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
	} elseif ($volume == 'RIC IX'){
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
	} elseif ($volume == 'RIC X'){
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

//functions
function generate_json($doc){
	$keys = array();
	$array = array();
	
	$csv = csvToArray($doc, ',');
	
	// Set number of elements (minus 1 because we shift off the first row)
	$count = count($csv) - 1;
	
	//Use first row for names
	$labels = array_shift($csv);
	
	foreach ($labels as $label) {
		$keys[] = $label;
	}
	
	// Bring it all together
	for ($j = 0; $j < $count; $j++) {
		$d = array_combine($keys, $csv[$j]);
		$array[$j] = $d;
	}
	return $array;
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
?>