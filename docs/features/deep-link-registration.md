# Deep Link Registration

## Overview

Registers deep link URL patterns with OpenRegister's unified search provider so that LarpingApp objects found via Nextcloud unified search link directly to LarpingApp's detail views.

## Features

- **8 object type URL patterns** registered for unified search
- **Automatic registration** via event listener on app boot
- **Optional dependency** — only fires when OpenRegister dispatches the event

## URL Patterns

| Object Type | URL Pattern |
|------------|-------------|
| Character | `/apps/larpingapp/#/characters/{uuid}` |
| Player | `/apps/larpingapp/#/players/{uuid}` |
| Ability | `/apps/larpingapp/#/abilities/{uuid}` |
| Skill | `/apps/larpingapp/#/skills/{uuid}` |
| Item | `/apps/larpingapp/#/items/{uuid}` |
| Condition | `/apps/larpingapp/#/conditions/{uuid}` |
| Effect | `/apps/larpingapp/#/effects/{uuid}` |
| Event | `/apps/larpingapp/#/events/{uuid}` |

## Technical Details

- Listener: `DeepLinkRegistrationListener`
- Registration: `Application.register()` registers the listener
- Event: `OCA\OpenRegister\Event\DeepLinkRegistrationEvent` (optional dependency)
- Safe: If OpenRegister is not installed, the listener is never called
