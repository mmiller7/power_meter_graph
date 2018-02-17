# power_meter_graph

This is based off the project from https://hackaday.com/2017/12/21/read-home-power-meters-with-rtl-sdr/

Files:
* install_sdr.sh
This script installs the rtlamr decoder and RTL-SDR drivers.

* rtlamr2sqlite.php
This script accepts input from rtlamr over stdin and processes it into the SQLite database

* html_graph.php
This script runs on a web-server and pulls data out of the SQLite database and renders graphs

* meter_readings.sqlite3.db
* meter_readings.sqlite3.db.empty
This is the SQLite database and spare empty copy (in case you manage to corrupt it or want to start over)

* minute_meter_readings.sqlite3.db
This is a SQLite database for storing very recent rolling metrics that are updated with greater frequency.
If you are running it on a SD card such as Raspberry pi you probably want to put this in a tempfs.

The following files are decoding "plugins" for rtlamr2sqlite to process different output formats.
This is useful if you have been dumping data to a file (e.g. CSV) and want to switch to a nicer format.
* decode_scm_csv.php
* decode_scm_json.php



Installation & Use

After you install the programs using install_sdr.sh you will need to pick your webserver and install that.
Once you have installed the webserver, you can put the php files in the appropriate locations and
tweak html_graph.php to point at the sqlite database files.

Note - If you use the per-minute stats and run from a SD card, I recommend putting the
minute_meter_readings.sqlite3.db on a tmpfs and set up a startup script to copy a fresh database
over on boot so it will run in RAM instead of writing a ton to the flash memory.

TODO: Make a script+watchdog that runs the RTL-SDR and pipes it to the PHP DB insert script
This will also need tweaking to have your meter ID(s) to store in the DB
