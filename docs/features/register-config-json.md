# Register Config JSON Auto-Import

## Overview

Automatically imports all LarpingApp schemas and registers into OpenRegister on app install/enable, eliminating manual configuration.

## Features

- **Auto-import on boot**: `Application.boot()` calls `SettingsService.loadSettings()` which imports schemas
- **Version-aware**: Skips re-import if app version hasn't changed (unless forced)
- **Manual re-import**: Available from Settings dialog or via POST `/api/settings/reimport`
- **Schema mapping**: Automatically configures IAppConfig with imported register/schema IDs

## Configuration File

`lib/Settings/larpingapp_register.json` — OpenAPI 3.0.0 format defining 9 entity schemas:
- Character, Player, Ability, Skill, Item, Condition, Effect, Event, Setting

## Service Chain

1. `Application.boot()` -> `SettingsService.loadSettings()`
2. `SettingsLoadService.loadSettings()` -> `ConfigFileLoaderService.loadConfigurationFile()`
3. `ConfigurationService.importFromApp()` (OpenRegister)
4. `SettingsLoadService.updateObjectTypeConfiguration()` updates IAppConfig

## Technical Details

- Config loader: `ConfigFileLoaderService` — file reading and JSON parsing
- Load service: `SettingsLoadService` — import orchestration
- Map builder: `SettingsMapBuilder` — slug-to-ID mapping
- Settings service: `SettingsService` — public API
