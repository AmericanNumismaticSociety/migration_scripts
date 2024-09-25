<?php
/************************
AUTHOR: Ethan Gruber
MODIFIED: January, 2018
DESCRIPTION: Receive and interpret escaped CSV sent from Filemaker Pro database
to public server, transform to Numishare-compliant NUDS XML (performing cleanup of data),
post to eXist XML database via cURL, and get Solr add document from Orbeon and post to Solr.
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

//$csv_id = $_GET['id'];
$csv_id = 'Collection-DE';

//create an array with pre-defined labels and values passed from the Filemaker POST
/*$labels = array("accnum","department","objtype","material","manufacture",
    "shape","weight","measurements","axis","denomination","era","dob",
    "startdate","enddate","refs","published","info","prevcoll","region","subregion",
    "locality","series","dynasty","mint","mintabbr","person","issuer",
    "magistrate","maker","artist","sernum","subjevent","subjperson",
    "subjissuer","subjplace","decoration","degree","findspot",
    "obverselegend","obversetype","reverselegend","reversetype","color",
    "edge","undertype","counterstamp","conservation","symbol",
    "obversesymbol","reversesymbol","signature","watermark",
    "imageavailable","acknowledgment","category","imagesponsor",
    "OrigIntenUse","Authenticity","PostManAlt","diameter","height","width","depth","privateinfo");*/


//open CSV file from FileMaker and clean it, writing it back to /tmp
$file = file_get_contents($csv_id . ".csv");
$cleanFile = $csv_id . '-cleaned.csv';
//escape conflicting XML characters

if (($handle = fopen($csv_id . ".csv", "r")) !== FALSE) {
    //$cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', preg_replace("[\x1D]", "|", str_replace('>', '&gt;', str_replace('<', '&lt;', str_replace('&', 'and', preg_replace("[\x0D]", "\n", $file))))));
    
    $cleaned = preg_replace("/[\x0D]/", "\n", $file);
    $cleaned = preg_replace("/[\x1D]/", "|", $cleaned);   
    //$cleaned = preg_replace('/\|+/', "x", $cleaned);
    //$cleaned = preg_replace('/\|\"/', '"', $cleaned);
} else {
    echo "Unable to open file.\n";
}

fclose($handle);

//$lines = explode("\n", $cleaned);


//file_put_contents($cleanFile, implode(',', $labels) . "\n");
file_put_contents($cleanFile, $cleaned);


/***** JSON PARSING FUNCTIONS *****/
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
