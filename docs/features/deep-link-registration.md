# Deep Link Registration

## Overview

Registers deep link URL patterns with OpenRegister's unified search provider so that Larpinq objects found via Nextcloud unified search link directly to Larpinq's detail views.

## Features

- **8 object type URL patterns** registered for unified search
- **Automatic registration** via event listener on app boot
- **Optional dependency** — only fires when OpenRegister dispatches the event

## URL Patterns

| Object Type | URL Pattern |
|------------|-------------|
| Character | `/apps/larpinq/#/characters/{uuid}` |
| Player | `/apps/larpinq/#/players/{uuid}` |
| Ability | `/apps/larpinq/#/abilities/{uuid}` |
| Skill | `/apps/larpinq/#/skills/{uuid}` |
| Item | `/apps/larpinq/#/items/{uuid}` |
| Condition | `/apps/larpinq/#/conditions/{uuid}` |
| Effect | `/apps/larpinq/#/effects/{uuid}` |
| Event | `/apps/larpinq/#/events/{uuid}` |

## Technical Details

- Listener: `DeepLinkRegistrationListener`
- Registration: `Application.register()` registers the listener
- Event: `OCA\OpenRegister\Event\DeepLinkRegistrationEvent` (optional dependency)
- Safe: If OpenRegister is not installed, the listener is never called
