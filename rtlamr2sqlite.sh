#!/bin/bash
/opt/rtlamr/bin/rtlamr -freqcorrection=42 -msgtype=scm -format=csv | php /opt/power_meter_graph/rtlamr2sqlite.php

