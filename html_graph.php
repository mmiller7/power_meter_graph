<?php
$hourly_db_handle	= new SQLite3('meter_readings.sqlite3.db');
date_default_timezone_set('America/New_York');



//constants
define('HOURLY', 3600); //Seconds in hour
define('DAILY', 86400); //Seconds in day
define('WEEKLY', 604800); //Seconds in week
define('MONTHLY', 2629743); //Seconds in month (avg 30.44 days)

define('NOW',time()); //Current local time as UTC timestamp
define('TODAY', strtotime('today midnight')); //Midnight local time as UTC timestamp
define('YESTERDAY', strtotime('yesterday midnight')); //Midnight local time as UTC timestamp
//define('LAST_SUNDAY', strtotime('midnight last sunday')); //Midnight 1st of this-month local time as UTC timestamp
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

//Hour threshold for hourly color coding
define('MORNING_HR', 5);
define('DAYTIME_HR', 8);
define('EVENING_HR', 17);
define('NIGHT_HR', 20);

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
function graphFunction($graphName,$db_handle,$startTime,$endTime,$interval,$offset = 0)
{


	//Query the DB
	$query_string='SELECT * FROM readings WHERE timestamp >= '.$startTime.' AND timestamp <= '.$endTime.' AND ( timestamp + '.TIMEZONE_OFFSET.' + '.$offset.' ) % '.$interval.' == 0';
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
				title: '<?php echo "$graphName (Total Usage: ".number_format($totalKwhUsed,2).")"; ?>',
				yaxis: {
					fixedrange: true,
					zeroline: false,
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
} //end of graph generator function
?>



<html>
<head>
</head>
<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
<body>
<center>
<?php graphFunction("Usage Today",$hourly_db_handle,TODAY,NOW,HOURLY); ?>
<?php graphFunction("Usage Yesterday",$hourly_db_handle,YESTERDAY,TODAY,HOURLY); ?>

<?php //graphFunction("Hourly Past 72 Hours",$hourly_db_handle,NOW-(72*HOURLY),NOW,HOURLY); ?>

<?php graphFunction("Daily Usage This Month",$hourly_db_handle,THIS_MONTH,TODAY,DAILY); ?>
<?php graphFunction("Daily Usage Last Month",$hourly_db_handle,LAST_MONTH,THIS_MONTH,DAILY); ?>

<?php graphFunction("Monthly Usage This Year",$hourly_db_handle,THIS_YEAR,TODAY,MONTHLY); ?>
<?php graphFunction("Monthly Usage Last Year",$hourly_db_handle,LAST_YEAR,THIS_YEAR,MONTHLY); ?>
</center>

<br><br><br><br>

</body>
</html>
