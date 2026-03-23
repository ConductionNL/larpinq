---
status: approved
---

# User Settings — Design

## Architecture

The user settings dialog is a Vue component (`UserSettings.vue`) using Nextcloud's `NcAppSettingsDialog`. It is opened from the navigation footer's Settings item and managed by `App.vue`'s `showSettingsDialog` state.

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| View | `UserSettings.vue` | Settings dialog with configuration section |
| View | `App.vue` | Hosts UserSettings, manages open state |
| Navigation | `MainMenu.vue` | Settings gear emits `open-settings` event |
| Store | `settings.js` | `reimportConfiguration()` action |
| Controller | `SettingsController.reimport()` | Backend endpoint for re-import |

## Flow

1. User clicks Settings gear in navigation footer
2. MainMenu emits `open-settings` event
3. App.vue sets `showSettingsDialog = true`
4. UserSettings dialog opens with Configuration section
5. User clicks "Re-import configuration"
6. settingsStore.reimportConfiguration() calls POST /api/settings/reimport
7. Success/failure message displayed in dialog
