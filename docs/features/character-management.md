# Character Management

## Overview

Character management provides full CRUD lifecycle for LARP characters, including player characters, NPCs, and other character types. Characters are the central entity in LarpingApp, linking to skills, items, conditions, and events.

## Features

- **Create characters** with name, description, background, faith, notice, and notes fields
- **Update characters** with all editable fields
- **Delete characters** with confirmation dialog
- **List characters** with search, pagination, and faceted filtering
- **View character detail** with tabbed interface (properties, relations, audit trail)
- **Stat calculation** via `CharacterService.calculateCharacter()` — automatically computes ability scores from associated effects
- **Player association** via `ocName` field referencing a Player object
- **Currency system** with gold, silver, copper fields
- **Approval workflow** via `approved` boolean field

## Navigation

Characters are accessible from the main navigation sidebar with the BriefcaseAccountOutline icon.

## Routes

- `/characters` — Character list view
- `/characters/:id` — Character detail view

## Screenshot

![Character Management](/screenshots/character-management.png)

## Technical Details

- Frontend: `ObjectList.vue` and `ObjectDetail.vue` (generic components)
- Store: `createObjectStore('object')` from `@conduction/nextcloud-vue`
- Backend: `RegisterObjectFetcher` resolves OpenRegister mappers
- Stat engine: `CharacterService` applies effects in order: skills, items, conditions, events
