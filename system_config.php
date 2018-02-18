<?php
//System dependant configuration for reading/writing to the database

$hourly_db_handle  = new SQLite3('meter_readings.sqlite3.db');
$minute_db_handle  = new SQLite3('minute_meter_readings.sqlite3.db');

date_default_timezone_set('America/New_York');
?>
