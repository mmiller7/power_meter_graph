<?php
include 'system_config.php';

$meterId   = getUrlInt('meterId');

//Hour threshold for hourly color coding
define('MORNING_HR', 5);
define('DAYTIME_HR', 8);
define('EVENING_HR', 17);
define('NIGHT_HR', 20);



//constants
define('MINUTE', 60); //Seconds in minute
define('TWO_MIN', 120); //Seconds in 5-min
define('FIVE_MIN', 300); //Seconds in 5-min
define('TEN_MIN', 600); //Seconds in 10-min
define('HOURLY', 3600); //Seconds in hour
define('DAILY', 86400); //Seconds in day
define('WEEKLY', 604800); //Seconds in week
#define('MONTHLY', 2629743); //Seconds in month (avg 30.44 days)
define('MONTHLY', 2628000); //Seconds in month (730 hours, must be multiple of 3600)

define('NOW',time()); //Current local time as UTC timestamp
define('THIS_HOUR',intval(NOW/3600)*3600);
define('TODAY', strtotime('today midnight')); //Midnight local time as UTC timestamp
define('YESTERDAY', strtotime('yesterday midnight')); //Midnight local time as UTC timestamp
define('LAST_SUNDAY', strtotime('midnight last sunday')); //Midnight 1st of this-month local time as UTC timestamp
define('THIS_MONTH', strtotime('midnight first day of this month')); //Midnight 1st of this-month local time as UTC timestamp
define('LAST_MONTH', strtotime('midnight first day of last month')); //Midnight 1st of last-month local time as UTC timestamp
define('THIS_YEAR', strtotime('midnight first day of january this year')); //Midnight this year New Year's Day as UTC timestamp
define('LAST_YEAR', strtotime('midnight first day of january last year')); //Midnight last year New Year's Day as UTC timestamp
	/*
		Note, since we were getting the UTC time corrisponding to the
		local time we want to graph, we don't need to worry about it.
		This will probably do something funky for daylight-savings during
		the hour that is skipped or repeated, I'm not sure.
	*/

//Timezone offset calculations
$dt = new DateTime("now");
$tz_offset=$dt->getOffset();
define('TIMEZONE_OFFSET',$tz_offset);



//Creates a graph with the name (title) specified (avoid special chars)
//from the database specified
//Spanning the duration from $startTime to $endTime (inclusive)
//with each bar representing the time-interval $interval.
//defaults to offset equal to timezone (to correct for midnight time)
//optional additional offset (e.g. to add approximage delta for billing cycle start day)
function graphKwhConsumed($graphName,$db_handle,$meterId,$startTime,$endTime,$interval,$offset = 0)
{
	//Query the DB
	$query_string='SELECT * FROM readings WHERE meter_id == '.$meterId.' AND timestamp >= '.$startTime.' AND timestamp <= '.$endTime.' AND ( timestamp + '.TIMEZONE_OFFSET.' + '.$offset.' ) % '.$interval.' == 0';
	$result = $db_handle->query($query_string);
	$row = $result->fetchArray();

	//echo 'console.log(\'Name='.$graphName.'\');'.PHP_EOL;
	//echo 'console.log(\'query_string='.$query_string.'\');'.PHP_EOL;
	//echo 'console.log(\'first_row='.$row.'\');'.PHP_EOL;

	if($row === false) //If the first row returned nothing there is no data to process, don't try and graph!
	{
		echo '<p style="font-family: \'Open Sans\', verdana, arial, sans-serif;">'.$graphName.' - No data available.</p>'.PHP_EOL;
	}
	else
	{
		//Prepare the data set for the graph
		$x='x: [';
		$y='y: [';
		$color='color: [';

		//Used to init the data so we can display the power-used over
		//the hour stated (e.g. 8AM is 8AM-9AM consumption)
		$prevRow=$row;
		$row = $result->fetchArray();

		$startKwh=$prevRow['kwh']; //used to calc total

		//Used by the loop to handle adding commas between values
		$firstRun=true;
		while($row !== false)
		{
			//If this isn't the first value in the list, add comma separators
			if(!$firstRun)
			{
				$x=$x.',';
				$y=$y.',';
				$color=$color.',';
			}
			$firstRun=false;

			//Format the X-axis labels
			$time_units=date('Y/m/d H:i',$prevRow['timestamp']);
			if($interval < DAILY)
			{
				if($endTime-$startTime > DAILY)
				{
					//X-axis units for hourly over multiple days
					$time_units=date('H:i - m/d',$prevRow['timestamp']);
					$hour=date('H',$prevRow['timestamp']);
					if($hour == 0)
					{
						$time_units='<b>'.$time_units.'</b>';
					}
				}
				else
				{
					//X-axis units for hourly over a single day
					$time_units=date('H:i',$prevRow['timestamp']);
				}
			}
			else if($interval == DAILY)
			{
				$time_units=date('M-d',$prevRow['timestamp']);
			}
			else if($interval > DAILY)
			{
				$time_units=date('Y/m/d',$prevRow['timestamp']);
			}

			//Format the bar colors
			if($interval < DAILY)
			{
				//daily formats for night/morning/day/evening colors
				$hour=date('H',$prevRow['timestamp']);
				if($hour == 0) //midnight
				{
					$thisColor='rgb(0,0,0)';
				}
				else if($hour == 12) //noon
				{
					$thisColor='rgb(255,238,0)';
				}
				else if($hour < MORNING_HR) //night before-morning
				{
					$thisColor='rgb(0,0,102)';
				}
				else if($hour >= MORNING_HR && $hour < DAYTIME_HR) //morning
				{
					$thisColor='rgb(153,153,102)';
				}
				else if($hour >= DAYTIME_HR && $hour < EVENING_HR) //day
				{
					$thisColor='rgb(255,204,0)';
				}
				else if($hour >= EVENING_HR && $hour < NIGHT_HR) //evening
				{
					$thisColor='rgb(102,102,153)';
				}
				else //late night
				{
					$thisColor='rgb(0,0,102)';
				}
			}
			else
			{
				//default color for non-hourly charts
				$thisColor='rgb(46,46,184)';
			}

			//Calculate the kwh used between meter reading interval
			$kwhUsed=$row['kwh']-$prevRow['kwh'];

			//Append the data to the graph data-set
			$x=$x.'\''.$time_units.'\'';
			$y=$y.'\''.$kwhUsed.'\'';
			$color=$color.'\''.$thisColor.'\'';

			//Get the next row of the data-set
			$prevRow=$row;
			$row = $result->fetchArray();
		}
		//Close the graph data-set
		$x=$x.'],';
		$y=$y.'],';
		$color=$color.']';

		//Compute the total kwh used for this entire graph
		$endKwh=$prevRow['kwh']; //used to calc total
		$totalKwhUsed=$endKwh-$startKwh;



		//Now that we have all the data, let's try and draw the graph
		?>
		<div id="<?php echo $graphName; ?>"><!-- Plotly chart will be drawn inside this DIV --></div>
		<script>
			var data = [
				{
					<?php echo $x.PHP_EOL; ?>
					<?php echo $y.PHP_EOL; ?>
					//x: ['giraffes', 'orangutans', 'monkeys'],
					//y: [20, 14, 23],
					marker: {
						<?php echo $color.PHP_EOL; ?>
						//color: 'rgb(51, 51, 153)'
					},
					type: 'bar',
				}
			];
			var layout = {
				title: '<?php echo "$graphName (Total Usage: ".number_format($totalKwhUsed,2)." kWh)"; ?>',
				yaxis: {
					fixedrange: true,
					zeroline: true,
					rangemode: 'tozero',
					gridwidth: 2
				},
				xaxis: {
					fixedrange: true
				},
				bargap: 0.05//,
				//width: 0.5
			};
			Plotly.newPlot('<?php echo $graphName; ?>', data, layout);
		</script>
		<?php
		//Okay - we're done graphing it

	} //end of if-check is first row valid
} //end of graphKwhConsumed



//Creates a graph of average power draw (killowatts) with the name (title) specified (avoid special chars)
//from the database specified
//Spanning the duration from $startTime to $endTime (inclusive)
//with line points representing the time-interval $interval.
//defaults to offset equal to timezone (to correct for midnight time)
//optional additional offset (e.g. to add approximage delta for billing cycle start day)
function graphPowerDraw($graphName,$db_handle,$meterId,$startTime,$endTime,$interval,$offset = 0)
{
	//Query the DB
	$query_string='SELECT * FROM readings WHERE meter_id == '.$meterId.' AND timestamp >= '.$startTime.' AND timestamp <= '.$endTime.' AND ( timestamp + '.TIMEZONE_OFFSET.' + '.$offset.' ) % '.$interval.' == 0';
	$result = $db_handle->query($query_string);
	$row = $result->fetchArray();

	//echo 'console.log(\'Name='.$graphName.'\');'.PHP_EOL;
	//echo 'console.log(\'query_string='.$query_string.'\');'.PHP_EOL;
	//echo 'console.log(\'first_row='.$row.'\');'.PHP_EOL;

	if($row === false) //If the first row returned nothing there is no data to process, don't try and graph!
	{
		echo '<p style="font-family: \'Open Sans\', verdana, arial, sans-serif;">'.$graphName.' - No data available.</p>'.PHP_EOL;
	}
	else
	{
		//Prepare the data set for the graph
		$x='x: [';
		$y='y: [';
		$color='color: [';

		//Used to init the data so we can display the power-used over
		//the hour stated (e.g. 8AM is 8AM-9AM consumption)
		$prevRow=$row;
		$row = $result->fetchArray();

		$startKwh=$prevRow['kwh']; //used to calc total

		//Used by the loop to handle adding commas between values
		$firstRun=true;
		while($row !== false)
		{
			//If this isn't the first value in the list, add comma separators
			if(!$firstRun)
			{
				$x=$x.',';
				$y=$y.',';
			}
			$firstRun=false;

			//Format the X-axis labels
			$time_units=date('Y/m/d H:i',$prevRow['timestamp']);
			if($interval < DAILY)
			{
				if($endTime-$startTime > DAILY)
				{
					//X-axis units for hourly over multiple days
					$time_units=date('H:i - m/d',$prevRow['timestamp']);
					$hour=date('H',$prevRow['timestamp']);
					if($hour == 0)
					{
						$time_units='<b>'.$time_units.'</b>';
					}
				}
				else
				{
					//X-axis units for hourly over a single day
					$time_units=date('H:i',$prevRow['timestamp']);
				}
			}
			else if($interval == DAILY)
			{
				$time_units=date('M-d',$prevRow['timestamp']);
			}
			else if($interval > DAILY)
			{
				$time_units=date('Y/m/d',$prevRow['timestamp']);
			}

			//Calculate the kwh used between meter reading interval
			$kwhUsed=$row['kwh']-$prevRow['kwh'];
			$eTime=$row['timestamp_rx']-$prevRow['timestamp_rx'];
			$eTimeHours=$eTime/3600;

			//Compute the time factor to multiply by so time cancels leaving instant average current
			$timeScalor=1/$eTimeHours;

			//Multiply to cancel time-units and get power
			$avgPower=$kwhUsed*$timeScalor;

			//Append the data to the graph data-set
			$x=$x.'\''.$time_units.'\'';
			$y=$y.'\''.$avgPower.'\'';

			//Get the next row of the data-set
			$prevRow=$row;
			$row = $result->fetchArray();
		}
		//Close the graph data-set
		$x=$x.'],';
		$y=$y.'],';

		//Now that we have all the data, let's try and draw the graph
		?>
		<div id="<?php echo $graphName; ?>"><!-- Plotly chart will be drawn inside this DIV --></div>
			<script>
				var data = [
					{
						<?php echo $x.PHP_EOL; ?>
						<?php echo $y.PHP_EOL; ?>
						mode: 'lines',
					}
			];
			var layout = {
				title: '<?php echo "$graphName"; ?>',
				yaxis: {
					fixedrange: true,
					zeroline: true,
					rangemode: 'tozero',
					gridwidth: 2
				},
				xaxis: {
					fixedrange: true
				}
			};
			Plotly.newPlot('<?php echo $graphName; ?>', data, layout);
		</script>
		<?php
		//Okay - we're done graphing it

	} //end of if-check is first row valid
} //end of graphPowerDraw



//Prints estimated instantanious(-ish) stats
//from the database specified
//defaults to offset equal to timezone (to correct for midnight time)
function estimateCurrentPower($db_handle,$meterId)
{
	//Query the DB
	$query_string='SELECT * FROM readings WHERE meter_id == '.$meterId.' ORDER BY timestamp DESC LIMIT 2';
	$result = $db_handle->query($query_string);
	$row = $result->fetchArray();

	if($row === false) //If the first row returned nothing there is no data to process, don't try and graph!
	{
		echo '<p style="font-family: \'Open Sans\', verdana, arial, sans-serif;">Estimated Current Power - No data available.</p>'.PHP_EOL;
	}
	else
	{
		$currentRecord=$row;
		$row = $result->fetchArray();
		$prevRecord=$row;

		$kwhUsed=$currentRecord['kwh']-$prevRecord['kwh'];
		$eTimeSeconds=$currentRecord['timestamp_rx']-$prevRecord['timestamp_rx'];
		$eTimeHours=$eTimeSeconds/3600;

		//Compute the time factor to multiply by so time cancels leaving instant average current
		$timeScalor=1/$eTimeHours;

		//Multiply to cancel time-units and get power
		$estimatedPower=$kwhUsed*$timeScalor;

		//echo '<p style="font-family: \'Open Sans\', verdana, arial, sans-serif;">Estimated Current Power Draw - '.number_format($estimatedPower,2).'kW avg over past '.number_format($eTimeSeconds,0).' seconds</p>'.PHP_EOL;
		echo '<p style="font-family: \'Open Sans\', verdana, arial, sans-serif;">Estimated Current Power Draw - '.number_format($estimatedPower,2).'kW</p>'.PHP_EOL;
	}
}



//Gets a list of meters in the database
function getMeterIdList($db_handle)
{
	//Query the DB
	$query_string='SELECT DISTINCT meter_id FROM readings ORDER BY meter_id';
	$result = $db_handle->query($query_string);
	$row = $result->fetchArray();

	//Array to store meter IDs
	$meters = array();

	//Iterate thru DB results
	while($row !== false)
	{
		// Add entries to the list of meters
		array_push($meters,$row['meter_id']);
	
		$row = $result->fetchArray();
	}

	return $meters;
}



//Generate drop-down list of meters to pick from
function meterListSelection($db_handle,$default=false)
{
	$meters = getMeterIdList($db_handle);

	echo '<form action="html_graph.php">'.PHP_EOL;
	echo 'Meter ID: '.PHP_EOL;
	echo '<select name="meterId">'.PHP_EOL;
	foreach($meters as $meter)
	{
		if($default === $meter)
		{
			echo '<option value="'.$meter.'" selected=true>'.$meter.'</option>'.PHP_EOL;
		}
		else
		{
			echo '<option value="'.$meter.'">'.$meter.'</option>'.PHP_EOL;
		}
	}
	echo '</select>'.PHP_EOL;
	echo '<input type="submit" value="Update Graphs">'.PHP_EOL;
	echo '</form>'.PHP_EOL;
	echo '<br>'.PHP_EOL;
}



//Get URL data if it exists
function getUrlStr($field)
{
	if(isset($_GET[$field]))
	{
		return $_GET[$field];
	}
	else
	{
		return false;
	}
}

//Get URL data if it exists
function getUrlInt($field)
{
	if(isset($_GET[$field]))
	{
		return intval($_GET[$field]);
	}
	else
	{
		return false;
	}
}

?>




<html>
<head>
</head>
<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
<body>
<center>

<?php
meterListSelection($hourly_db_handle,$meterId);

if($meterId !== false) //no meter ID specified
{
	estimateCurrentPower($minute_db_handle,$meterId);
	//graphKwhConsumed("Current Hour",$minute_db_handle,$meterId,THIS_HOUR,NOW,MINUTE);
	graphPowerDraw("Current Hour Power Draw (kW)",$minute_db_handle,$meterId,THIS_HOUR,NOW,TWO_MIN);

	graphKwhConsumed("Hourly Usage Today",$hourly_db_handle,$meterId,TODAY,NOW,HOURLY);
	graphKwhConsumed("Hourly Usage Yesterday",$hourly_db_handle,$meterId,YESTERDAY,TODAY,HOURLY);

	//graphKwhConsumed("Hourly Past 72 Hours",$hourly_db_handle,$meterId,NOW-(72*HOURLY),NOW,HOURLY);

	graphKwhConsumed("Daily Usage This Month",$hourly_db_handle,$meterId,THIS_MONTH,TODAY,DAILY);
	graphKwhConsumed("Daily Usage Last Month",$hourly_db_handle,$meterId,LAST_MONTH,THIS_MONTH,DAILY);

	graphKwhConsumed("Monthly Usage This Year",$hourly_db_handle,$meterId,THIS_YEAR,TODAY,MONTHLY,(BILLING_OFFSET*DAILY));
	graphKwhConsumed("Monthly Usage Last Year",$hourly_db_handle,$meterId,LAST_YEAR,THIS_YEAR,MONTHLY,(BILLING_OFFSET*DAILY));
?>

</center>

<br><br><br><br>

</body>
</html>

<?php
} //end IF meterId
?>


