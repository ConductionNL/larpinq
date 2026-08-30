# Tasks: Admin Settings

## 1. Bug Fix — ISettings Implementation
- [x] 1.1 Verify `lib/Settings/LarpingAppAdmin.php` implements `ISettings` (not `IIconSection`) — confirm fix from commit 0efbcec
- [x] 1.2 Verify `lib/Sections/LarpingAppAdmin.php` implements `IIconSection` (correct for section registration)
- [x] 1.3 Test admin settings page loads without 403 error

## 2. Security — Controller Annotation
- [x] 2.1 Add `#[AuthorizedAdminSetting(Admin::class)]` to `SettingsController::index()` if missing
- [x] 2.2 Verify non-admin users get 403 when calling settings API

## 3. Unit Tests (ADR-009)
- [x] 3.1 Create `tests/Unit/Service/SettingsServiceTest.php` — test CONFIG_KEYS filtering, getSettings(), updateSettings()
- [x] 3.2 Extend `tests/Unit/Controller/SettingsControllerTest.php` — test index() returns JSON with all config keys
- [x] 3.3 Test SettingsLoadService — config file loading, register bootstrap

## 4. Browser Testing
- [x] 4.1 Navigate to admin settings page, verify it renders
- [x] 4.2 Test cascading dropdown (select OpenRegister → register → schema)
- [x] 4.3 Test save and reload persistence

## 5. Documentation with Screenshots (ADR-010)
- [x] 5.1 Take screenshot of admin settings page using Playwright MCP browser
- [x] 5.2 Write feature doc at `docs/features/admin-settings.md` with screenshot
- [x] 5.3 Update `docs/README.md` index with link to admin-settings doc

## 6. i18n (ADR-005)
- [x] 6.1 Verify all user-facing strings in Settings.vue use `t()` function
- [x] 6.2 Verify `l10n/en.json` and `l10n/nl.json` contain all settings-related keys
