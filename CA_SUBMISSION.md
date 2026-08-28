# Community Applications submission checklist

This repository follows the current Unraid Community Apps starter layout for plugins.

## Required repository files

- `ca_profile.xml` in the repository root.
- `plugins/smartfancontrol.xml` as the Community Apps plugin wrapper.
- `smartfancontrol.plg` as the actual Unraid plugin definition.
- `icon.svg`, `README.md`, and `LICENSE` hosted from the same public repository.

The `<PluginURL>` in `plugins/smartfancontrol.xml` must exactly match the `pluginURL` attribute embedded in `smartfancontrol.plg`:

```text
https://raw.githubusercontent.com/blackboygirl/smartfancontrol/main/smartfancontrol.plg
```

## Before submitting

1. Push this repository to the `main` branch.
2. Confirm the raw PLG reports the intended version:

   ```bash
   curl -fsSL https://raw.githubusercontent.com/blackboygirl/smartfancontrol/main/smartfancontrol.plg | grep 'ENTITY version'
   ```

3. Confirm an existing Unraid installation can see the release:

   ```bash
   plugin check smartfancontrol.plg
   ```

4. Use the Community Apps submit flow (`/submit`) and run both **Validate** and **Scan** as instructed by the official starter repository.
5. Submit the public GitHub repository URL:

   ```text
   https://github.com/blackboygirl/smartfancontrol
   ```

## Beta status

`plugins/smartfancontrol.xml` currently contains `<Beta>true</Beta>` because the plugin is still being validated across different hardware. When it is ready for a stable listing, change this to `false` and remove the `(Beta)` wording from the installer output/release notes if desired.

## Versioning

Starting with this Community Apps candidate, public releases use `YYYY.MM.DD`. If more than one release is published on the same day, append a letter, for example `2026.08.29a`, then `2026.08.29b`.
