---
status: done
---

# User Settings

## Purpose

Provides a settings dialog accessible from the navigation footer, allowing users to re-import configuration. Uses `NcAppSettingsDialog` from `@nextcloud/vue`.

@e2e exclude The settings dialog's re-import round-trip is covered by Jest/vitest unit tests (tests/vitest/settingsStore.spec.js — reimportConfiguration POSTs to /api/settings/reimport); the dialog wiring, loading state, and feedback are component-level concerns not exercisable in the bare test-env (re-import requires OpenRegister configured with the Larpinq schemas).

## Requirements

### Requirement: Settings Dialog

A settings dialog MUST be openable from a gear icon in the navigation footer, built with `NcAppSettingsDialog` and exposing a Configuration section with a re-import button.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| USET-001 | A settings gear icon in the navigation footer MUST open the settings dialog | MUST | Implemented |
| USET-002 | The dialog MUST use NcAppSettingsDialog with show-navigation | MUST | Implemented |
| USET-003 | The dialog MUST include a Configuration section with re-import button | MUST | Implemented |

#### Scenario: Open the settings dialog from the footer

- GIVEN the user is in the Larpinq navigation
- WHEN they click the settings gear icon in the navigation footer
- THEN the `NcAppSettingsDialog` MUST open with navigation shown
- AND it MUST present a Configuration section containing a re-import button

### Requirement: Re-import Configuration

Clicking re-import MUST invoke `settingsStore.reimportConfiguration()`, show a loading state while in flight, and display success or failure feedback on completion.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| USET-004 | Clicking re-import MUST call settingsStore.reimportConfiguration() | MUST | Implemented |
| USET-005 | The button MUST show loading state during reimport | MUST | Implemented |
| USET-006 | Success/failure feedback MUST be displayed after reimport | MUST | Implemented |

#### Scenario: Re-import configuration from the dialog

- GIVEN the Configuration section is open
- WHEN the user clicks the re-import button
- THEN `settingsStore.reimportConfiguration()` MUST be called
- AND the button MUST show a loading state during the request
- AND success or failure feedback MUST be displayed when it completes

### Requirement: Integration with App

The `UserSettings` component MUST be wired into `App.vue`, opened via an `open-settings` event from the MainMenu settings nav item, with `App.vue` owning the `showSettingsDialog` state.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| USET-007 | App.vue MUST include UserSettings component | MUST | Implemented |
| USET-008 | MainMenu Settings nav item MUST emit open-settings event | MUST | Implemented |
| USET-009 | App.vue MUST manage showSettingsDialog state | MUST | Implemented |

#### Scenario: MainMenu settings item opens the dialog via App.vue

- GIVEN `App.vue` includes the `UserSettings` component and owns `showSettingsDialog`
- WHEN the MainMenu Settings nav item emits the `open-settings` event
- THEN `App.vue` MUST set `showSettingsDialog` to open the dialog
