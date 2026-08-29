# Changelog

## 2026.08.29a
- Add Dashboard fan-display customization for Unraid's native **Fan Information** tile.
- Add **Only show fans with RPM > 0** filtering; zero-RPM channels are hidden dynamically without affecting PWM control or stall protection.
- Add persistent per-channel fan remarks/display names (for example `FAN 5` -> `Tesla P4 Fan`) with live Dashboard updates.
- Add a **Dashboard Fan Display** panel under Smart Fan Control settings showing detected RPM channels, current speed, and editable remarks.
- Store Dashboard display preferences independently in `/boot/config/plugins/smartfancontrol/fan-display.json`.
- Keep fan identification based on `fanN_input` channel numbers so Dashboard customizations survive `hwmonX` renumbering and normal reboots.

## 2026.08.29
- Prepare the repository for Unraid Community Applications submission using the current official starter layout (`ca_profile.xml` plus `plugins/smartfancontrol.xml`).
- Switch the public plugin version scheme from `0.1.x` to Unraid/LimeTech-style date versions (`YYYY.MM.DD`; use `a`, `b`, ... for additional releases on the same day).
- Add a repository icon and Community Applications metadata while preserving all Smart Fan Control v0.1.8 runtime behavior and fixes.
- Keep the canonical `pluginURL` unchanged so existing v0.1.8 installations can discover this release as an online update.

## 0.1.8 (Beta)
- Fix `drivers.conf` persistence: write real newline-separated module names instead of literal `\n` sequences.
- Make sensor-driver loading return a failure when any configured `modprobe` fails, so the WebGUI no longer reports a false successful save/load.
- Include the actual `modprobe` error in diagnostics/log output to make hardware-driver failures actionable.

## 0.1.7 (Beta)
- Fix automatic driver detection when `sensors-detect --auto` returns no recommendations because hwmon drivers are already loaded/bound.
- Make lm-sensors output parsing more tolerant across old/new `sensors-detect` formats, including both plain module lists and `modprobe` snippets.
- Add an independent fallback that resolves currently loaded hwmon backing modules from sysfs and known hwmon modules; it does not read Dynamix configuration.
- Show whether detection came from `sensors-detect`, currently loaded hwmon modules, or both.

## 0.1.6 (Beta)
- Add independent sensor-driver auto-detection using the local `sensors-detect --auto` workflow used by lm-sensors/Dynamix System Temperature.
- Add a WebGUI **Sensor Drivers** panel with auto-detect, editable module list, save/load, and loaded/available status.
- Validate detected/saved module names and verify each module with `modprobe` before persisting it.
- Automatically rescan PWM/RPM/temperature hardware after saving and loading drivers.
- Keep the v0.1.5 Dynamix `drivers.conf` import only as an optional one-time migration; new/clean installs no longer need Dynamix System Temperature to discover drivers.
- Report clearly when `sensors-detect` or Perl is unavailable instead of silently depending on another plugin.
- Preserve all existing Tesla P4/NVIDIA, multi-PWM, fan-curve, fail-safe, online-update, and Unraid 7.x behavior.

## 0.1.5 (Beta)
- Add persistent sensor-driver management at `/boot/config/plugins/smartfancontrol/drivers.conf`.
- On first 0.1.5 install, automatically import the proven driver list from Dynamix System Temperature when available.
- Load configured hwmon modules with `modprobe` whenever Smart Fan Control starts, including at Unraid boot.
- Add `rc.smartfancontrol drivers` for manually reloading configured sensor modules.
- Extend diagnostics to show configured sensor drivers and whether each module is currently loaded.
- Keep driver modules loaded on plugin stop/remove to avoid disrupting other hwmon users.
- Preserve all v0.1.4 online-update, native icon, Unraid 7.x CSRF, multi-PWM, NVIDIA/Tesla P4, curve and fail-safe behavior.

## 0.1.4 (Beta)
- Fix the Plugins page icon by using Unraid's native `icon-fan` glyph instead of unavailable `fa-fan`.

## 0.1.3 (Beta)
- Add a canonical GitHub `pluginURL` so Unraid can check for updates.
- Add GitHub Issues support URL and plugin ownership metadata.
- Add a maintainable source tree and reproducible PLG build script.
- Preserve all v0.1.2 runtime refresh, Unraid 7.x CSRF, multi-PWM, Tesla P4/NVIDIA sensor, curve and fail-safe behavior.

## 0.1.2 (Beta)
- Move settings entry to Settings -> User Utilities.
- Force-clean runtime plugin files during upgrade/reinstall while preserving config.
- Keep the Unraid 7.x CSRF fix and align runtime/package version markers.

## 0.1.1 (Beta)
- Fix Unraid 7.x AJAX CSRF handling by relying on webGui gateway validation.

## 0.1.0 (Beta)
- Multi-PWM independent fan controllers.
- NVIDIA GPU, hwmon and array-max temperature sources.
- Multiple sensors per controller, multi-point curves and fail-safes.
