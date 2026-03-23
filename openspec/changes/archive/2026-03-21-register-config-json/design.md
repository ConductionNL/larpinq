---
status: approved
---

# Register Config JSON Auto-Import — Design

## Architecture

The auto-import system uses a service chain:
1. `Application.boot()` → `SettingsService.loadSettings()` → `SettingsLoadService.loadSettings()`
2. `ConfigFileLoaderService.loadConfigurationFile()` reads and parses the JSON
3. `SettingsLoadService` delegates to OpenRegister's `ConfigurationService.importFromApp()`
4. `SettingsMapBuilder` maps imported schema slugs to IDs for IAppConfig

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| Config | `lib/Settings/larpingapp_register.json` | Schema definitions (OpenAPI 3.0.0) |
| Service | `ConfigFileLoaderService` | File loading and JSON parsing |
| Service | `SettingsLoadService` | Import orchestration |
| Service | `SettingsMapBuilder` | Slug-to-ID mapping |
| Service | `SettingsService` | Public API, delegates to load service |
| Controller | `SettingsController.reimport()` | Manual re-import endpoint |
| Bootstrap | `Application.boot()` | Auto-import on app enable |

## Version-Aware Import

The import uses `IAppManager.getAppVersion()` to pass the current app version to `importFromApp()`. This allows OpenRegister to skip re-import if the version hasn't changed (unless `force=true`).
