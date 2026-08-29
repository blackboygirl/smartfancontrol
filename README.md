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
- Dashboard **Fan Information** cleanup: optionally hide zero-RPM channels
- Per-channel Dashboard fan remarks/display names (for example `FAN 5` -> `Tesla P4 Fan`)

## Sensor driver discovery

Smart Fan Control can detect hwmon driver modules with local `sensors-detect --auto`, persist one module per line in `/boot/config/plugins/smartfancontrol/drivers.conf`, and load them with `modprobe` on startup.


## Dashboard fan display

Under **Settings -> User Utilities -> Smart Fan Control -> Dashboard Fan Display**, the plugin lists detected `fanN_input` RPM channels and lets you:

- enable **Only show fans with RPM > 0** for Unraid's native **Fan Information** Dashboard tile;
- assign a custom remark/display name to each Dashboard channel, such as `CPU Fan`, `Case Intake`, or `Tesla P4 Fan`;
- keep these display preferences separate from control logic, so hiding or renaming a Dashboard item never changes PWM output, RPM fail-safe checks, or controller mappings.

Display preferences are stored in:

```text
/boot/config/plugins/smartfancontrol/fan-display.json
```

The Dashboard helper watches Unraid's native fan tile for live updates, so a fan that changes between 0 RPM and a positive speed is hidden/shown automatically when zero-RPM filtering is enabled.

## Safety

This plugin writes directly to Linux hwmon PWM controls. Prefer BIOS control for CPU cooling. For GPU/chassis fans, verify the PWM/RPM mapping and observe fan speed and temperature during the first enable. Configure a 100% fail-safe.

## Install

### Community Applications

After the repository is accepted into Unraid Community Applications, open **Apps**, search for **Smart Fan Control**, and select **Install**.

### Manual plugin install

Install from **Plugins -> Install Plugin** with:

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

## Versioning

Public releases use the Unraid/LimeTech-style `YYYY.MM.DD` version format. If multiple releases are published on the same day, append a letter such as `2026.08.29a`. The previous development line ended at `0.1.8`; `2026.08.29` is the first Community Applications submission candidate; `2026.08.29a` adds Dashboard fan filtering and custom remarks; `2026.08.29b` fixes zero-RPM filtering for the native Unraid 7.3.x fan tile markup; `2026.08.29c` compacts the remaining visible fans into a gap-free three-column Dashboard layout.

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


### Driver detection behavior

The driver detector runs `sensors-detect --auto` first. If lm-sensors returns no recommendations because the relevant drivers are already loaded, Smart Fan Control independently inspects currently loaded hwmon modules/sysfs and merges those results. This fallback does not depend on Dynamix System Temperature.
