# Register Config JSON Auto-Import

## Problem

LarpingApp currently uses a dual data-source pattern with both internal Nextcloud DB mappers AND OpenRegister. The newer apps (Pipelinq, Procest) use a pure OpenRegister approach with a `_register.json` file that auto-imports all schemas and registers on app install/enable via a repair step.

LarpingApp should adopt this pattern to:
1. Simplify initial setup (no manual schema configuration needed)
2. Ensure consistent schema definitions across installations
3. Align with the ConductionNL app template convention

## Proposal

### 1. Create `larpingapp_register.json`

An OpenAPI 3.0.0 format JSON file defining all LarpingApp schemas:
- Character (schema:Person)
- Player (schema:Person)
- Ability (schema:PropertyValue)
- Skill (schema:Action)
- Item (schema:Product)
- Condition (schema:MedicalCondition)
- Effect (schema:PropertyValue)
- Event (schema:Event)
- Setting (schema:GameServer)

### 2. Add SettingsLoadService

A service that loads the register JSON via OpenRegister's `ConfigurationService::importFromApp()`.

### 3. Add InitializeSettings repair step

A post-migration repair step that auto-imports the register JSON on app install/enable.

### 4. Update SettingsService

Add `SLUG_TO_CONFIG_KEY` mapping and `autoConfigureAfterImport()` to automatically configure schema IDs from the import result.

### 5. Update routes

Add a `/api/settings/load` endpoint for manual re-import from admin settings.

## Files to Change

1. **New:** `lib/Settings/larpingapp_register.json` — Register + schema definitions
2. **New:** `lib/Repair/InitializeSettings.php` — Post-migration repair step
3. **Update:** `lib/Service/SettingsService.php` — Add loadConfiguration, autoConfigureAfterImport
4. **Update:** `lib/Controller/SettingsController.php` — Add load() endpoint
5. **Update:** `appinfo/info.xml` — Add repair step registration
6. **Update:** `appinfo/routes.php` — Add /api/settings/load route

## Risks

- The existing dual data-source pattern (internal mappers vs OpenRegister) should continue to work. The register JSON import is additive.
- Existing installations may need a manual re-import if they already have schemas configured differently.

## Acceptance Criteria

- GIVEN a fresh Nextcloud installation with OpenRegister enabled
- WHEN larpingapp is installed
- THEN all schemas are auto-imported and configured without manual setup

- GIVEN an admin on the settings page
- WHEN they click "Re-import configuration"
- THEN schemas are re-imported and the register name matches the app name
