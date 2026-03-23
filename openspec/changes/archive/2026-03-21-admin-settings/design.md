# Design: Admin Settings

## Context

LarpingApp's admin settings are largely implemented. The settings panel allows per-object-type data source configuration (Internal vs OpenRegister) with cascading dropdowns. The main outstanding issues are:

1. **ISettings bug (SET-003)** — `lib/Settings/LarpingAppAdmin.php` previously implemented `IIconSection` instead of `ISettings`, causing a 403 on the admin page. This was fixed in commit `0efbcec` but needs verification.
2. **Missing SettingsLoadService tests** — The JSON-based config import (`larpingapp_register.json`) lacks unit test coverage.
3. **Missing SettingsController security annotation** — The `index()` endpoint was missing `#[AuthorizedAdminSetting]`.

## Goals / Non-Goals

**Goals:**
- Verify the ISettings fix is working correctly
- Add unit tests for SettingsService (config key filtering, get/update)
- Add unit tests for SettingsController (admin-only access, response format)
- Document the admin settings feature with screenshots

**Non-Goals:**
- Changing the settings UI layout (works as-is)
- Adding new object types beyond the existing 10
- Refactoring the cascading dropdown logic

## Decisions

### 1. Use existing Nextcloud admin settings pattern
**Decision:** Keep the current `LarpingAppAdmin implements ISettings` + `LarpingAppAdmin implements IIconSection` (in Sections/) dual-class pattern.
**Rationale:** This is the standard Nextcloud pattern — one class for the section (sidebar item), one for the settings content.

### 2. Settings storage via IAppConfig
**Decision:** Continue using `IAppConfig` for all settings storage (no OpenRegister for settings themselves).
**Rationale:** Admin settings are app configuration, not user data. IAppConfig is the Nextcloud-native approach per ADR-004.

### 3. Frontend component reuse
**Decision:** Settings.vue uses standard Nextcloud components (`NcSelect`, `NcButton`) — no `@conduction/nextcloud-vue` components needed here since it's a simple form, not a list/detail/dashboard view.
**Rationale:** Per ADR-012, shared components are for standard CRUD patterns. Settings forms are one-off and don't benefit from CnIndexPage/CnFormDialog.

## Risks / Trade-offs

- **[Risk]** OpenRegister may not be installed → Settings.vue already handles this with `hasOpenRegisters` check, showing disabled dropdowns.
- **[Risk]** Config keys may drift between PHP and JS → Mitigated by `SettingsService.CONFIG_KEYS` constant used in both `getSettings()` and `updateSettings()`.

## Files Affected

- `lib/Settings/LarpingAppAdmin.php` — verify ISettings implementation
- `lib/Controller/SettingsController.php` — verify security annotation
- `lib/Service/SettingsService.php` — existing, needs test coverage
- `tests/Unit/Service/SettingsServiceTest.php` — new
- `tests/Unit/Controller/SettingsControllerTest.php` — existing, extend
- `docs/features/admin-settings.md` — update with screenshots
