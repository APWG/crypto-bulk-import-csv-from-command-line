<?php
$api_url = "https://api.ecrimex.net";
$endpoint = "/groups/c3a060d0bc2869346c91938aa08ede9746a7a1d4";
$api_token_key="";

if (!isset($argv[1]) or $argv[1] == '-h' or $argv[1] == '-help') {
	if (!isset($argv[1])) {
		$message = "\nERROR: no csv filename specified as input\n";
	} else {
		$message = "\n";
	}
	die("$message
crypto_csv_import.php - Bulk import CSV data into the eCX Virtual Currency Data Exchange Workgroup.

-A file of CSV data is read, saved into an array, each element in the array converted to JSON to match the eCX API submission format, and POSTed to eCX catching the result code, and rejected submissions are saved into an exception file. 

Usage:  php crypto_csv_import.php /path/to/filename.csv

Params:
- CSV filename, the /path/to/the/filename of the CSV file you want to bulk import
- `-h` or `-help` for this message

Requirements:
- Written to be run on an Ubuntu system, but not Ubuntu specific yet untested on Windows or other OS 
- php_curl module needs to be installed so that the script can POST data over cURL to the eCX API
- The first line in the CSV file will be a header, noting the column names.  Be sure that you match up the column names to the `name` value in the Threat Model for the workgroup.
- The schema of your CSV data most likley not perfectly match the Workgroups Threat Model.  So, in your CSV file be sure to include any of the Threat Models required fields with default values
- The 6 required fields for the Virtual Currency Data Exchange Workgroup are: source, procedure, timestamp*, currency, address, and tag - so at a minimum you should have these matching 6 columns in your CSV file.  Other fields in your CSV may match up as well.
- The procedure and 
- Number of CSV header column names must match the number of delimited fields
- Use the Postman API client to test a few rows of your CSV data that you've converted to JSON (http://www.convertcsv.com/csv-to-json.htm) to make sure that you can POST correctly prior to running this script

* Date Option:
- Use of epoch date values are *highly* encouraged, they take into consideration date and time and timezone and come back with a known valid combination of these that will always be correct and always convert correctly into any ISO format.  Converting ISO dates without timestamp or timezone data results in strange epoch values.  
- Epoch date fields in the Threat Model must be in the CSV file as epoch date values in a `timestamp` column.  Use =(A2-25569)*86400 to convert ISO dates to epoch, where A2 is your ISO date cell ID.  Set your non-epoch date cell format to Date, and your epoch column to Number, zero decimal places.  Then hide the ISO date column when saving the Excel file as CSV so that it is not included  
- However.... if you omit the timestamp column holding epochs, and instead provide a `date` column - indicating ISO dates - the script will attempt to convert the ISO dates to epoch for you.  
- Note that with all the various ways dates might be represented in a spreadsheet that the value may not be supported by the epoch conversion logic, so you may see POST failures in the exceptions.csv output file

Settings:
- Edit line 4 of this script with your eCX API Token Key prior to running this script
- The $endpoint value is currently set to the Virtual Currency Data Exchange Workgroup

Output: 
- Progress indicator to the console
- The file `exceptions.csv` (if already exists) is initialized with a listing of the data that failed to validate or POST, along with any API result code and API error message/s for each row that failed\n\n");
} else {
	$file = $argv[1];
}

set_error_handler(
	function ($severity, $message, $file, $line) {
		throw new ErrorException($message, $severity, $severity, $file, $line);
	}
);

// confirm cURL is installed
if  (!in_array  ('curl', get_loaded_extensions())) {
	die("\n
cURL needs to be installed, follow these instructions to install cURL on Ubuntu for PHP 5.x:
Open SSH\n

First Install CURL by typing `sudo apt-get install curl`\n
Then Restart Apache by typing `sudo service apache2 restart`\n
Then Install PHP5 CURL by typing `sudo apt-get install php5-curl`\n
will prompt to install... type y or yes!\n
Then Restart Apache by typing `sudo service apache2 restart`\n");
}

try {
	file_get_contents($file);
}
catch (Exception $e) {
	die("\nFATAL: Unable to open file \"" . $argv[1] . "\"\n");
}

// set up the exception file
if (file_exists('exceptions.csv')) {
	unlink('exceptions.csv');
}

$csv= file_get_contents($file);
$csvArray = array_map("str_getcsv", explode("\n", $csv));
unset($csv);

// element 0 is the field names. save the values and pop it off
$csvFields= array_shift($csvArray);

// change the assoc array keys to the csv field names
function remap(&$row, $element_id, $fields) {
	$data = array();
	if (is_null($row[0])) {
		return;
	}
	$row = array_combine($fields, $row);
	if (isset($row['date'])) {
		$date = date_create($row['date']);
		if (FALSE === $date) {
			// write an error to exceptions.csv about unconvertible/bad date format
			// line number, data, error message
			$row['line'] = $element_id;
			$row['error'] = 'bad date value, unable to convert';
			$fp = fopen('exceptions.csv', 'a');
			fputcsv($fp, $row);
		} else {
			// make an datetime object from the ISO $row['date']  value, then output the datetime as an int in unix time/epoch saving into the timestamp element
			// move the new field to the beginning of the array to keep the possible error output during cURL consistent
			$row = array('timestamp' => /* (int) */ date_format(date_create($row['date']), "U")) + $row;
			// remove the unwanted ISO date element
			unset($row['date']);
		}
	} elseif (isset($row['timestamp'])) {
		// make sure value is an int
		$row['timestamp'] = (int) $row['timestamp'];
	}

}

array_walk($csvArray, 'remap', $csvFields);

// set default cURL params
$curl = curl_init();

curl_setopt_array($curl, array(
	CURLOPT_URL => $api_url . $endpoint,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER => 0,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "POST",
	CURLOPT_HTTPHEADER => array(
		"Authorization: " . $api_token_key,
		"Content-Type: application/json"
	),
));

foreach ($csvArray as $row => $fields) {
	if (!isset($row['error'])) {
		$json = json_encode($fields, JSON_PRETTY_PRINT);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
		$response = curl_exec($curl);
		$err = curl_error($curl);
		if ($err) {
			die("cURL Error #: " . $err);
		} else {
			$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			if ($status != 201) {
				$error = json_decode($response);
				$fields['line'] = $row;
				$fields['error'] = implode('|', $error->error->messages);
				$fp = fopen('exceptions.csv', 'a');
				fputcsv($fp, $fields);
				fclose($fp);
			} elseif ($status == 201) {
				echo "Line " . $row . " POSTed successfully\n";
			}
		}
	} else {
		$xx=2;
	}
}
curl_close($curl);
echo "\nComplete\n\n Check exceptions.csv for any rows that failed.  The exceptions file will be overwritten on the next run, so be sure to check it prior.\n\n";