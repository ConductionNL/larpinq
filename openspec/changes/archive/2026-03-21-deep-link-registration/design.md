---
status: approved
---

# Deep Link Registration — Design

## Architecture

Uses Nextcloud's event listener system. A `DeepLinkRegistrationListener` is registered in `Application.php` and listens for `OCA\OpenRegister\Event\DeepLinkRegistrationEvent`. When fired, it registers URL templates for all LarpingApp object types.

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| Listener | `DeepLinkRegistrationListener` | Registers URL patterns |
| Bootstrap | `Application.register()` | Registers the listener |
| Event | `DeepLinkRegistrationEvent` | OpenRegister event (optional dependency) |

## URL Pattern

All deep links follow the pattern: `/apps/larpingapp/#/{plural-type}/{uuid}`

This matches the Vue router routes defined in `router/index.js`.

## Optional Dependency

The event class `OCA\OpenRegister\Event\DeepLinkRegistrationEvent` only exists when OpenRegister is installed. The listener registration is safe because Nextcloud only dispatches events to registered listeners, and the event itself is only dispatched by OpenRegister.
