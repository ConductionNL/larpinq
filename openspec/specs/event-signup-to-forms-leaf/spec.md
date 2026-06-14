# event-signup-to-forms-leaf Specification

## Purpose
TBD - created by archiving change event-signup-to-forms-leaf. Update Purpose after archive.
## Requirements
### Requirement: Event detail page MUST host the OR forms leaf for sign-up

The Event detail page MUST surface the OpenRegister forms integration leaf to collect player sign-ups. The leaf MUST be obtained through the OR integration registry (ADR-019) and LarpingApp MUST NOT build a bespoke sign-up form or submission store (ADR-022). The host is `src/views/ObjectDetail.vue` for the `event` object type.

#### Scenario: Sign-up form renders on an event

- GIVEN an event "Summer LARP 2025" open for sign-up
- AND the OpenRegister integration registry exposes the forms leaf
- WHEN a player opens the event detail page
- THEN the forms leaf sign-up form MUST render
- AND a player MUST be able to submit a sign-up bound to this event

### Requirement: Form definition and submissions MUST be owned by the forms leaf

The sign-up form definition and its submissions MUST be owned by the OpenRegister forms abstraction via the leaf, and LarpingApp MUST NOT persist a parallel submission store.

#### Scenario: A sign-up submission is stored by the forms leaf

- GIVEN the forms leaf sign-up form for event "Summer LARP 2025"
- WHEN a player submits the form
- THEN the submission MUST be persisted through the forms leaf / OR forms abstraction
- AND LarpingApp MUST NOT write the submission to an app-local table

### Requirement: Capacity and waiting-list ordering MUST stay in LarpingApp

The event-domain rules — capacity, confirmed-vs-waitlisted classification, and waiting-list ordering derived from submission order — MUST remain LarpingApp logic that consumes the leaf's submissions; confirmed sign-ups MUST feed the Event `players[]` participation.

#### Scenario: Waiting list forms when capacity is reached

- GIVEN event "Summer LARP 2025" with capacity 2 and two confirmed sign-ups
- WHEN a third player submits the sign-up form
- THEN the third player MUST be classified as waitlisted (not added to `players[]`)
- AND the two confirmed players MUST be present in the Event `players[]`

### Requirement: Forms leaf MUST degrade gracefully when unavailable

The Event detail page MUST hide the sign-up surface and MUST allow `players[]` to be edited manually (existing behaviour) when the OpenRegister forms leaf or the integration registry is not available, mirroring the DocuDesk PDF graceful-degradation pattern in `openspec/specs/pdf-export/spec.md`.

#### Scenario: Sign-up hidden when integration registry absent

- GIVEN the OpenRegister integration registry does not expose a forms leaf
- WHEN the event detail page is opened
- THEN the sign-up form MUST NOT be rendered
- AND a game master MUST still be able to edit `players[]` manually
- AND the rest of the event detail page MUST render normally

