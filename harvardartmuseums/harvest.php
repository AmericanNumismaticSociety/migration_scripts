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
	
	$service = 'http://api.harvardartmuseums.org/object?size=100&classification=Coins&q=department:"Department%20of%20Ancient%20and%20Byzantine%20Art%20%26%20Numismatics"&apikey=' . $apiKey . '&page=' . $page;
	
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
				preg_match('/(RIC[^,]+),\s(.*)/', $reference, $matches);
				
				if (isset($matches[1])){
					switch ($matches[1]){
						case 'RIC I 2':
							$volume = 'ric.1(2)';
							break;
						case 'RIC II.1 2':
							$volume = 'ric.2_1(2)';
							break;
						case 'RIC II':
							$volume = 'ric.2';
							break;
						case 'RIC III':
							$volume = 'ric.3';
							break;
						case 'RIC IV':
						case 'RIC IV(B)':
						case 'RIC IV(C)':
							$volume = 'ric.4';
							break;
						case 'RIC V':
						case 'RIC V(1)':
						case 'RIC V(2)':
							$volume = 'ric.5';
							break;
						default:
							$volume = null;
					}

					//only process currently published volumes
					if ($volume != null && isset($matches[2])){
						$row = array();
						
						//generate record metadata array
						$row = construct_metadata($record);
						$row['reference'] = $reference;
						
						preg_match('/([^\s|^,]+).*?p\.\s?([0-9]+)/', $matches[2], $arr);
							
						if (isset($arr[1]) and isset($arr[2])){
							$id = str_replace(' ', '', $arr[1]);
							$pageNumber = trim($arr[2]);	

							//ignore uncertain coins
							if (substr($id, -1) != '?'){
								$authority = get_authority($matches[1], $pageNumber);
									
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
		
	} elseif ($volume == 'RIC II.1 2') {
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
	}
	
	return $authority;
}

?>