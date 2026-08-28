#!/bin/bash
set +e

echo "=== Smart Fan Control diagnostics ==="
date
echo

echo "[Service]"
/etc/rc.d/rc.smartfancontrol status 2>&1
echo

echo "[Configured sensor drivers]"
if [[ -f /boot/config/plugins/smartfancontrol/drivers.conf ]]; then
  cat /boot/config/plugins/smartfancontrol/drivers.conf
else
  echo "No drivers.conf"
fi
echo

echo "[Sensor driver module status]"
if [[ -f /boot/config/plugins/smartfancontrol/drivers.conf ]]; then
  while IFS= read -r line || [[ -n "$line" ]]; do
    module="${line%%#*}"
    module="$(echo "$module" | xargs 2>/dev/null)"
    [[ -n "$module" ]] || continue
    if lsmod | awk '{print $1}' | grep -qx "$module"; then
      echo "$module: loaded"
    else
      echo "$module: not loaded"
    fi
  done < /boot/config/plugins/smartfancontrol/drivers.conf
fi
echo

echo "[NVIDIA]"
if command -v nvidia-smi >/dev/null 2>&1; then
  nvidia-smi --query-gpu=index,uuid,name,temperature.gpu --format=csv,noheader,nounits 2>&1
else
  echo "nvidia-smi not found"
fi
echo

echo "[hwmon]"
for h in /sys/class/hwmon/hwmon*; do
  [[ -d "$h" ]] || continue
  name="$(cat "$h/name" 2>/dev/null)"
  [[ -n "$name" ]] || continue
  echo "-- $h : $name --"
  for f in "$h"/pwm[0-9]* "$h"/fan*_input "$h"/temp*_input; do
    [[ -f "$f" ]] || continue
    base="$(basename "$f")"
    [[ "$base" =~ ^pwm[0-9]+$ || "$base" =~ ^pwm[0-9]+_enable$ || "$base" =~ ^fan[0-9]+_input$ || "$base" =~ ^temp[0-9]+_input$ ]] || continue
    printf '%-18s %s\n' "$base" "$(cat "$f" 2>/dev/null)"
  done
done
echo

echo "[Config]"
cat /boot/config/plugins/smartfancontrol/config.json 2>/dev/null || echo "No config"
echo

echo "[Status]"
cat /run/smartfancontrol/status.json 2>/dev/null || echo "No runtime status"
echo

echo "[Last log lines]"
tail -50 /var/log/smartfancontrol.log 2>/dev/null || true

