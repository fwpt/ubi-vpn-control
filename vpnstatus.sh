#!/bin/vbash
#
# VPN remote control script for Ubiquiti devices
# Polls the remote control panel and enables or disables the VPN interface
# This allows for remotely starting and stopping the VPN service (OpenVPN) on demand which prevents you from exposing the interface when not required
# Tested on EdgeRouter ER-X 
# Author: github.com/fwpt
# Configuration:
#  1. Set VPN control panel credentials in VPNCTRL_USER and VPNCTRL_PASSWORD environment variables
#  2. Set URLBASE to match control panel URL
#  3. Change vtun0 if you're running OpenVPN on another interface

URLBASE="https://host.name/vpnstatus_v0/status.php?"
TS="&ts=$(date +%s)"

# export your credentials as environment variables
CREDS="$VPNCTRL_USER:$VPNCTRL_PASSWORD"

# make sure script is run as group vyattacfg
if [ 'vyattacfg' != $(id -ng) ]; then
	exec sg vyattacfg -c "$0 $@"
fi

# shorthand for cmd wrapper
cw=/opt/vyatta/sbin/vyatta-cfg-cmd-wrapper

# lock check
# @TODO

# @TODO: on client disconnect: send request stopped_disconnect

function report {

	# report status change back to control panel
	url="${URLBASE}a=servicereport&s=${1}${TS}"
	res=$(curl -u $CREDS -s $url)
}

function startVPN {

	# check current status before starting
	if ps aux | grep -v grep | grep -q vtun0; then
		# already running; ignore
		return
	fi
	
	#echo "Enabling vtun0"
	$cw begin
	# yes this is the right command to enable the vtun0 again (delete the disable flag)
	$cw delete interfaces openvpn vtun0 disable
	$cw commit
	$cw end

	# check if we are started
	if ps aux | grep -v grep | grep -q vtun0; then
		# we are running; good
		#echo "started successfully"
		report "started"
	fi	
}

function stopVPN {

	if ps aux | grep -v grep | grep -q vtun0; then
		# currently running; as expected
		#echo "Disabling vtun0"
		$cw begin
		$cw set interfaces openvpn vtun0 disable
		$cw commit
		$cw end
	fi
	
	# check if we are stopped
	if ps aux | grep -v grep | grep -q vtun0; then
		#echo "we are still running, not stopped!"
		return
	fi
	report "stopped_forced"
}

# poll control panel and check requested action
url="${URLBASE}a=getnewservicestatus${TS}"
res=$(curl -u $CREDS -s $url)

if [ "$res" == "1" ]; then

	# we should start
	startVPN
fi
if [ "$res" == "2" ]; then

	# we should stop/kill process
	stopVPN
fi 

