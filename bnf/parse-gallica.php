<?php 

$ids = array();
$url = 'http://oai.bnf.fr/oai2/OAIHandler?verb=ListRecords&metadataPrefix=oai_dc&set=gallica';

read_oai($url, $ids);


/**** FUNCTIONS ****/
function read_oai ($url, $ids){
	$doc = new DOMDocument();
	if ($doc->load($url) === FALSE){
		return "FAIL";
	} else {
		echo "Processing {$url}\n";
		$service = $doc->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'request')->item(0)->nodeValue;
		$xpath = new DOMXpath($doc);
		$xpath->registerNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
		$xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
		$records = $xpath->query("descendant::oai:record[not(oai:header/@status = 'deleted')]");
		
		foreach ($records as $record){
			$id = $record->getElementsByTagNameNS('http://www.openarchives.org/OAI/2.0/', 'identifier')->item(0)->nodeValue;
			$descriptions = $record->getElementsByTagNameNS('http://purl.org/dc/elements/1.1/', 'description');
			
			foreach ($descriptions as $desc){
				if (strpos($desc->nodeValue, 'MonnGre') !== FALSE){
					echo "Found {$id}\n";
					$ids[] = $id;
				}
			}
		}
		
		//look for resumptionToken
		$token = $xpath->query("descendant::oai:resumptionToken");
		
		if ($token->length > 0){
			echo "Checked " . $token->item(0)->getAttribute('cursor') . " of " . $token->item(0)->getAttribute('completeListSize') . ".\n";
			$url = $service . '?verb=ListRecords&resumptionToken=' . $token->item(0)->nodeValue;
			read_oai($url, $ids);
		}
	}	
}


?>