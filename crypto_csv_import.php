<?php
$api_url = "https://api.ecrimex.net";
$endpoint = "/groups/c3a060d0bc2869346c91938aa08ede9746a7a1d4";
$api_token_key="<put your eCX API token here>";

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
- `-h` or `-help` for this message\n\n");
} else {
	$file = $argv[1];
}

// custom error handler, $e doesn't get used below but this function exposes any error "more cleanly"
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
Then restart Apache by typing `sudo service apache2 restart`\n");
}

try {
	file_get_contents($file);
}
catch (Exception $e) {
	die("\nFATAL: Unable to open file \"" . $argv[1] . "\"\n");
}

// if an exception file exists, delete it, if errors are found later on in the logic then the file will be created prior to writing it
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
			// date_format returns a string, so cast the return as an int when storing
			$row = array('timestamp' => (int) date_format(date_create($row['date']), "U")) + $row;
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
	CURLOPT_USERAGENT => 'cURL, Bulk CSV Import, Virtual Currency Workgroup',
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

