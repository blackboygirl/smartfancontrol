# Changelog

## 0.1.3 (Beta)
- Add a canonical GitHub `pluginURL` so Unraid can check for updates and no longer shows the plugin as unavailable after publication.
- Add GitHub Issues support URL and a native Unraid fan icon in the Plugins page.
- Set the GitHub repository owner as the plugin author for clearer ownership.
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
