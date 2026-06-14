# character-photos-leaf Specification

## Purpose
TBD - created by archiving change character-photos-leaf. Update Purpose after archive.
## Requirements
### Requirement: Character detail page MUST host the OR photos leaf

The Character detail page MUST surface the OpenRegister photos integration leaf for character portraits / reference images. The leaf MUST be obtained through the OR integration registry (ADR-019) and LarpingApp MUST NOT add a bespoke image field or upload handler to the Character schema (ADR-022). The host is `src/views/ObjectDetail.vue` for the `character` object type.

#### Scenario: Photos leaf renders on a character detail page

- GIVEN a character "Sir Lancelot"
- AND the OpenRegister integration registry exposes the photos leaf
- WHEN a game master opens the character detail page
- THEN the photos leaf widget MUST be rendered
- AND a game master MUST be able to attach a portrait image to the character

### Requirement: Images MUST be stored via the OR files abstraction

Character images MUST be stored through the OpenRegister files / object-interactions abstraction that the photos leaf is built on, and LarpingApp MUST NOT introduce an app-local image column or blob store on the Character object.

#### Scenario: Attaching a portrait persists via OR files

- GIVEN the photos leaf on character "Sir Lancelot"
- WHEN a game master uploads a portrait image
- THEN the image MUST be persisted through the OR files / object-interactions abstraction
- AND LarpingApp MUST NOT write an image blob field on the Character object

### Requirement: Photos leaf MUST degrade gracefully when unavailable

The Character detail page MUST omit the portrait widget and MUST continue to function as today when the OpenRegister photos leaf or the integration registry is not available, mirroring the DocuDesk PDF graceful-degradation pattern in `openspec/specs/pdf-export/spec.md`.

#### Scenario: Photos leaf hidden when integration registry absent

- GIVEN the OpenRegister integration registry does not expose a photos leaf
- WHEN the character detail page is opened for "Sir Lancelot"
- THEN the portrait widget MUST NOT be rendered
- AND the rest of the character detail page MUST render normally

