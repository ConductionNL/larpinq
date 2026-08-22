---
status: done
---

# Deep Link Registration

## Purpose

@e2e exclude pure-backend event-listener spec — DeepLinkRegistrationListener registers URL patterns via PHP event dispatch; no browser-navigable UI surface to drive with Playwright

Registers deep link URL patterns with OpenRegister's unified search provider so that Larpinq objects found via Nextcloud unified search link directly to Larpinq's detail views instead of OpenRegister's generic view.

## Requirements

### Requirement: Deep Link Listener

A `DeepLinkRegistrationListener` MUST register Larpinq deep link URL templates with OpenRegister's unified search provider when OpenRegister dispatches its registration event, and MUST degrade gracefully when OpenRegister is not installed.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DEEP-001 | A `DeepLinkRegistrationListener` MUST be registered in Application.php | MUST | Implemented |
| DEEP-002 | The listener MUST register URL templates for all 8 object types | MUST | Implemented |
| DEEP-003 | URL pattern MUST be `/apps/larpingapp/#/{type}/{uuid}` | MUST | Implemented |
| DEEP-004 | The listener MUST only fire when OpenRegister dispatches DeepLinkRegistrationEvent | MUST | Implemented |
| DEEP-005 | The listener MUST gracefully handle OpenRegister not being installed | MUST | Implemented |

#### Scenario: Listener registers URL templates on OpenRegister event

- GIVEN OpenRegister is installed and dispatches `DeepLinkRegistrationEvent`
- WHEN the `DeepLinkRegistrationListener` handles the event
- THEN it MUST register URL templates of the form `/apps/larpingapp/#/{type}/{uuid}` for all 8 object types
- AND WHEN OpenRegister is not installed
- THEN the listener MUST NOT throw and registration MUST be skipped

### Requirement: Object Type URL Mapping

Each Larpinq object type MUST map to its corresponding Larpinq detail-view deep link URL so unified-search results route to the in-app view.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DEEP-006 | Characters MUST link to `/apps/larpingapp/#/characters/{uuid}` | MUST | Implemented |
| DEEP-007 | Players MUST link to `/apps/larpingapp/#/players/{uuid}` | MUST | Implemented |
| DEEP-008 | Abilities MUST link to `/apps/larpingapp/#/abilities/{uuid}` | MUST | Implemented |
| DEEP-009 | Skills MUST link to `/apps/larpingapp/#/skills/{uuid}` | MUST | Implemented |
| DEEP-010 | Items MUST link to `/apps/larpingapp/#/items/{uuid}` | MUST | Implemented |
| DEEP-011 | Conditions MUST link to `/apps/larpingapp/#/conditions/{uuid}` | MUST | Implemented |
| DEEP-012 | Effects MUST link to `/apps/larpingapp/#/effects/{uuid}` | MUST | Implemented |
| DEEP-013 | Events MUST link to `/apps/larpingapp/#/events/{uuid}` | MUST | Implemented |

#### Scenario: Object type resolves to its detail-view URL

- GIVEN a unified-search result references a Larpinq object by type and uuid
- WHEN the deep link URL is resolved for a `character` with uuid `abc`
- THEN the URL MUST be `/apps/larpingapp/#/characters/abc`
- AND each of the 8 object types MUST resolve to its corresponding pluralized detail-view path
