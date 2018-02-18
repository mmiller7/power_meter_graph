<?php
// Matthew Miller
// 8 Feb 2018
// Pipe the output of the rtlamr to this script (uncomment matching format below)
// $GOCODE/bin/rtlamr -filterid=12345678 -msgtype=scm -format=json | php rtlamr2sqlite.php
// $GOCODE/bin/rtlamr -filterid=12345678 -msgtype=scm -format=csv | php rtlamr2sqlite.php
// Note - the command you run must match the decoder specified below

//Database connect
include 'system_config.php';

//some constants for intervals of interest
define('NOW',time()); //Current local time as UTC timestamp
define('MINUTE', 60); //Seconds in minute
define('FIVE_MIN', 300); //Seconds in 5-min
define('TEN_MIN', 600); //Seconds in 10-min
define('HOURLY', 3600); //Seconds in hour
define('DAILY', 86400); //Seconds in day

//Open stdin to process data
$f = fopen( 'php://stdin', 'r' );

//Process incoming data line by line
while( $line = fgets( $f ) )
{
//***************************************

	// Specify format to decode

	//include 'decode_scm_json.php';
	include 'decode_scm_csv.php';

//***************************************

	/*
	Inputs:
					$line

	Outputs:
					$rxTimeStr
					$meterId
					$meterKwh
	*/

	insertDbFunction($hourly_db_handle,HOURLY,$rxTimeStr,$meterId,$meterKwh);

	if(isset($minute_db_handle))
	{
		insertDbFunction($minute_db_handle,MINUTE,$rxTimeStr,$meterId,$meterKwh,NOW-(2*DAILY));
	}

}


//This function takes the raw meter readings and puts it into the database at specified interval
function insertDbFunction($db_handle,$interval,$rxTimeStr,$meterId,$meterKwh,$cleanupBefore=0)
{
	//Fix the ISO time format so PHP can process it (trim long decimal seconds)
	$rxTimeStrFixed=preg_replace('/\.[0-9]+/',"",$rxTimeStr);

	//Convert time to UNIX format (seconds since epoch)
	$rxTime=strtotime($rxTimeStrFixed);

	//Get rounded down/up hours
	$prevTime=intval($rxTime/$interval)*$interval; //3600 sec per hour, drop fractional part then multiply back

	//Check if we already have a record for the time
	$query_string='SELECT * FROM readings WHERE meter_id == '.$meterId.' AND timestamp == '.$prevTime;
	$result     = $db_handle->query($query_string);
	$row        = $result->fetchArray();

	echo "+ Processing $interval sec reading for $prevTime rx-time $rxTime ";

	//If we found no result for the prevTime timestamp, insert it to the database
	if($row === false)
	{
		echo "Inserting into database.";

		//Build insert-statment for database
		$query_string='INSERT INTO readings VALUES('.$prevTime.','.$rxTime.','.$meterId.','.$meterKwh.')';

		//Insert into database
		$db_handle->exec($query_string);

	}
	echo PHP_EOL;

	//If a cleanup start-date is specified, purge older records
	if($cleanupBefore !== 0)
	{
		echo "- Purging $interval sec readings prior to $cleanupBefore".PHP_EOL;

		//Build delete-statment for database
		$query_string='DELETE FROM readings WHERE timestamp < '.$cleanupBefore;

		//Insert into database
		$db_handle->exec($query_string);
	}
}



fclose( $f );

?>
