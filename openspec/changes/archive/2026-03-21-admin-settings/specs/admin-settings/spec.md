---
status: enriched
---

# Admin Settings

## Purpose

Provides per-object-type data source configuration for LarpingApp, allowing administrators to choose whether each entity type (ability, character, condition, effect, event, item, player, setting, skill) is stored in the internal Nextcloud database or in an OpenRegister instance. When OpenRegister is selected, administrators configure the specific register and schema for each object type. Settings are exposed via the Nextcloud Admin Settings panel and a REST API. Additionally, provides a JSON-based configuration import mechanism via `SettingsLoadService` that bootstraps registers and schemas from a bundled configuration file.

## Requirements

---

### Requirement: Nextcloud Admin Panel Integration

The LarpingApp MUST register a dedicated section in the Nextcloud admin settings panel that renders settings via a Vue component.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-001 | LarpingApp MUST have a dedicated admin section in the Nextcloud admin settings panel | MUST | Implemented |
| SET-002 | The admin section MUST use the LarpingApp icon (`app-dark.svg`) | MUST | Implemented |
| SET-003 | The admin section MUST be registered via `LarpingAppAdmin` in `lib/Settings/` (as `<admin>` in info.xml) and `LarpingAppAdmin` IIconSection in `lib/Sections/` (as `<admin-section>` in info.xml). Note: `lib/Settings/LarpingAppAdmin.php` implements `IIconSection` rather than `ISettings`; it should implement `ISettings` to properly render the admin panel content. | MUST | Bug |
| SET-004 | Settings MUST be rendered using a Vue component (`Settings.vue`) via the `settings` entry point | MUST | Implemented |
| SET-005 | The admin section MUST have priority 55 in the settings panel ordering | MUST | Implemented |

#### Scenario: Admin opens LarpingApp settings panel

- GIVEN an administrator is logged in to Nextcloud
- WHEN they navigate to Admin Settings
- THEN a "Larping App" section MUST appear in the left sidebar
- AND it MUST use the `app-dark.svg` icon
- AND clicking it MUST render the Settings.vue component

#### Scenario: Non-admin user cannot access admin settings

- GIVEN a regular (non-admin) Nextcloud user
- WHEN they navigate to Admin Settings
- THEN the LarpingApp admin section MUST NOT be visible
- AND direct URL access to the settings page MUST be denied

#### Scenario: Settings panel rendering with IIconSection bug

- GIVEN `LarpingAppAdmin` in `lib/Settings/` implements `IIconSection` instead of `ISettings`
- WHEN the admin navigates to the LarpingApp settings section
- THEN the section tab appears in the sidebar
- BUT the settings content panel MAY not render correctly because `IIconSection` does not provide `getForm()`

---

### Requirement: Data Source Configuration

Administrators MUST be able to configure each of the 9 object types to use either internal Nextcloud database storage or OpenRegister storage with a specific register and schema.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-010 | All 9 object types MUST be listed as configurable: ability, character, condition, effect, event, item, player, setting, skill | MUST | Implemented |
| SET-011 | Each object type MUST have a source selector with options: "Internal" or "Open Register" | MUST | Implemented |
| SET-012 | When "Open Register" is selected, a register dropdown MUST appear populated from available OpenRegister registers | MUST | Implemented |
| SET-013 | When a register is selected, a schema dropdown MUST appear populated from that register's schemas | MUST | Implemented |
| SET-014 | Changing source to "Internal" MUST clear the register and schema selections | MUST | Implemented |
| SET-015 | Changing register MUST clear the schema selection | MUST | Implemented |
| SET-016 | A "Save All" button MUST persist all configuration changes in one request | MUST | Implemented |

#### Scenario: Configure character type for OpenRegister

- GIVEN OpenRegister is installed and has a register "LARP Data" with schema "Character"
- WHEN an administrator changes the Character source from "Internal" to "Open Register"
- AND selects register "LARP Data"
- AND selects schema "Character"
- AND clicks "Save All"
- THEN `character_source` MUST be set to "openregister" in IAppConfig
- AND `character_register` MUST be set to the register ID
- AND `character_schema` MUST be set to the schema ID

#### Scenario: Cascading dropdown behavior

- GIVEN an administrator has selected "Open Register" source for the Skill type
- AND has selected register "LARP Data" and schema "Skill Schema"
- WHEN the administrator changes the register to "Game Registry"
- THEN the schema dropdown MUST clear its selection
- AND MUST repopulate with schemas from "Game Registry"

#### Scenario: Switch back to internal storage

- GIVEN Character is configured to use OpenRegister with register "LARP Data" and schema "Character"
- WHEN an administrator changes the Character source to "Internal"
- THEN the register and schema selectors MUST be hidden
- AND their values MUST be cleared
- WHEN they click "Save All"
- THEN `character_source` MUST be set to "internal"
- AND `character_register` MUST be set to ""
- AND `character_schema` MUST be set to ""

#### Scenario: Save all configuration at once

- GIVEN the administrator has changed sources for 3 object types
- WHEN they click "Save All"
- THEN a single POST /api/settings request MUST be sent
- AND all 3 object types MUST be updated atomically
- AND a loading spinner MUST appear during the save operation

#### Scenario: Configure all 10 types for OpenRegister

- GIVEN OpenRegister is installed with a register containing schemas for all entity types
- WHEN the administrator selects "Open Register" for all 10 types and assigns register/schema pairs
- AND clicks "Save All"
- THEN all 30 config keys (10 types x 3 keys each) MUST be persisted
- AND subsequent CRUD operations for all types MUST use OpenRegister mappers

---

### Requirement: OpenRegister Detection

The settings UI MUST detect whether OpenRegister is installed and display appropriate warnings when it is not.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-020 | The system MUST check if the OpenRegister app is installed via `IAppManager::getInstalledApps()` | MUST | Implemented |
| SET-021 | The system MUST display a warning NcNoteCard when OpenRegister is not installed | MUST | Implemented |
| SET-022 | When OpenRegister is unavailable, register/schema selectors MUST NOT be displayed | MUST | Implemented |

#### Scenario: OpenRegister not installed

- GIVEN OpenRegister is not installed on the Nextcloud instance
- WHEN an administrator opens the LarpingApp admin settings
- THEN a warning NcNoteCard MUST be displayed with text "Open Register is not installed. Some features might be unavailable."
- AND source selectors MUST still show "Internal" and "Open Register" options
- BUT selecting "Open Register" MUST NOT show register/schema dropdowns

#### Scenario: OpenRegister installed with registers

- GIVEN OpenRegister is installed and has 2 registers with 3 schemas each
- WHEN an administrator opens the LarpingApp admin settings
- THEN no warning card MUST be displayed
- AND the register dropdown MUST list both registers when "Open Register" is selected

#### Scenario: OpenRegister installed with no registers

- GIVEN OpenRegister is installed but has no registers configured
- WHEN an administrator selects "Open Register" for a type
- THEN the register dropdown MUST appear but be empty
- AND the administrator MUST be able to save with empty register/schema

---

### Requirement: Configuration Storage

Settings MUST be persisted in Nextcloud's IAppConfig as key-value pairs following a consistent naming convention.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-030 | Source configuration MUST be stored as `{objectType}_source` in IAppConfig (value: "internal" or "openregister") | MUST | Implemented |
| SET-031 | Register selection MUST be stored as `{objectType}_register` in IAppConfig | MUST | Implemented |
| SET-032 | Schema selection MUST be stored as `{objectType}_schema` in IAppConfig | MUST | Implemented |
| SET-033 | Default source for all object types MUST be "internal" | MUST | Implemented |
| SET-034 | Default register and schema for all object types MUST be empty string | MUST | Implemented |

#### Scenario: Default configuration on fresh install

- GIVEN LarpingApp is freshly installed
- WHEN the settings API is queried via GET /api/settings
- THEN all object types MUST have source "internal"
- AND all register values MUST be ""
- AND all schema values MUST be ""

#### Scenario: Configuration persistence across restarts

- GIVEN an administrator has configured Character for OpenRegister
- WHEN the Nextcloud server restarts
- THEN the Character configuration MUST remain as "openregister" with the saved register and schema IDs

#### Scenario: SettingsService CONFIG_KEYS alignment

- GIVEN `SettingsService` defines CONFIG_KEYS as: register, character_schema, player_schema, ability_schema, skill_schema, item_schema, condition_schema, effect_schema, event_schema, setting_schema
- WHEN `getSettings()` is called
- THEN only these 10 keys MUST be returned from IAppConfig
- AND this simplified key structure (single register, per-type schemas) represents a newer configuration model

---

### Requirement: Settings API

The system MUST expose REST endpoints for reading and updating settings.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-040 | The system MUST retrieve all settings via `GET /api/settings` | MUST | Implemented |
| SET-041 | The system MUST update settings via `POST /api/settings` | MUST | Implemented |
| SET-042 | Settings response MUST include: `objectTypes` array, `openRegisters` boolean, `isAdmin` boolean, and `configuration` object | MUST | Implemented |
| SET-043 | The `configuration` object MUST contain the CONFIG_KEYS values (register, per-type schemas) | MUST | Implemented |

#### Scenario: GET settings returns full state

- GIVEN OpenRegister is installed and Character is configured for OpenRegister
- WHEN an admin calls GET /api/settings
- THEN the response MUST contain `objectTypes` listing all 10 types
- AND `openRegisters` MUST be true
- AND `isAdmin` MUST be true
- AND `configuration` MUST contain the current register and schema values

#### Scenario: POST settings updates configuration

- GIVEN the current configuration has Character using internal storage
- WHEN an admin POSTs `{"character_schema": "schema-uuid-123"}`
- THEN `character_schema` MUST be updated in IAppConfig
- AND the response MUST contain the updated configuration

#### Scenario: POST settings with unknown keys

- GIVEN an admin POSTs `{"malicious_key": "evil", "character_schema": "valid-id"}`
- WHEN `updateSettings()` processes the request
- THEN only `character_schema` MUST be stored (it matches CONFIG_KEYS)
- AND `malicious_key` MUST be silently ignored

---

### Requirement: Settings API Security

Both settings endpoints MUST be restricted to admin users.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-050 | `SettingsController.index()` (GET) MUST be admin-only (no `@NoAdminRequired` annotation) | MUST | Implemented |
| SET-051 | `SettingsController.create()` (POST) MUST be admin-only (no `@NoAdminRequired` annotation) | MUST | Implemented |
| SET-052 | Both endpoints MUST have `@NoCSRFRequired` for API compatibility | MUST | Implemented |
| SET-053 | `SettingsController.reimport()` (POST /api/settings/reimport) MUST be admin-only and trigger forced re-import of configuration from JSON | MUST | Implemented |

#### Scenario: Non-admin user attempts to read settings

- GIVEN a regular user (not in admin group)
- WHEN they call GET /api/settings
- THEN a 403 Forbidden response MUST be returned
- AND no settings data MUST be exposed

#### Scenario: Admin user triggers re-import

- GIVEN the admin clicks the re-import button
- WHEN POST /api/settings/reimport is called
- THEN `SettingsService.loadSettings(force: true)` MUST be invoked
- AND the response MUST include counts of registers and schemas imported
- AND the configuration MUST be refreshed from the JSON file

#### Scenario: CSRF token not required

- GIVEN an API client calls GET /api/settings without a CSRF token
- WHEN the request reaches the controller
- THEN the request MUST succeed because `@NoCSRFRequired` is set
- AND the settings MUST be returned normally

---

### Requirement: Configuration Import via JSON

The system MUST support bootstrapping register and schema configuration from a bundled JSON file via `SettingsLoadService`.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-060 | `SettingsLoadService` MUST load configuration from the bundled JSON file | MUST | Implemented |
| SET-061 | Import MUST create registers and schemas in OpenRegister based on the JSON definition | MUST | Implemented |
| SET-062 | Import MUST persist the created register and schema IDs in IAppConfig | MUST | Implemented |
| SET-063 | Import MUST support a `force` flag to re-import even when configuration already exists | MUST | Implemented |

#### Scenario: First-time configuration import

- GIVEN LarpingApp is freshly installed with OpenRegister available
- AND no register or schema configuration exists
- WHEN `loadSettings()` is triggered (e.g., via the re-import button)
- THEN the JSON config file MUST be read from the app bundle
- AND registers and schemas MUST be created in OpenRegister
- AND the IAppConfig MUST be populated with the created IDs

#### Scenario: Force re-import overwriting existing configuration

- GIVEN configuration already exists from a previous import
- WHEN `loadSettings(force: true)` is called
- THEN the existing configuration MUST be overwritten
- AND new registers/schemas MUST be created
- AND the response MUST include counts of registers and schemas created

#### Scenario: Import fails when OpenRegister unavailable

- GIVEN OpenRegister is not installed
- WHEN `loadSettings()` is triggered
- THEN the import MUST throw an exception
- AND the settings MUST remain unchanged

---

### Requirement: RegisterObjectFetcher Data Source Dispatch

The `RegisterObjectFetcher` MUST read per-type configuration from IAppConfig to obtain the correct OpenRegister mapper.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-070 | `RegisterObjectFetcher.getMapper()` MUST read `{objectType}_register` and `{objectType}_schema` from IAppConfig | MUST | Implemented |
| SET-071 | The mapper MUST be obtained from OpenRegister's ObjectService using the configured register and schema | MUST | Implemented |
| SET-072 | An exception MUST be thrown if register is not configured for the requested type | MUST | Implemented |
| SET-073 | An exception MUST be thrown if schema is not configured for the requested type | MUST | Implemented |
| SET-074 | The OpenRegister ObjectService MUST be cached after first retrieval | MUST | Implemented |

#### Scenario: Mapper dispatch for configured type

- GIVEN `character_register` is set to "reg-123" and `character_schema` is set to "sch-456"
- WHEN `RegisterObjectFetcher.getMapper('character')` is called
- THEN it MUST obtain the OpenRegister ObjectService from the DI container
- AND call `$openRegister->getMapper('reg-123', 'sch-456')`
- AND return the resulting mapper

#### Scenario: Mapper dispatch for unconfigured type

- GIVEN `ability_register` is empty in IAppConfig
- WHEN `RegisterObjectFetcher.getMapper('ability')` is called
- THEN it MUST throw an Exception with message "Register not configured for ability"

#### Scenario: OpenRegister service caching

- GIVEN the first call to `getOpenRegisterService()` succeeds
- WHEN a second call is made
- THEN the cached instance MUST be returned without resolving from DI again

---

### Requirement: Database Schema

Database migrations MUST create tables for all entity types used in internal storage mode.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-080 | Migration `Version0Date20240826193657` MUST create 9 tables: `larpingapp_abilities`, `larpingapp_conditions`, `larpingapp_effects`, `larpingapp_events`, `larpingapp_items`, `larpingapp_players`, `larpingapp_settings`, `larpingapp_skills`, `larpingapp_templates` | MUST | Implemented |
| SET-081 | The `larpingapp_characters` table MUST exist for internal character storage but is NOT created by any migration -- `CharacterMapper` references this table but it does not exist in the database schema | MUST | Bug |
| SET-082 | Migration `Version0Date20241015141612` MUST add `base` (INTEGER, default 0) and `allowed_negative` (BOOLEAN, default false) columns to `larpingapp_abilities` | MUST | Implemented |
| SET-083 | All tables MUST use auto-incrementing integer primary keys (`id`) | MUST | Implemented |

#### Scenario: Fresh install creates all tables

- GIVEN LarpingApp is being installed for the first time
- WHEN the migration `Version0Date20240826193657` runs
- THEN 9 tables MUST be created with the correct columns
- AND the abilities table MUST NOT yet have `base` or `allowed_negative` columns

#### Scenario: Ability table migration adds columns

- GIVEN the initial migration has already run
- WHEN migration `Version0Date20241015141612` runs
- THEN `larpingapp_abilities` MUST gain `base` (INTEGER default 0) and `allowed_negative` (BOOLEAN default false)

#### Scenario: Character internal storage fails due to missing table

- GIVEN character type is configured for internal storage
- WHEN a CRUD operation is attempted on a character
- THEN the operation MUST fail because `larpingapp_characters` table does not exist
- AND a database error MUST be thrown

---

### Requirement: App Metadata

The app MUST declare correct metadata in `info.xml` for Nextcloud compatibility.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-090 | App version MUST be defined in `appinfo/info.xml` | MUST | Implemented |
| SET-091 | App license MUST be `EUPL-1.2` (European Union Public License) | MUST | Implemented |
| SET-092 | Supported Nextcloud versions MUST include 28 through 30 | MUST | Implemented |
| SET-093 | Supported databases MUST include PostgreSQL (min 10), SQLite, MySQL (min 8.0) | MUST | Implemented |
| SET-094 | Required PHP version MUST be 8.0+ with 64-bit integer support | MUST | Implemented |
| SET-095 | App namespace MUST be `LarpingApp` | MUST | Implemented |
| SET-096 | Navigation entry MUST route to `larpingapp.dashboard.page` with icon `app.svg` and label "Larping" | MUST | Implemented |

#### Scenario: App installs on supported Nextcloud version

- GIVEN a Nextcloud 29 instance
- WHEN an administrator installs LarpingApp
- THEN the app MUST install successfully
- AND the "Larping" navigation item MUST appear in the top bar

#### Scenario: App rejected on unsupported Nextcloud version

- GIVEN a Nextcloud 27 instance
- WHEN an administrator attempts to install LarpingApp
- THEN the installation MUST be rejected due to `min-version="28"` constraint

#### Scenario: App metadata displayed in app store

- GIVEN LarpingApp is listed in the Nextcloud app store
- THEN the license MUST show as EUPL-1.2
- AND the category MUST show as "organization"
- AND supported databases MUST list PostgreSQL, SQLite, and MySQL

---

## Data Model

### Settings Configuration Keys (SettingsService.CONFIG_KEYS)

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `register` | string | "" | Shared OpenRegister register ID |
| `character_schema` | string | "" | OpenRegister schema ID for characters |
| `player_schema` | string | "" | OpenRegister schema ID for players |
| `ability_schema` | string | "" | OpenRegister schema ID for abilities |
| `skill_schema` | string | "" | OpenRegister schema ID for skills |
| `item_schema` | string | "" | OpenRegister schema ID for items |
| `condition_schema` | string | "" | OpenRegister schema ID for conditions |
| `effect_schema` | string | "" | OpenRegister schema ID for effects |
| `event_schema` | string | "" | OpenRegister schema ID for events |
| `setting_schema` | string | "" | OpenRegister schema ID for settings |

### Settings API Response Structure

```json
{
  "objectTypes": ["ability", "character", "condition", "effect", "event", "item", "player", "setting", "skill"],
  "openRegisters": true,
  "isAdmin": true,
  "configuration": {
    "register": "1",
    "character_schema": "1",
    "player_schema": "2",
    "ability_schema": "3",
    "skill_schema": "4",
    "item_schema": "5",
    "condition_schema": "6",
    "effect_schema": "7",
    "event_schema": "8",
    "setting_schema": "9"
  }
}
```

### Database Tables (from migrations)

| Table Name | Created In | Columns |
|------------|-----------|---------|
| `larpingapp_abilities` | Version0Date20240826193657 | id, name, description; +base, allowed_negative (Version0Date20241015141612) |
| `larpingapp_conditions` | Version0Date20240826193657 | id, name, description |
| `larpingapp_effects` | Version0Date20240826193657 | id, name, description |
| `larpingapp_events` | Version0Date20240826193657 | id, title, description, start_date, end_date, user_id |
| `larpingapp_items` | Version0Date20240826193657 | id, name, description |
| `larpingapp_players` | Version0Date20240826193657 | id, name, description |
| `larpingapp_settings` | Version0Date20240826193657 | id, name, value |
| `larpingapp_skills` | Version0Date20240826193657 | id, name, description |
| `larpingapp_templates` | Version0Date20240826193657 | id, name, description |
| `larpingapp_characters` | **MISSING** | CharacterMapper references this table, but no migration creates it |

## User Interface

### Admin Settings Page (`Settings.vue`)

The settings page is divided into two `NcSettingsSection` blocks:

1. **LarpingApp header**: App name ("Larping App") with description and documentation link to `https://conduction.gitbook.io/larpingapp-nextcloud/users`

2. **Data Storage section**: Per-object-type configuration
   - Warning `NcNoteCard` when OpenRegister is not installed
   - For each object type (alphabetically: ability through template):
     - Capitalized object type name as section header
     - Source selector (`NcSelect`): "Internal" or "Open Register"
     - Register selector (shown only when OpenRegister source selected): populated from available registers
     - Schema selector (shown only when register is selected): populated from schemas
   - Save All button with loading spinner during save
   - Re-import button for forcing configuration reload from JSON

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/settings` | Retrieve all settings including object types, OpenRegister availability, admin status, and current configuration |
| POST | `/api/settings` | Update settings (accepts CONFIG_KEYS key-value pairs) |
| POST | `/api/settings/reimport` | Force re-import of configuration from bundled JSON file |

Note: All endpoints are admin-only (no `@NoAdminRequired` annotation). All endpoints have `@NoCSRFRequired`.

## Dependencies

- **Nextcloud IAppConfig**: Key-value configuration storage for register/schema per object type
- **Nextcloud ISettings / IIconSection**: Admin panel integration (`lib/Settings/LarpingAppAdmin.php`, `lib/Sections/LarpingAppAdmin.php`)
- **IAppManager**: Checking if OpenRegister app is installed
- **RegisterObjectFetcher**: Resolves register/schema from config to obtain OpenRegister mappers
- **SettingsService**: Business logic for reading/writing CONFIG_KEYS
- **SettingsLoadService**: JSON-based configuration import from bundled file
- **SettingsController**: API endpoints for reading, writing, and reimporting settings
- **Vue Settings.vue**: Admin UI component with NcSettingsSection, NcSelect, NcButton, NcNoteCard
