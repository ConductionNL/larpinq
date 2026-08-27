# event-location-to-maps-leaf Specification

## Purpose
TBD - created by archiving change event-location-to-maps-leaf. Update Purpose after archive.
## Requirements
### Requirement: Event detail page MUST host the OR maps leaf for location

The Event detail page MUST surface the OpenRegister maps integration leaf for capturing and displaying the event location. The leaf MUST be obtained through the OR integration registry (ADR-019) and MUST NOT be a Larpinq-local geo model or map embed (ADR-022). The host is `src/views/ObjectDetail.vue` for the `event` object type.

#### Scenario: Maps leaf renders for an event with a location

- GIVEN an event "Summer LARP 2025" with a confirmed location "Forest Camp, Veluwe"
- AND the OpenRegister integration registry exposes the maps leaf
- WHEN a game master opens the event detail page
- THEN the maps leaf widget MUST render a map centred on the event location
- AND MUST display the address

### Requirement: Structured location MUST be owned by the maps leaf, not duplicated

The structured location data (address and/or coordinates) MUST be owned by the OpenRegister maps abstraction via the leaf, and Larpinq MUST NOT maintain a parallel geo model for events.

#### Scenario: Setting a location through the maps leaf

- GIVEN an event with no confirmed location
- WHEN the game master picks a point / enters an address in the maps leaf and saves
- THEN the location MUST be persisted through the maps leaf / OR maps abstraction
- AND Larpinq MUST NOT write a duplicate coordinate model of its own

### Requirement: Legacy free-text location MUST be preserved and migratable

The system MUST preserve any existing free-text `location` string and MUST offer it as the initial address hint when the user first edits the location through the maps leaf; the original string MUST NOT be discarded until the user confirms a structured location.

#### Scenario: Migrating a legacy free-text location

- GIVEN an event whose `location` is the legacy string "Forest Camp"
- WHEN the game master opens the maps leaf
- THEN "Forest Camp" MUST be pre-filled as the address hint
- AND the legacy string MUST remain on the object until a structured location is confirmed

### Requirement: Maps leaf MUST degrade gracefully when unavailable

The Event detail page MUST fall back to displaying the plain `location` string read-only and MUST continue to function when the OpenRegister maps leaf or the integration registry is not available, mirroring the DocuDesk PDF graceful-degradation pattern in `openspec/specs/pdf-export/spec.md`.

#### Scenario: Maps leaf hidden when integration registry absent

- GIVEN the OpenRegister integration registry does not expose a maps leaf
- WHEN the event detail page is opened for an event with `location` "Forest Camp"
- THEN the map widget MUST NOT be rendered
- AND the plain string "Forest Camp" MUST be shown read-only
- AND the rest of the event detail page MUST render normally

