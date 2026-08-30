---
status: done
---

# Register Config JSON Auto-Import

## Purpose

@e2e exclude pure-backend config-import spec — JSON auto-import runs in Application::boot() via PHP; ConfigFileLoaderService/SettingsLoadService/SettingsMapBuilder logic is covered by PHPUnit; no browser-navigable UI surface

Automatically imports all Larpinq schemas and registers into OpenRegister on app install/enable, eliminating manual configuration. Uses the `larpinq_register.json` file (OpenAPI 3.0.0 format) with `ConfigurationService.importFromApp()`.

## Requirements

### Requirement: Register JSON File

A bundled `larpinq_register.json` file MUST define the schemas for all 9 entity types in OpenAPI 3.0.0 format with x-openregister extensions.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-001 | A `larpinq_register.json` file MUST exist at `lib/Settings/` | MUST | Implemented |
| REG-002 | The file MUST define schemas for all 9 entity types | MUST | Implemented |
| REG-003 | The file MUST use OpenAPI 3.0.0 format with x-openregister extensions | MUST | Implemented |

#### Scenario: Bundled register file defines all schemas

- GIVEN the app ships `lib/Settings/larpinq_register.json`
- WHEN the file is parsed
- THEN it MUST be valid OpenAPI 3.0.0 with x-openregister extensions
- AND it MUST define schemas for all 9 entity types

### Requirement: Auto-Import on Boot

The application MUST trigger configuration import on boot, delegating to OpenRegister's `importFromApp()`, and MUST skip silently when OpenRegister is not installed.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-004 | Application.boot() MUST call SettingsService.loadSettings() | MUST | Implemented |
| REG-005 | SettingsLoadService MUST delegate to ConfigurationService.importFromApp() | MUST | Implemented |
| REG-006 | Import MUST be skipped silently if OpenRegister is not installed | MUST | Implemented |

#### Scenario: Import runs on boot when OpenRegister is present

- GIVEN OpenRegister is installed
- WHEN `Application.boot()` runs and calls `SettingsService.loadSettings()`
- THEN `SettingsLoadService` MUST delegate to `ConfigurationService.importFromApp()`
- AND WHEN OpenRegister is not installed
- THEN the import MUST be skipped silently without error

### Requirement: Config File Loading

`ConfigFileLoaderService` MUST load and parse the bundled JSON file, throwing a `RuntimeException` when the file is absent and ensuring the x-openregister sourceType is set.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-007 | ConfigFileLoaderService MUST load and parse the JSON file | MUST | Implemented |
| REG-008 | ConfigFileLoaderService MUST throw RuntimeException if file not found | MUST | Implemented |
| REG-009 | ConfigFileLoaderService MUST ensure x-openregister sourceType is set | MUST | Implemented |

#### Scenario: Config file is loaded and validated

- GIVEN the bundled register JSON exists
- WHEN `ConfigFileLoaderService` loads it
- THEN it MUST parse the JSON and ensure the x-openregister `sourceType` is set
- AND WHEN the file is missing
- THEN it MUST throw a `RuntimeException`

### Requirement: Schema Mapping

After import, the loaded register/schema IDs MUST be persisted to IAppConfig and a slug-to-ID mapping MUST be built from the import result.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-010 | SettingsLoadService MUST update IAppConfig with imported register/schema IDs | MUST | Implemented |
| REG-011 | SettingsMapBuilder MUST build slug-to-ID mapping from import result | MUST | Implemented |

#### Scenario: Imported IDs are mapped and persisted

- GIVEN a successful import returns register and schema IDs
- WHEN `SettingsLoadService` processes the result
- THEN it MUST update IAppConfig with the imported register/schema IDs
- AND `SettingsMapBuilder` MUST build a slug-to-ID mapping from the import result

### Requirement: Re-import Endpoint

The system MUST expose a re-import action that re-runs configuration import on `POST /api/settings/reimport`, reachable from the user settings dialog.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-012 | SettingsController.reimport() MUST re-import configuration on POST /api/settings/reimport | MUST | Implemented |
| REG-013 | The reimport action MUST be available from the user settings dialog | MUST | Implemented |

#### Scenario: Re-import endpoint re-runs configuration import

- GIVEN an admin triggers re-import from the user settings dialog
- WHEN a `POST /api/settings/reimport` request is handled by `SettingsController.reimport()`
- THEN the configuration MUST be re-imported from the bundled register file
