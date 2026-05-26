---
status: draft
---

# Player Person Data via Contacts Leaf

## Purpose

Source player/person data (name, email, phone, address) from the OpenRegister
contacts integration leaf on the Player detail page, consuming the OR
integration registry (ADR-019) per ADR-022 instead of growing a bespoke
LarpingApp person model. The LARP-domain Player ↔ character linkage stays
in-app.

## ADDED Requirements

### Requirement: Player detail page MUST host the OR contacts leaf

The Player detail page MUST surface the OpenRegister contacts integration leaf for player person data. The leaf MUST be obtained through the OR integration registry (ADR-019) and LarpingApp MUST NOT maintain an app-local person model duplicating OR contacts (ADR-022). The host is `src/views/ObjectDetail.vue` for the `player` object type.

#### Scenario: Contacts leaf renders on a player detail page

- GIVEN a player "John Doe" linked to an OR contact with email "john@example.com"
- AND the OpenRegister integration registry exposes the contacts leaf
- WHEN a game master opens the player detail page
- THEN the contacts leaf widget MUST render the contact's person data
- AND MUST display the email "john@example.com"

### Requirement: Person attributes MUST be owned by the contacts leaf

The person attributes (display name, email, phone, address, notes) MUST be owned by the OpenRegister contacts abstraction via the leaf, and LarpingApp MUST follow the OR contacts schema rather than store a parallel set of person fields on the Player object.

#### Scenario: Editing person data through the contacts leaf

- GIVEN a player linked to an OR contact
- WHEN the game master edits the phone number in the contacts leaf and saves
- THEN the phone number MUST be persisted through the contacts leaf / OR contacts abstraction
- AND LarpingApp MUST NOT write a duplicate phone field on the Player object

### Requirement: In-game Player linkage MUST remain in LarpingApp

LarpingApp MUST keep the LARP-domain linkage between a Player and their characters (the character `ocName` reference, PLR-006) and the event participation (`players[]`) in-app; only the person/contact attributes are delegated to the contacts leaf.

#### Scenario: Character ocName still resolves after contacts adoption

- GIVEN character "Sir Lancelot" references player "John Doe" via `ocName`
- WHEN the player's person data is sourced from the contacts leaf
- THEN the character's `ocName` reference MUST still resolve to "John Doe"
- AND the in-game linkage MUST be unchanged

### Requirement: Legacy Player name/description MUST be migratable to the contact

The system MUST map an existing Player `name` to the linked contact's display name and `description` to the contact notes when adopting the contacts leaf, and MUST NOT lose the legacy values during migration.

#### Scenario: Migrating a legacy player profile

- GIVEN a player "John Doe" with description "Experienced LARP player" and no linked contact
- WHEN the contacts leaf is adopted for this player
- THEN "John Doe" MUST map to the contact display name
- AND "Experienced LARP player" MUST map to the contact notes

### Requirement: Contacts leaf MUST degrade gracefully when unavailable

The Player detail page MUST fall back to the existing `{name, description}` fields and MUST continue to function when the OpenRegister contacts leaf or the integration registry is not available, mirroring the DocuDesk PDF graceful-degradation pattern in `openspec/specs/pdf-export/spec.md`.

#### Scenario: Contacts leaf hidden when integration registry absent

- GIVEN the OpenRegister integration registry does not expose a contacts leaf
- WHEN the player detail page is opened for player "John Doe"
- THEN the contacts widget MUST NOT be rendered
- AND the existing `name` and `description` fields MUST be shown
- AND the rest of the player detail page MUST render normally
