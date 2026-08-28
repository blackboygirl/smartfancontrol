#!/bin/bash
set -u
/etc/rc.d/rc.smartfancontrol stop >/dev/null 2>&1 || true
# Force a clean runtime refresh on upgrade/reinstall. Persistent config on /boot is preserved.
rm -rf /usr/local/emhttp/plugins/smartfancontrol
rm -f /etc/rc.d/rc.smartfancontrol
rm -rf /run/smartfancontrol
mkdir -p /boot/config/plugins/smartfancontrol
