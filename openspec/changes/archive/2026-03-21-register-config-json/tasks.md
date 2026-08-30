---
status: done
---

# Register Config JSON Auto-Import — Tasks

- [x] Create larpingapp_register.json with all 9 entity schemas
- [x] Implement ConfigFileLoaderService for JSON file loading
- [x] Implement SettingsLoadService for import orchestration
- [x] Implement SettingsMapBuilder for slug-to-ID mapping
- [x] Implement SettingsService.loadSettings() delegation
- [x] Implement Application.boot() auto-import call
- [x] Implement SettingsController.reimport() endpoint
- [x] Add /api/settings/reimport route in routes.php
- [x] Unit tests: ConfigFileLoaderServiceTest, SettingsLoadServiceTest (ADR-009)
- [x] Feature documentation: docs/features/register-config-json.md (ADR-010)
- [x] i18n: Verify reimport UI strings use t() function (ADR-005)
