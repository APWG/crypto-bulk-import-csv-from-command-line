#Bulk import CSV data into the eCX Virtual Currency Data Exchange Workgroup

A file of CSV data is read, saved into an array, and each element in the array converted to JSON to match the eCX API submission format.  The JSON data is POSTed to eCX and the logic catches the result code and any error messages.  Rejected submissions are saved into an exception file. 

Usage:  
`php crypto_csv_import.php /path/to/filename.csv`


*Params:*
- CSV filename, the /path/to/the/filename of the CSV file you want to bulk import
- `-h` or `-help` for this message

*Requirements:*
- Written to be run on an Ubuntu system, but not Ubuntu specific. Untested on Windows or other OS 
- The php_curl module needs to be installed so that the script can POST data over cURL to the eCX API
- The first line in the CSV file will be a header, noting the column names.  Be sure that you match up the column names to the `name` value in the Threat Model for the workgroup.
- The schema of your CSV data most likley not perfectly match the Workgroups Threat Model.  So, in your CSV file be sure to include any of the Threat Models required fields with default values
- The 6 required fields for the Virtual Currency Data Exchange Workgroup are: source, procedure, timestamp*, currency, address, and tag - so at a minimum you should have these matching 6 columns in your CSV file.  Other fields in your CSV may match up as well.
- The procedure and currency fields accept only certain values, be sure you match your data up correctly
- The number of CSV header column names must match the number of delimited fields
- Use the Postman API client to test a few rows of your CSV data that you've converted to JSON (http://www.convertcsv.com/csv-to-json.htm) to make sure that you can POST correctly prior to running this script

*Date Option:*
- Use of epoch date values are *highly* encouraged, they take into consideration date and time and timezone and come back with a known valid combination of these that will always be correct and always convert correctly into any ISO format.  Converting ISO dates without timestamp or timezone data results in strange epoch values.  
- Epoch date fields in the Threat Model must be in the CSV file as epoch date values in a `timestamp` column.  Use =(A2-25569)*86400 to convert ISO dates to epoch, where A2 is your ISO date cell ID.  Set your non-epoch date cell format to Date, and your epoch column to Number, zero decimal places.  Then hide the ISO date column when saving the Excel file as CSV so that it is not included  
- However.... if you omit the `timestamp` column holding epochs, and instead provide a `date` column - containing ISO dates - the script will attempt to convert the ISO dates to epoch for you.  
- Note that with all the various ways dates might be represented in a spreadsheet that the ISO date value may not be supported by the epoch conversion logic, mm-dd-yyy hh:mm is the best ISO date format to use.  You may see POST failures in the exceptions.csv output file from failed ISO date conversions

*Settings:*
- Edit lines 2-4 of this script with the correct values to access the eCX API prior to running.
- The $endpoint value is currently set to the Virtual Currency Data Exchange Workgroup

*Output:* 
- Progress indicator to the console
- The file `exceptions.csv` (if already exists) is initialized with a listing of the data that failed to validate or POST, along with any API result code and API error message/s for each row that failed\n\n");

***Installing PHP_cURL***

Follow these instructions to install cURL on Ubuntu for PHP 5.x:
Open SSH\n

First Install CURL by typing `sudo apt-get install curl`

Then Restart Apache by typing `sudo service apache2 restart`

Then Install PHP5 CURL by typing `sudo apt-get install php5-curl`

The install script will prompt you to install... type y or yes!

Then restart Apache by typing `sudo service apache2 restart