<?php 

$server = 'numismatics.org';
$dir = '/home/komet/ans_migration/crro/reprocessed/';
$project = 'crro';
$files = scandir($dir);

foreach ($files as $file){
	if (strpos($file, '.xml') !== FALSE){
		$filename = $dir . $file;
		if (($readFile = fopen($filename, 'r')) === FALSE){
			echo "Unable to read {$file}\n";
		} else {
			//PUT xml to eXist
			$putToExist=curl_init();
		
			//set curl opts
			curl_setopt($putToExist,CURLOPT_URL,'http://' . $server . ':8080/exist/rest/db/' . $project . '/objects/' . $file);
			curl_setopt($putToExist,CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=utf-8"));
			curl_setopt($putToExist,CURLOPT_CONNECTTIMEOUT,2);
			curl_setopt($putToExist,CURLOPT_RETURNTRANSFER,1);
			curl_setopt($putToExist,CURLOPT_PUT,1);
			curl_setopt($putToExist,CURLOPT_INFILESIZE,filesize($filename));
			curl_setopt($putToExist,CURLOPT_INFILE,$readFile);
			curl_setopt($putToExist,CURLOPT_USERPWD,"admin:");
			$response = curl_exec($putToExist);
		
			$http_code = curl_getinfo($putToExist,CURLINFO_HTTP_CODE);
		
			//error and success logging
			if (curl_error($putToExist) === FALSE){
				echo "{$file} failed to write to eXist.\n";
			}
			else {
				if ($http_code == '201'){
					echo "{$file} written.\n";
				}
			}
			//close eXist curl
			curl_close($putToExist);
		
			//close files and delete from /tmp
			fclose($readFile);
		}
	}
}


?>