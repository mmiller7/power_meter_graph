<?php
// Matthew Miller
// 8 Feb 2018
// This parses CSV format to extract SCM meter readings

/*
Inputs:
				$line

Outputs:
				$rxTimeStr
				$meterId
				$meterKwh
*/


	// Decode the line as CSV data
  $record = explode(",", $line);

  if($record !== NULL)
  {
    // Pull out significant data
    $rxTimeStr=$record[0];
    $meterId=$record[3];
    $meterKwh=$record[7]/100.00;
  }
?>
