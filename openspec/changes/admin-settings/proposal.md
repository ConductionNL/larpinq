# Admin Settings

## Problem
Provides per-object-type data source configuration for LarpingApp, allowing administrators to choose whether each entity type (ability, character, condition, effect, event, item, player, setting, skill, template) is stored in the internal Nextcloud database or in an OpenRegister instance. When OpenRegister is selected, administrators configure the specific register and schema for each object type. Settings are exposed via the Nextcloud Admin Settings panel and a REST API. Additionally, provides a JSON-based configuration import mechanism via `SettingsLoadService` that bootstraps registers and schemas from a bundled configuration file.

## Proposed Solution
Implement Admin Settings following the detailed specification. Key requirements include:
- Requirement: Nextcloud Admin Panel Integration
- Requirement: Data Source Configuration
- Requirement: OpenRegister Detection
- Requirement: Configuration Storage
- Requirement: Settings API

## Scope
This change covers all requirements defined in the admin-settings specification.

## Success Criteria
- Admin opens LarpingApp settings panel
- Non-admin user cannot access admin settings
- Settings panel rendering with IIconSection bug
- Configure character type for OpenRegister
- Cascading dropdown behavior
