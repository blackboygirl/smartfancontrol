# Changelog

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
