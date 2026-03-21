---
status: implemented
---

# Register Config JSON Auto-Import

## Purpose

Automatically imports all LarpingApp schemas and registers into OpenRegister on app install/enable, eliminating manual configuration. Uses the `larpingapp_register.json` file (OpenAPI 3.0.0 format) with `ConfigurationService.importFromApp()`.

## Requirements

### Requirement: Register JSON File

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-001 | A `larpingapp_register.json` file MUST exist at `lib/Settings/` | MUST | Implemented |
| REG-002 | The file MUST define schemas for all 9 entity types | MUST | Implemented |
| REG-003 | The file MUST use OpenAPI 3.0.0 format with x-openregister extensions | MUST | Implemented |

### Requirement: Auto-Import on Boot

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-004 | Application.boot() MUST call SettingsService.loadSettings() | MUST | Implemented |
| REG-005 | SettingsLoadService MUST delegate to ConfigurationService.importFromApp() | MUST | Implemented |
| REG-006 | Import MUST be skipped silently if OpenRegister is not installed | MUST | Implemented |

### Requirement: Config File Loading

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-007 | ConfigFileLoaderService MUST load and parse the JSON file | MUST | Implemented |
| REG-008 | ConfigFileLoaderService MUST throw RuntimeException if file not found | MUST | Implemented |
| REG-009 | ConfigFileLoaderService MUST ensure x-openregister sourceType is set | MUST | Implemented |

### Requirement: Schema Mapping

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-010 | SettingsLoadService MUST update IAppConfig with imported register/schema IDs | MUST | Implemented |
| REG-011 | SettingsMapBuilder MUST build slug-to-ID mapping from import result | MUST | Implemented |

### Requirement: Re-import Endpoint

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| REG-012 | SettingsController.reimport() MUST re-import configuration on POST /api/settings/reimport | MUST | Implemented |
| REG-013 | The reimport action MUST be available from the user settings dialog | MUST | Implemented |
