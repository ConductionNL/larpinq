# User Settings

## Problem

LarpingApp has admin settings for configuring OpenRegister schemas, but no personal user settings. Users cannot configure personal preferences like notification settings, default views, or display options.

Pipelinq and Procest both implement a `UserSettings.vue` dialog using `NcAppSettingsDialog` that allows users to configure personal preferences.

## Proposal

Add a user settings dialog to LarpingApp with the following sections:

### Notifications
- **Event reminders** — Get notified about upcoming events you're subscribed to
- **Character updates** — Get notified when characters you manage are modified

### Display
- **Default view** — Choose which view opens when navigating to LarpingApp (Dashboard, Characters, Events)

## Files to Change

1. **New:** `src/views/settings/UserSettings.vue` — User settings dialog component
2. **New:** `lib/Controller/UserSettingsController.php` — API endpoints for user preferences (GET/PUT)
3. **Update:** `src/App.vue` — Add settings gear icon that opens UserSettings dialog
4. **Update:** `appinfo/routes.php` — Add user settings API routes
5. **Update:** `l10n/en.json` + `l10n/nl.json` — Translation keys

## Backend Storage

User settings are stored via Nextcloud's `IConfig::setUserValue()` / `getUserValue()` — no OpenRegister needed for personal preferences.

## Acceptance Criteria

- GIVEN a user opens LarpingApp
- WHEN they click the settings gear icon in the navigation footer
- THEN a settings dialog opens with notification and display preferences

- GIVEN a user toggles a notification preference
- WHEN the dialog closes
- THEN the preference is persisted and respected on next load
