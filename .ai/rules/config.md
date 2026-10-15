---
paths:
  - 'config/**'
---

# Config

## Admin settings, not .env
Anything an admin may want to change (API keys, OAuth apps, AI services and models, e-mail, brand, limits, storage) is a property on a spatie settings class in `App\Admin\Settings` with `#[ConfigKey]`, not an `.env` value. Code reads `config()`; `SettingsConfig` copies the settings in. `.env` is only for bootstrap: APP_KEY, database, Redis, drivers, Reverb, `VITE_*`, embedding storage, the Bunny CDN `assets` disk, `FLARE_KEY`, `BUILTIN_MCP_*`, `TRUSTED_PROXIES`, `CHATVIA_URI`.

## Read env() inline
In `config/*.php`, call `env()` inline for each value: no local variables and no `?:` or `rtrim()` wrapping. Code anywhere else reads `config()`, never `env()`.

## Keys and file names
Config keys are snake_case and kept flat. Multi-word app config files are kebab-case (`team-permissions.php`).
