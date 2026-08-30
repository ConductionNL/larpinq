---
status: implemented
---

# Deep Link Registration

## Purpose

Registers deep link URL patterns with OpenRegister's unified search provider so that LarpingApp objects found via Nextcloud unified search link directly to LarpingApp's detail views instead of OpenRegister's generic view.

## Requirements

### Requirement: Deep Link Listener

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DEEP-001 | A `DeepLinkRegistrationListener` MUST be registered in Application.php | MUST | Implemented |
| DEEP-002 | The listener MUST register URL templates for all 8 object types | MUST | Implemented |
| DEEP-003 | URL pattern MUST be `/apps/larpingapp/#/{type}/{uuid}` | MUST | Implemented |
| DEEP-004 | The listener MUST only fire when OpenRegister dispatches DeepLinkRegistrationEvent | MUST | Implemented |
| DEEP-005 | The listener MUST gracefully handle OpenRegister not being installed | MUST | Implemented |

### Requirement: Object Type URL Mapping

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
