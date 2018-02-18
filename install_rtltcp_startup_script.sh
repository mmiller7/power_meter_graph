#!/bin/bash

#Install init.d script template
echo '**** Fetching init.d script template ****'
git clone https://github.com/fhd/init-script-template.git
cd init-script-template
echo '**** Configuring aprx init.d from template ****'
cp template rtl_tcp
sed -i 's|cmd=""|cmd="/usr/local/bin/rtl_tcp"|g' rtl_tcp
sed -i 's/user=""/user="rtl_tcp"/g' rtl_tcp
sed -i 's/# Required-Start:    $remote_fs $syslog/# Required-Start:    $remote_fs $syslog rtl_tcp/g' rtl_tcp
sed -i 's/# Provides:/# Provides: rtl_tcp/g' rtl_tcp
sed -i 's/# Description:       Enable service provided by daemon./# Description:       Starts rtl_tcp daemon/g' rtl_tcp

echo '**** Installing aprx init.d ****'
cp rtl_tcp /etc/init.d/

# Give it a limited user account to run under
useradd -r -s /sbin/nologin -M rtl_tcp

# Enable it
update-rc.d rtl_tcp defaults
systemctl enable rtl_tcp

# To disable it
echo 'If you change your mind, to disable rtl_tcp at startup run these:'
echo '   systemctl disable rtl_tcp'
echo '   update-rc.d -f rtl_tcp remove'
echo ''
echo 'Service rtl_tcp is installed but not started until next boot.'
echo 'Done.'
