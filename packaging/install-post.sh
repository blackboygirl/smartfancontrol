#!/bin/bash
set -u
NAME="smartfancontrol"
PERSIST="/boot/config/plugins/$NAME"
DRIVERS="$PERSIST/drivers.conf"
DYNAMIX_DRIVERS="/boot/config/plugins/dynamix.system.temp/drivers.conf"

mkdir -p "$PERSIST" /run/$NAME

if [[ ! -f "$PERSIST/config.json" ]]; then
  cp /usr/local/emhttp/plugins/$NAME/default.json "$PERSIST/config.json"
fi
chmod 0644 "$PERSIST/config.json"

# One-time migration of sensor driver modules. If Dynamix System Temperature is
# installed, import its proven module list so Smart Fan Control can take over
# driver loading on future boots. Otherwise preserve any currently loaded
# common hwmon drivers as a best-effort starting point.
if [[ ! -f "$DRIVERS" ]]; then
  if [[ -s "$DYNAMIX_DRIVERS" ]]; then
    awk 'NF && $1 !~ /^#/ {gsub(/^[[:space:]]+|[[:space:]]+$/, ""); if ($0 != "") print $0}' "$DYNAMIX_DRIVERS" > "$DRIVERS"
    logger -t smartfancontrol "Imported sensor drivers from Dynamix System Temperature"
  else
    : > "$DRIVERS"
    for module in coretemp nct6775 k10temp zenpower it87 drivetemp jc42; do
      if lsmod | awk '{print $1}' | grep -qx "$module"; then
        echo "$module" >> "$DRIVERS"
      fi
    done
    logger -t smartfancontrol "Created sensor driver list from currently loaded modules"
  fi
fi
chmod 0644 "$DRIVERS"

/etc/rc.d/rc.smartfancontrol start >/dev/null 2>&1 || true
logger -t smartfancontrol "Smart Fan Control 0.1.5 installed"
echo ""
echo "-----------------------------------------------------------"
echo " Smart Fan Control 0.1.5 (Beta) installed"
echo " Sensor driver config: $DRIVERS"
echo " Open Settings -> User Utilities -> Smart Fan Control to configure it."
echo "-----------------------------------------------------------"
echo ""
