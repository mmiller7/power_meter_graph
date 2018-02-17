#!/bin/bash

set -e

if [ "$(whoami)" != "root" ]; then
	echo "ERROR: This script be run as root!"
	exit 1
fi



echo '**** Installing dependencies ****'
apt-get -y install git install golang-go

echo '**** Installing rtlamr decoder ****'
mkdir /opt/rtlamr
export $GOPATH=/opt/rtlamr
go get github.com/bemasher/rtlamr
ln -s /opt/rtlamr/bin/rtlamr /usr/local/bin

echo '**** Installing RTL-SDR Driver ****'
git clone https://github.com/mmiller7/rtl-sdr_linux_driver.git
rtl-sdr_linux_driver/sdrDriverInstall.sh
