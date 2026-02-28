# Admin Settings

## Purpose

Provides per-object-type data source configuration for LarpingApp, allowing administrators to choose whether each entity type (ability, character, condition, effect, event, item, player, setting, skill, template) is stored in the internal Nextcloud database or in an OpenRegister instance. When OpenRegister is selected, administrators configure the specific register and schema for each object type. Settings are exposed via the Nextcloud Admin Settings panel and a REST API.

## Requirements

### Nextcloud Admin Panel Integration

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-001 | LarpingApp has a dedicated admin section in the Nextcloud admin settings panel | MUST | Implemented |
| SET-002 | The admin section uses the LarpingApp icon (`app-dark.svg`) | MUST | Implemented |
| SET-003 | The admin section is registered via `LarpingAppAdmin` ISettings (in `lib/Settings/`) and `LarpingAppAdmin` IIconSection (in `lib/Sections/`) | MUST | Implemented |
| SET-004 | Settings are rendered using a Vue component (`Settings.vue`) via the `settings` entry point | MUST | Implemented |
| SET-005 | The admin section has priority 55 in the settings panel ordering | MUST | Implemented |

### Data Source Configuration

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-010 | All 10 object types are listed as configurable: ability, character, condition, effect, event, item, player, setting, skill, template | MUST | Implemented |
| SET-011 | Each object type has a source selector with options: "Internal" or "Open Register" | MUST | Implemented |
| SET-012 | When "Open Register" is selected, a register dropdown appears populated from available OpenRegister registers | MUST | Implemented |
| SET-013 | When a register is selected, a schema dropdown appears populated from that register's schemas | MUST | Implemented |
| SET-014 | Changing source to "Internal" clears the register and schema selections | MUST | Implemented |
| SET-015 | Changing register clears the schema selection | MUST | Implemented |
| SET-016 | A "Save All" button persists all configuration changes in one request | MUST | Implemented |

### OpenRegister Detection

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-020 | Check if the OpenRegister app is installed via `IAppManager::getInstalledApps()` | MUST | Implemented |
| SET-021 | Display a warning NcNoteCard when OpenRegister is not installed | MUST | Implemented |
| SET-022 | When OpenRegister is unavailable, register/schema selectors are not displayed | MUST | Implemented |

### Configuration Storage

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-030 | Source configuration is stored as `{objectType}_source` in IAppConfig (value: "internal" or "openregister") | MUST | Implemented |
| SET-031 | Register selection is stored as `{objectType}_register` in IAppConfig | MUST | Implemented |
| SET-032 | Schema selection is stored as `{objectType}_schema` in IAppConfig | MUST | Implemented |
| SET-033 | Default source for all object types is "internal" | MUST | Implemented |
| SET-034 | Default register and schema for all object types is empty string | MUST | Implemented |

### Settings API

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-040 | Retrieve all settings via `GET /api/settings` | MUST | Implemented |
| SET-041 | Update settings via `POST /api/settings` | MUST | Implemented |
| SET-042 | Settings response includes: `objectTypes` array, `openRegisters` boolean, `availableRegisters` list, and `configuration` object | MUST | Implemented |
| SET-043 | The `configuration` object contains `{type}_source`, `{type}_register`, `{type}_schema` for each object type | MUST | Implemented |

### Settings API Security

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-050 | `SettingsController.index()` (GET) requires CSRF but NOT admin rights -- uses `@NoCSRFRequired` only, no `@NoAdminRequired` annotation | MUST | Implemented |
| SET-051 | `SettingsController.create()` (POST) requires CSRF but NOT admin rights -- uses `@NoCSRFRequired` only, no `@NoAdminRequired` annotation | MUST | Implemented |
| SET-052 | Because neither endpoint has `@NoAdminRequired`, both settings endpoints are admin-only by default (Nextcloud requires admin for routes without `@NoAdminRequired`) | MUST | Implemented |
| SET-053 | `SettingsController.create()` accepts ALL request parameters without validation or whitelisting -- any key-value pair sent in the POST body is stored in IAppConfig for the app | MUST | Bug |
| SET-054 | There is no validation that the keys being set are valid configuration keys (e.g., `{type}_source`, `{type}_register`, `{type}_schema`). An admin could inject arbitrary app config values | SHOULD | Bug |
| SET-055 | There is no validation that `_source` values are limited to "internal" or "openregister" | SHOULD | Bug |

### ObjectService Data Source Dispatch

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-060 | ObjectService.getMapper() reads `{objectType}_source` from IAppConfig to determine data source | MUST | Implemented |
| SET-061 | When source is "openregister", the mapper is obtained from OpenRegister using configured register and schema | MUST | Implemented |
| SET-062 | When source is "internal", the appropriate Nextcloud Entity Mapper is returned via a match statement | MUST | Implemented |
| SET-063 | An exception is thrown if OpenRegister source is configured but the service is unavailable | MUST | Implemented |
| SET-064 | An exception is thrown if register or schema is not configured when using OpenRegister source | MUST | Implemented |

### ObjectService Constructor Bugs

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-070 | The ObjectService constructor type-hints `IContainer` for the `$container` parameter, but `IContainer` is a deprecated Nextcloud interface -- the correct type is `Psr\Container\ContainerInterface` | MUST | Bug |
| SET-071 | The ObjectService constructor type-hints `IConfig` for the `$config` parameter, but the code calls `getValueString()` which is an `IAppConfig` method, not `IConfig`. The import statement says `use OCP\IAppConfig;` but the constructor signature says `private IConfig $config` | MUST | Bug |
| SET-072 | Despite these type-hint bugs, Nextcloud's DI auto-wiring may inject the correct implementations at runtime, so the bugs may not cause immediate runtime failures | SHOULD | Bug |

### Mapper findAll() Signature Inconsistencies

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-080 | `AbilityMapper.findAll(string $userId)` -- requires a userId parameter, no limit/offset/filters support | MUST | Bug |
| SET-081 | `CharacterMapper.findAll(string $userId)` -- requires a userId parameter, no limit/offset/filters support | MUST | Bug |
| SET-082 | `EffectMapper.findAll(string $userId)` -- requires a userId parameter, no limit/offset/filters support | MUST | Bug |
| SET-083 | `EventMapper.findAll(?int $limit, ?int $offset, ?array $filters, ?array $searchConditions, ?array $searchParams)` -- supports limit/offset/filters but different param names than what ObjectService passes | MUST | Bug |
| SET-084 | `SkillMapper.findAll(?int $limit, ?int $offset, ?array $filters, ?array $searchConditions, ?array $searchParams)` -- same as EventMapper | MUST | Bug |
| SET-085 | `ItemMapper.findAll()` -- takes NO parameters at all, returns all items | MUST | Bug |
| SET-086 | `ObjectService.getObjects()` calls `findAll()` with named parameters `limit`, `offset`, `filters`, `sort`, `search`, `extend` -- this signature is incompatible with ALL internal mappers. None accept `sort`, `search`, or `extend` parameters; some require `userId` which ObjectService does not pass | MUST | Bug |

### Application Class

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-090 | `Application` class (`lib/AppInfo/Application.php`) implements `IBootstrap` with empty `register()` and `boot()` methods | MUST | Implemented |
| SET-091 | The empty `register()` and `boot()` methods mean no services, event listeners, middleware, or other components are registered during app bootstrap | SHOULD | Implemented |
| SET-092 | `Application::APP_ID` is defined as `'larpingapp'` | MUST | Implemented |

### Database Schema

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-100 | Migration `Version0Date20240826193657` creates 9 tables: `larpingapp_abilities`, `larpingapp_conditions`, `larpingapp_effects`, `larpingapp_events`, `larpingapp_items`, `larpingapp_players`, `larpingapp_settings`, `larpingapp_skills`, `larpingapp_templates` | MUST | Implemented |
| SET-101 | The `larpingapp_characters` table is NOT created by any migration -- `CharacterMapper` references this table but it does not exist in the database schema | MUST | Bug |
| SET-102 | Migration `Version0Date20241015141612` adds `base` (INTEGER, default 0) and `allowed_negative` (BOOLEAN, default false) columns to the `larpingapp_abilities` table | MUST | Implemented |
| SET-103 | All tables use auto-incrementing integer primary keys (`id`) | MUST | Implemented |

### App Metadata

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SET-110 | App version is `0.1.20` (from `appinfo/info.xml`) | MUST | Implemented |
| SET-111 | App license is `EUPL-1.2` (European Union Public License) | MUST | Implemented |
| SET-112 | Supported Nextcloud versions: 28 through 30 (`min-version="28" max-version="30"`) | MUST | Implemented |
| SET-113 | Supported databases: PostgreSQL (min 10), SQLite, MySQL (min 8.0) | MUST | Implemented |
| SET-114 | Required PHP version: 8.0+ with 64-bit integer support | MUST | Implemented |
| SET-115 | App category: `organization` | MUST | Implemented |
| SET-116 | App namespace: `LarpingApp` | MUST | Implemented |
| SET-117 | Admin settings registered in info.xml: `OCA\LarpingApp\Settings\LarpingAppAdmin` (admin panel) and `OCA\LarpingApp\Sections\LarpingAppAdmin` (admin section) | MUST | Implemented |
| SET-118 | Navigation entry: route `larpingapp.dashboard.page`, icon `app.svg`, label "Larping" | MUST | Implemented |

## Data Model

### Settings Configuration Keys

For each of the 10 object types (`ability`, `character`, `condition`, `effect`, `event`, `item`, `player`, `setting`, `skill`, `template`):

| Key Pattern | Type | Default | Description |
|-------------|------|---------|-------------|
| `{type}_source` | string | "internal" | Data source: "internal" or "openregister" |
| `{type}_register` | string | "" | OpenRegister register ID |
| `{type}_schema` | string | "" | OpenRegister schema ID |

### Settings API Response Structure

```json
{
  "objectTypes": ["ability", "character", "condition", "effect", "event", "item", "player", "setting", "skill", "template"],
  "openRegisters": true,
  "availableRegisters": [
    {
      "id": 1,
      "title": "LARP Register",
      "schemas": [
        { "id": 1, "title": "Character Schema" },
        { "id": 2, "title": "Skill Schema" }
      ]
    }
  ],
  "configuration": {
    "ability_source": "internal",
    "ability_register": "",
    "ability_schema": "",
    "character_source": "openregister",
    "character_register": "1",
    "character_schema": "1"
  }
}
```

### Setting Entity (Internal)

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | integer | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Setting key name |
| value | string | Yes | "" | Setting value |

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
     - Capitalized object type name as section header (e.g., "Character", "Skill")
     - Source selector (`NcSelect`): "Internal" or "Open Register"
     - Register selector (`NcSelect`, shown only when OpenRegister source selected): populated from `availableRegisters`
     - Schema selector (`NcSelect`, shown only when register is selected): populated from the selected register's schemas
   - Save All button with loading spinner during save

### Component Details

- Source options are hardcoded: `[{ label: 'Internal', value: 'internal' }, { label: 'Open Register', value: 'openregister' }]`
- Register options are derived from `settings.availableRegisters` mapping `id` to string and `title` to label
- Schema options are derived from the selected register's `schemas` array
- Settings are loaded on component creation via `GET /api/settings`
- Save performs `POST /api/settings` with flattened `{type}_source`, `{type}_register`, `{type}_schema` key-value pairs

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/settings` | Retrieve all settings including object types, OpenRegister availability, available registers, and current configuration |
| POST | `/api/settings` | Update settings (accepts flat key-value pairs) |

Note: Both endpoints are admin-only (no `@NoAdminRequired` annotation). The GET endpoint has `@NoCSRFRequired`. The POST endpoint also has `@NoCSRFRequired`.

## Scenarios

### Initial Settings Load

```
GIVEN an administrator opens the LarpingApp admin settings
WHEN the page loads
THEN GET /api/settings is called
AND the response populates the object type list, OpenRegister availability, available registers, and current configuration
AND each object type shows its configured source (defaulting to "Internal")
```

### Configure a Type for OpenRegister

```
GIVEN OpenRegister is installed and has a register "LARP Data" with schema "Character"
WHEN an administrator changes the Character source from "Internal" to "Open Register"
AND selects register "LARP Data"
AND selects schema "Character"
AND clicks "Save All"
THEN character_source is set to "openregister"
AND character_register is set to the register ID
AND character_schema is set to the schema ID
AND subsequent character CRUD operations use the OpenRegister mapper
```

### Switch Back to Internal

```
GIVEN Character is configured to use OpenRegister
WHEN an administrator changes the Character source to "Internal"
THEN the register and schema selectors are hidden
AND their values are cleared
WHEN they click "Save All"
THEN character_source is set to "internal"
AND subsequent character CRUD operations use the internal CharacterMapper
```

### OpenRegister Not Available

```
GIVEN OpenRegister is not installed
WHEN an administrator opens the LarpingApp admin settings
THEN a warning NcNoteCard is displayed: "Open Register is not installed. Some features might be unavailable."
AND the source selector still shows but selecting "Open Register" will not show register/schema dropdowns
```

### ObjectService Mapper Dispatch

```
GIVEN character_source is set to "openregister" in IAppConfig
AND character_register and character_schema are configured
WHEN ObjectService.getMapper("character") is called
THEN it reads the source configuration
AND obtains a mapper from OpenRegister using the configured register and schema
AND returns the OpenRegister mapper for character operations
```

### Arbitrary Parameter Injection (Bug Scenario)

```
GIVEN an admin user has access to the settings API
WHEN they POST to /api/settings with {"malicious_key": "malicious_value", "ability_source": "internal"}
THEN BOTH keys are stored in IAppConfig because create() iterates all params without filtering
AND "malicious_key" is stored as an app config value for larpingapp
```

## Dependencies

- **Nextcloud IAppConfig**: Key-value configuration storage for source/register/schema per object type
- **Nextcloud ISettings / IIconSection**: Admin panel integration (`lib/Settings/LarpingAppAdmin.php`, `lib/Sections/LarpingAppAdmin.php`)
- **IAppManager**: Checking if OpenRegister app is installed
- **OpenRegister ObjectService**: Obtaining mappers for configured register/schema combinations, listing available registers
- **ObjectService**: Central dispatcher that reads configuration to select internal or OpenRegister mappers
- **SettingsController**: API endpoints for reading and writing settings
- **Vue Settings.vue**: Admin UI component with NcSettingsSection, NcSelect, NcButton, NcNoteCard
