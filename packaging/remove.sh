#!/bin/bash
set -u
/etc/rc.d/rc.smartfancontrol stop >/dev/null 2>&1 || true
rm -rf /usr/local/emhttp/plugins/smartfancontrol
rm -f /etc/rc.d/rc.smartfancontrol
rm -rf /run/smartfancontrol
rm -rf /boot/config/plugins/smartfancontrol
logger -t smartfancontrol "Smart Fan Control removed"
