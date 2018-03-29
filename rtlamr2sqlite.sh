#!/bin/bash

_term() {
  echo "Caught SIGTERM signal, terminating rtlamr SDR process..."
  kill -TERM "$child" 2>/dev/null
}

trap _term SIGTERM

delay=10
echo "`date` - Waiting $delay seconds before starting rtlamr..."
sleep $delay
echo "`date` - Attempting to start rtlamr and pipe to php..."
/opt/rtlamr/bin/rtlamr -freqcorrection=42 -msgtype=scm -format=csv > >(/usr/bin/php /opt/power_meter_graph/rtlamr2sqlite.php) &
child=$!

echo "rtlamr SDR process PID=$child"

wait $child

