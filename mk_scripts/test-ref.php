<?php 

$ref = 'RIC II-1² Vespasianus 1016';
$types = array();

$uri = parseReference($ref, $collection='');

echo "{$uri}\n";

function parseReference($ref, $collection){
	GLOBAL $types;
	
	if (strpos($ref, 'RIC') !== FALSE){
		preg_match('/^RIC\s([^\s]+)\s([^\d]+)\s(\d.*)$/', $ref, $pieces);
		var_dump($pieces);
		if (isset($pieces[1]) && isset($pieces[2])){
			$vol = $pieces[1];
			$authority = trim(str_replace(',', '', $pieces[2]));
			$nums = $pieces[3];
		}
		
		//$pieces = explode(' ', $ref);
		//$vol = $pieces[1];
		
		//assemble id
		$nomismaId = array();
		$nomismaId[] = 'ric';
		
		//volume
		switch ($vol) {
			case 'I²':
				$nomismaId[] = '1(2)';
				break;
			case 'II-1²':
				$nomismaId[] = '2_1(2)';
				break;
			case 'II':
				$nomismaId[] = '2';
				break;
			case 'III':
				$nomismaId[] = '3';
				break;
			case 'IV-1':
			case 'IV-2':
			case 'IV-3':
				$nomismaId[] = '4';
				break;
			case 'V-1':
				$nomismaId[] = '5';
				break;
			case 'V-2':
				$nomismaId[] = '5';
				break;
			case 'VI':
				$nomismaId[] = '6';
				break;
			case 'VII':
				$nomismaId[] = '7';
				break;
			case 'VIII':
				$nomismaId[] = '8';
				break;
			case 'IX':
				$nomismaId[] = '9';
				break;
			case 'X':
				$nomismaId[] = '10';
				break;
			default:
				$nomismaId[] = null;
		}
		
		//normalize authority
		//$names = array_slice($pieces, 2, count($pieces) - 3);
		//$authority = implode(' ', $names);
		switch ($authority) {
			case 'Augustus':
				$nomismaId[] = 'aug';
				break;
			case 'Tiberius':
				$nomismaId[] = 'tib';
				break;
			case 'Caligula':
				$nomismaId[] = 'gai';
				break;
			case 'Claudius':
				$nomismaId[] = 'cl';
				break;
			case 'Nero':
				$nomismaId[] = 'ner';
				break;
			case 'Galba':
				$nomismaId[] = 'gal';
				break;
			case 'Otho':
				$nomismaId[] = 'ot';
				break;
			case 'Vitellius':
				$nomismaId[] = 'vit';
				break;
			case 'Macer':
				$nomismaId[] = 'clm';
				break;
			case 'Civil Wars':
				$nomismaId[] = 'cw';
				break;
			case 'Vespasianus':
				$nomismaId[] = 'ves';
				break;
			case 'Titus':
				$nomismaId[] = 'tit';
				break;
			case 'Domitianus':
				$nomismaId[] = 'dom';
				break;
			case 'Nerva':
				$nomismaId[] = 'ner';
				break;
			case 'Traianus':
				$nomismaId[] = 'tr';
				break;
			case 'Hadrianus':
				$nomismaId[] = 'hdn';
				break;
			case 'Pius':
				$nomismaId[] = 'ant';
				break;
			case 'Aurelius':
				$nomismaId[] = 'm_aur';
				break;
			case 'Commodus':
				$nomismaId[] = 'com';
				break;
			case 'Pertinax':
				$nomismaId[] = 'pert';
				break;
			case 'Didius Iulianus':
				$nomismaId[] = 'dj';
				break;
			case 'Niger':
				$nomismaId[] = 'pn';
				break;
			case 'Clodius Albinus':
				$nomismaId[] = 'ca';
				break;
			case 'Septimius Severus':
				$nomismaId[] = 'ss';
				break;
			case 'Caracalla':
				$nomismaId[] = 'crl';
				break;
			case 'Geta':
				$nomismaId[] = 'ge';
				break;
			case 'Macrinus':
				$nomismaId[] = 'mcs';
				break;
			case 'Elagabalus':
				$nomismaId[] = 'el';
				break;
			case 'Severus Alexander':
				$nomismaId[] = 'sa';
				break;
			case 'Maximinus Thrax':
				$nomismaId[] = 'max_i';
				break;
			case 'Maximus':
				$nomismaId[] = 'mxs';
				break;
			case 'Diva Paulina':
				$nomismaId[] = 'pa';
				break;
			case 'Gordianus I.':
				$nomismaId[] = 'gor_i';
				break;
			case 'Gordianus II.':
				$nomismaId[] = 'gor_ii';
				break;
			case 'Pupienus':
				$nomismaId[] = 'pup';
				break;
			case 'Balbinus':
				$nomismaId[] = 'balb';
				break;
			case 'Gordianus III. Caesar':
				$nomismaId[] = 'gor_iii_caes';
				break;
			case 'Gordianus III.':
				$nomismaId[] = 'gor_iii';
				break;
			case 'Philippus I.':
				$nomismaId[] = 'ph_i';
				break;
			case 'Pacatianus':
				$nomismaId[] = 'pac';
				break;
			case 'Iotapianus':
				$nomismaId[] = 'jot';
				break;
			case 'Decius':
				$nomismaId[] = 'tr_d';
				break;
			case 'Trebonianus':
				$nomismaId[] = 'tr_g';
				break;
			case 'Aemilianus':
				$nomismaId[] = 'aem';
				break;
			case 'Uranus':
				$nomismaId[] = 'uran_ant';
				break;
			case 'Valerianus':
				$nomismaId[] = 'val_i';
				break;
			case 'Mariniana':
				$nomismaId[] = 'marin';
				break;
			case 'Gallienus Mitherrscher':
				$nomismaId[] = 'gall(1)';
				break;
			case 'Salonina Mitherrscher':
				$nomismaId[] = 'sala(1)';
				break;
			case 'Saloninus':
				$nomismaId[] = 'sals';
				break;
			case 'Valerianus II.':
				$nomismaId[] = 'val_ii';
				break;
			case 'Gallienus':
				$nomismaId[] = 'gall(2)';
				break;
			case 'Salonina':
				$nomismaId[] = 'sala(2)';
				break;
			case 'Claudius Gothicus':
				$nomismaId[] = 'cg';
				break;
			case 'Quintillus':
				$nomismaId[] = 'qu';
				break;
			case 'Aurelianus':
				$nomismaId[] = 'aur';
				break;
			case 'Severina':
				$nomismaId[] = 'seva';
				break;
			case 'Tacitus':
				$nomismaId[] = 'tac';
				break;
			case 'Florianus':
				$nomismaId[] = 'fl';
				break;
			case 'Probus':
				$nomismaId[] = 'pro';
				break;
			case 'Carus/Familie':
				$nomismaId[] = 'car';
				break;
			case 'Tetrarchie (vorreform)':
				$nomismaId[] = 'dio';
				break;
			case 'Postumus':
				$nomismaId[] = 'post';
				break;
			case 'Laelianus':
				$nomismaId[] = 'lae';
				break;
			case 'Marius':
				$nomismaId[] = 'mar';
				break;
			case 'Victorinus':
				$nomismaId[] = 'vict';
				break;
			case 'Tetrici':
				$nomismaId[] = 'tet_i';
				break;
			case 'Carausius':
				$nomismaId[] = 'cara';
				break;
			case 'Carausius, Diocletianus, Maximianus Herculius':
				$nomismaId[] = 'cara-dio-max_her';
				break;
			case 'Allectus':
				$nomismaId[] = 'all';
				break;
			case 'Macrianus':
				$nomismaId[] = 'mac_ii';
				break;
			case 'Quietus':
				$nomismaId[] = 'quit';
				break;
			case 'Regalianus':
				$nomismaId[] = 'reg';
				break;
			case 'Dryantilla':
				$nomismaId[] = 'dry';
				break;
			case 'Iulianus von Pannonien':
				$nomismaId[] = 'jul_i';
				break;
			case 'Alexandria':
				$nomismaId[]='alex';
				break;
			case 'Amiens':
				$nomismaId[]='amb';
				break;
			case 'Antioch':
			case 'Antiochia':
				$nomismaId[]='anch';
				break;
			case 'Aquileia':
				$nomismaId[]='aq';
				break;
			case 'Arles':
				$nomismaId[]='ar';
				break;
			case 'Carthago':
				$nomismaId[]='carth';
				break;
			case 'Constantinople':
			case 'Constantinopolis':
				$nomismaId[]='cnp';
				break;
			case 'Cyzicus':
				$nomismaId[]='cyz';
				break;
			case 'Heraclea':
				$nomismaId[]='her';
				break;
			case 'London':
			case 'Londinium':
				$nomismaId[]='lon';
				break;
			case 'Lugdunum':
			case 'Lyons':
				$nomismaId[]='lug';
				break;
			case 'Nicomedia':
				$nomismaId[]='nic';
				break;
			case 'Ostia':
				$nomismaId[]='ost';
				break;
			case 'Roma':
			case 'Rome':
				$nomismaId[]='rom';
				break;
			case 'Serdica':
				$nomismaId[]='serd';
				break;
			case 'Sirmium':
				$nomismaId[]='sir';
				break;
			case 'Siscia':
				$nomismaId[]='sis';
				break;
			case 'Thessalonica':
				$nomismaId[]='thes';
				break;
			case 'Ticinum':
				$nomismaId[]='tic';
				break;
			case 'Treveri':
			case 'Trier':
				$nomismaId[]='tri';
				break;
			default:
				$nomismaId[] = null;
		}
		
		//add number:
		//$num = ltrim($pieces[count($pieces) - 1], '0');
		
		//find numbers through regular expressions
		preg_match_all('/(\d{1,4}[A-Za-z]{1,2}|\d{1,4})/', $nums, $numArray);
		echo "Parsing {$ref}\n";
		
		var_dump($numArray);
		
		if (isset($numArray[1])){
			$matches = $numArray[1];
			//only parse if there's one parseable RIC number
			if (count($matches) == 1){
				$num = ltrim($matches[0], '0');
				if ($vol == 'X'){
					//echo "RIC 10:\n";
					// handle RIC 10 in a lookup table
					if (array_key_exists($num, $pairs)){
						//replace a null value for $nomismaId[2] with the new authority pair
						$nomismaId[2] = $pairs[$num];
						$uri = 'http://numismatics.org/ocre/id/' . implode('.', $nomismaId) .  '.' . $num;
						
						if (in_array($uri, $types)){
						
							return $uri;
						} else {
							$file_headers = @get_headers($uri . '.xml');
							if ($file_headers[0] == 'HTTP/1.1 200 OK'){
								$types[] = $uri;
								
								return $uri;
							}
						}
					}
				} elseif ($nomismaId[1] != null && $nomismaId[2] != null){
					$uri = 'http://numismatics.org/ocre/id/' . implode('.', $nomismaId) . '.' . strtoupper($num);
					//see if the URI is already in the validated array
					if (in_array($uri, $types)){
						return $uri;
					} else {
						$file_headers = @get_headers($uri . '.xml');
						if ($file_headers[0] == 'HTTP/1.1 200 OK'){
							$types[] = $uri;
							
							return $uri;
						} else {
							$uri = 'http://numismatics.org/ocre/id/' . implode('.', $nomismaId) .  '.' . $num;
							
							//see if the URI is already in the validated array
							if (in_array($uri, $types)){
								return $uri;
							} else {
								$file_headers = @get_headers($uri . '.xml');
								if ($file_headers[0] == 'HTTP/1.1 200 OK'){
									$types[] = $uri;
									
									return $uri;
								}
							}
						}
					}
				}
			}
		}
	} else if (strpos($ref, 'RRC') !== FALSE){
		//RRC
		$pieces = explode(',', $ref);
		
		if ($collection == 'vienna'){
			$id = 'rrc-' . str_replace('/', '.', ltrim(trim($pieces[1]), '0'));
			$uri = 'http://numismatics.org/crro/id/' . $id;
		} else {
			$frag = array();
			$frag[] = ltrim(trim($pieces[1]), '0');
			if (isset($pieces[2])) {
				$frag[] = ltrim(trim($pieces[2]), '0');
			} else {
				$frag[] = '1';
			}
			
			$id = 'rrc-' . implode('.', $frag);
			$uri = 'http://numismatics.org/crro/id/' . $id;
		}
		
		//see if the URI is already in the validated array
		if (in_array($uri, $types)){
			return $uri;
		} else {
			$file_headers = @get_headers($uri . '.xml');
			if ($file_headers[0] == 'HTTP/1.1 200 OK'){
				$types[] = $uri;
				return $uri;
			}
		}
	}
}

?>