#!/bin/bash
set -u
mkdir -p /boot/config/plugins/smartfancontrol /run/smartfancontrol
if [[ ! -f /boot/config/plugins/smartfancontrol/config.json ]]; then
  cp /usr/local/emhttp/plugins/smartfancontrol/default.json /boot/config/plugins/smartfancontrol/config.json
fi
chmod 0644 /boot/config/plugins/smartfancontrol/config.json
/etc/rc.d/rc.smartfancontrol start >/dev/null 2>&1 || true
logger -t smartfancontrol "Smart Fan Control 0.1.4 installed"
echo ""
echo "-----------------------------------------------------------"
echo " Smart Fan Control 0.1.4 (Beta) installed"
echo " Open Settings -> User Utilities -> Smart Fan Control to configure it."
echo "-----------------------------------------------------------"
echo ""
