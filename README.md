# Smart Fan Control for Unraid

Smart Fan Control is an Unraid plugin for controlling one or more Linux `hwmon` PWM fan outputs from selectable temperature sources. It was initially validated with an NVIDIA Tesla P4 controlling an `nct6793` PWM channel, and also supports hwmon CPU/motherboard/NVMe sensors and array maximum temperature.

## Features

- Multiple independent PWM controllers
- NVIDIA GPU temperature source by stable GPU UUID
- CPU / motherboard / NVMe hwmon temperature sources
- Array highest-temperature source
- Multiple sensors per controller with maximum/average aggregation
- Multi-point linear fan curves
- RPM monitoring, critical-temperature, sensor-loss and stall fail-safes
- Restores the original `pwmX_enable` mode when the service stops
- Unraid WebGUI integration under **Settings -> User Utilities**
- Native Unraid plugin update checking through `pluginURL`
- Persistent hwmon sensor-driver loading via `drivers.conf`
- Independent **Auto Detect Drivers** workflow using local `sensors-detect --auto`
- Driver status/validation in the Unraid WebGUI; Dynamix System Temperature is not required

## Safety

This plugin writes directly to Linux hwmon PWM controls. Prefer BIOS control for CPU cooling. For GPU/chassis fans, verify the PWM/RPM mapping and observe fan speed and temperature during the first enable. Configure a 100% fail-safe.

## Install

After this repository is public, install from **Plugins -> Install Plugin** with:

```text
https://raw.githubusercontent.com/blackboygirl/smartfancontrol/main/smartfancontrol.plg
```

Or from an Unraid shell:

```bash
plugin install https://raw.githubusercontent.com/blackboygirl/smartfancontrol/main/smartfancontrol.plg
```

Existing configuration is kept across upgrades in:

```text
/boot/config/plugins/smartfancontrol/config.json
```

Sensor driver modules are stored separately in:

```text
/boot/config/plugins/smartfancontrol/drivers.conf
```

On upgrades, Smart Fan Control can import an existing Dynamix System Temperature `drivers.conf` once as a convenience. Starting with v0.1.6 this is no longer required: **Settings -> User Utilities -> Smart Fan Control -> Sensor Drivers -> Auto Detect Drivers** runs the local `sensors-detect --auto` workflow, validates the recommended kernel modules, and lets you save/load them into Smart Fan Control's own `drivers.conf`. Automatic detection requires the `sensors-detect` script and Perl to be available on the Unraid host.

## Recommended initial Tesla P4 curve

| GPU temp | PWM |
|---:|---:|
| 45°C | 40% |
| 50°C | 45% |
| 55°C | 50% |
| 60°C | 60% |
| 65°C | 70% |
| 70°C | 80% |
| 75°C | 100% |

## Diagnostics

```bash
/usr/local/emhttp/plugins/smartfancontrol/scripts/diagnose.sh
/etc/rc.d/rc.smartfancontrol drivers
cat /run/smartfancontrol/status.json
tail -n 100 /var/log/smartfancontrol.log
```

## Development

The editable runtime files are under `source/`. Run:

```bash
python3 build.py
```

to regenerate `smartfancontrol.plg`. The build script reads the version from `VERSION`, injects the version into runtime files, and embeds the source files in the self-contained PLG.

## License

MIT
