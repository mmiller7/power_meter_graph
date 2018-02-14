<?php
// Matthew Miller
// 8 Feb 2018
// Pipe the output of the rtlamr to this script (uncomment matching format below)
// $GOCODE/bin/rtlamr -filterid=12345678 -msgtype=scm -format=json | php rtlamr2sqlite.php
// $GOCODE/bin/rtlamr -filterid=12345678 -msgtype=scm -format=csv | php rtlamr2sqlite.php

//Database connect
$db_handle  = new SQLite3('meter_readings.sqlite3.db');
date_default_timezone_set('America/New_York');

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


	//Fix the ISO time format so PHP can process it (trim long decimal seconds)
	$rxTimeStrFixed=preg_replace('/\.[0-9]+/',"",$rxTimeStr);

	//Convert time to UNIX format (seconds since epoch)
	$rxTime=strtotime($rxTimeStrFixed);

	//Get rounded down/up hours
	$prevHour=intval($rxTime/3600)*3600; //3600 sec per hour, drop fractional part then multiply back
	//$nextHour=$roundDownTime+3600; //advance to next hour

	//Check if we already have a record for the time
	$query_string='SELECT * FROM readings WHERE timestamp == '.$prevHour;
	$result     = $db_handle->query($query_string);
	$row        = $result->fetchArray();

	echo "Processing reading for $prevHour rx-time $rxTime ";

	//If we found no result for the prevHour timestamp, insert it to the database
	if($row === false)
	{
		echo "Inserting into database.";

		//Build insert-statment for database
		$query_string='INSERT INTO readings VALUES('.$prevHour.','.$rxTime.','.$meterId.','.$meterKwh.')';

		//Insert into database
		$db_handle->exec($query_string);

	}
	echo PHP_EOL;

}

fclose( $f );

?>
