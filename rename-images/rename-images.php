<?php 

/* Author: Ethan Gruber
 * Date: November 2015
 * Function: This script will iterate through CSV files to rename
 * images from Emma's photography folders to meet the accession number
 * based filenaming convention. 
* Instructions:
* 1. Save Excel spreadsheets as CSV, and copy to /Volumes/data03/Workgroup/OCRE/Roman Trays CSV/
* 2. Rename CSV file to precisely match the tray number. The photography folder name should be identical to this.
* 3. Make sure the column headings are the same across all spreadsheets.
* 4. Run the script from the command line. It will iterate through all CSV files to rename the files. The process counts the number of images to start and end, for each CSV.
 */

date_default_timezone_set("America/New_York");

$csvDir = '/Volumes/data03/Workgroup/OCRE/Roman Trays CSV/';
$imageInput = '/Volumes/data03/Workgroup/PhotographyBackup/Emma Backup/Roman/';
$imageOutput = '/Volumes/data03/Workgroup/PhotographyBackup/rename-complete/';
$logFile = 'rename-images.log';

//begin log file
error_log(date(DATE_W3C) . ": Beginning process.\n", 3, $logFile);

$files = scandir($csvDir);
foreach ($files as $file){
	//ignore hidden CSV files
	if (strpos($file, '.csv') > 0 && substr($file, 0, 1) != '.'){
		$data = generate_json($csvDir . $file);
		
		$accnumCount = count($data);
		$uniqueAccnums = array();
		foreach ($data as $row){
			$accnum = $row['accnum'];
			if (!in_array($accnum, $uniqueAccnums)){
				$uniqueAccnums[] = $accnum;
			}
		}
		
		
		$folder = substr($data[0]['Current Location'], 0, 7);
		
		//make the folder in the $imageOutput
		mkdir($imageOutput . $folder);
		
		//add image count into the log
		$imageScan = scandir($imageInput . $folder);
		$images = array();
		foreach ($imageScan as $f){
			if (strpos($f, '.tif') > 0){
				$images[] = $f;
			}
		}
		$imageCount = count($images);
		
		//add log information
		
		echo date(DATE_W3C) .  ": Processing {$folder}\n";
		error_log(date(DATE_W3C) . ": Processing {$folder}\n", 3, $logFile);
		error_log("Count of rows in the spreadsheet: {$accnumCount}\n", 3, $logFile);
		error_log("Count of .tif images in the folder: {$imageCount}\n", 3, $logFile);
		
		//produce error if the number of rows in the spreadsheet is not equal to the number of unique accession numbers
		if ($accnumCount != count($uniqueAccnums)){
			echo "Error: number of spreadsheet rows not equal to number of unique accession numbers in {$folder}.\n";
			error_log("Error: number of spreadsheet rows not equal to number of unique accession numbers in {$folder}.\n", 3, $logFile);
		} else {
			//proceed if there are two images per row in the spreadsheet
			if (($accnumCount * 2) == $imageCount){
				$rowNum = 1;
				foreach ($data as $row){
					$accnum = $row['accnum'];
					$imageNum = 1;
					foreach ($images as $image){
						if ($imageNum == ($rowNum * 2) - 1 || $imageNum == $rowNum * 2){
							$oldFilename = $image;
							$pieces = explode('.', $image);
							$pieces[0] = $accnum;
							$newFilename = implode('.', $pieces);
								
							echo "Copying {$folder}/{$oldFilename} to {$folder}/{$newFilename}.\n";
							//echo 'copy ' . $imageInput . $folder . '/' . $oldFilename . ' to ' . $imageOutput . $folder . '/' . $newFilename . "\n";
							copy($imageInput . $folder . '/' . $oldFilename, $imageOutput . $folder . '/' . $newFilename);
						}
						$imageNum++;
					}
					$rowNum++;
				}
			} else {
				echo "Error: row/image numbering mismatch in {$folder}.\n";
				error_log("Error: row/image numbering mismatch in {$folder}.\n", 3, $logFile);
			}
		}		
		
		/* compare $imageCount with $postCount:
		 * the number of images in Emma's folder compared with the number of images in the rename-complete.
		 * if the numbers are different, then overwriting has occurred due to image naming problem or duplicate accession number
		 */
		$postScan = scandir($imageOutput . $folder);
		$postImages = array();
		foreach ($postScan as $f){
			if (strpos($f, '.tif') > 0){
				$postImages[] = $f;
			}
		}
		$postCount = count($postImages);
		
		if ($imageCount != $postCount){
			echo "Error: source and target image folder count mismatch: {$folder}.\n";
			error_log(date(DATE_W3C) . "Error: source and target image folder count mismatch: {$folder}.\n", 3, $logFile);
		}
	}	
}
echo date(DATE_W3C) . ": Process complete.\n";
error_log(date(DATE_W3C) . ": Process complete.\n", 3, $logFile);

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

function number_pad($number,$n) {	
	return str_pad((int) $number,$n,"0",STR_PAD_LEFT);
}

?>
