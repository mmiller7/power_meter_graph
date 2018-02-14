# power_meter_graph

This is based off the project from https://hackaday.com/2017/12/21/read-home-power-meters-with-rtl-sdr/

Files:
* rtlamr2sqlite.php
This script accepts input from rtlamr over stdin and processes it into the SQLite database

* html_graph.php
This script runs on a web-server and pulls data out of the SQLite database and renders graphs

* meter_readings.sqlite3.db
* meter_readings.sqlite3.db.empty
This is the SQLite database and spare empty copy (in case you manage to corrupt it or want to start over)


The following files are decoding "plugins" for rtlamr2sqlite to process different output formats.
This is useful if you have been dumping data to a file (e.g. CSV) and want to switch to a nicer format.
* decode_scm_csv.php
* decode_scm_json.php
