<?php
// Matthew Miller
// 8 Feb 2018
// This parses JSON format to extract SCM meter readings

/*
Inputs:
				$line

Outputs:
				$rxTimeStr
				$meterId
				$meterKwh
*/

	// Decode the line as JSON data
  $record = json_decode($line);

  if($record !== NULL)
  {
    // Pull out significant data
    $rxTimeStr=$record->{'Time'};
    $meterId=$obj->{'Message'}->{'ID'};
    $meterKwh=$obj->{'Message'}->{'Consumption'}/100.00;
  }
?>
