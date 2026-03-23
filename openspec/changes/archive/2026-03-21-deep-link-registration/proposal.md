# Deep Link Registration

## Problem

LarpingApp does not register deep links with OpenRegister's unified search provider. When users search in Nextcloud's unified search bar, LarpingApp objects (characters, events, items, etc.) do not link directly to LarpingApp's detail views. Instead, they open in OpenRegister's generic view.

Pipelinq and Procest both implement this pattern via a `DeepLinkRegistrationListener` that registers URL templates for each object type.

## Proposal

Add a `DeepLinkRegistrationListener` to LarpingApp that registers deep link URL patterns for all object types:

- Characters: `/apps/larpingapp/#/characters/{uuid}`
- Players: `/apps/larpingapp/#/players/{uuid}`
- Abilities: `/apps/larpingapp/#/abilities/{uuid}`
- Skills: `/apps/larpingapp/#/skills/{uuid}`
- Items: `/apps/larpingapp/#/items/{uuid}`
- Conditions: `/apps/larpingapp/#/conditions/{uuid}`
- Effects: `/apps/larpingapp/#/effects/{uuid}`
- Events: `/apps/larpingapp/#/events/{uuid}`

## Files to Change

1. **New:** `lib/Listener/DeepLinkRegistrationListener.php` — Event listener
2. **Update:** `lib/AppInfo/Application.php` — Register the listener
3. **Update:** `appinfo/info.xml` — No changes needed (listener is registered in PHP)

## Risks

- None significant. The deep link event is optional — if OpenRegister is not installed, the listener is never called.
- The listener uses `OCA\OpenRegister\Event\DeepLinkRegistrationEvent` which is only available when OpenRegister is enabled.

## Acceptance Criteria

- GIVEN a user searches for a character name in Nextcloud's unified search
- WHEN the search results include a LarpingApp character
- THEN clicking the result navigates to `/apps/larpingapp/#/characters/{uuid}`
