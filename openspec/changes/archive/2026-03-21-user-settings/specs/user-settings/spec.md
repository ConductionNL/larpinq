---
status: implemented
---

# User Settings

## Purpose

Provides a settings dialog accessible from the navigation footer, allowing users to re-import configuration. Uses `NcAppSettingsDialog` from `@nextcloud/vue`.

## Requirements

### Requirement: Settings Dialog

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| USET-001 | A settings gear icon in the navigation footer MUST open the settings dialog | MUST | Implemented |
| USET-002 | The dialog MUST use NcAppSettingsDialog with show-navigation | MUST | Implemented |
| USET-003 | The dialog MUST include a Configuration section with re-import button | MUST | Implemented |

### Requirement: Re-import Configuration

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| USET-004 | Clicking re-import MUST call settingsStore.reimportConfiguration() | MUST | Implemented |
| USET-005 | The button MUST show loading state during reimport | MUST | Implemented |
| USET-006 | Success/failure feedback MUST be displayed after reimport | MUST | Implemented |

### Requirement: Integration with App

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| USET-007 | App.vue MUST include UserSettings component | MUST | Implemented |
| USET-008 | MainMenu Settings nav item MUST emit open-settings event | MUST | Implemented |
| USET-009 | App.vue MUST manage showSettingsDialog state | MUST | Implemented |
