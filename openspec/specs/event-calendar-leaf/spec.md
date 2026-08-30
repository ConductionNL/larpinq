# event-calendar-leaf Specification

## Purpose
TBD - created by archiving change event-calendar-leaf. Update Purpose after archive.
## Requirements
### Requirement: Event detail page MUST host the OR calendar leaf

The Event detail page MUST surface the OpenRegister calendar integration leaf as a widget. The leaf MUST be obtained through the OR integration registry (ADR-019) and MUST NOT be a Larpinq-local calendar implementation (ADR-022). The host is `src/views/ObjectDetail.vue` for the `event` object type.

#### Scenario: Calendar leaf appears on an event with dates

- GIVEN an event "Summer LARP 2025" with `startDate` 2025-06-01T10:00 and `endDate` 2025-06-03T16:00
- AND the OpenRegister integration registry exposes the calendar leaf
- WHEN a game master opens the event detail page
- THEN the calendar leaf widget MUST be rendered on the page
- AND the widget MUST show the event spanning 2025-06-01 to 2025-06-03

### Requirement: Event dates MUST map to the calendar event range

The Event's `startDate` and `endDate` MUST be the source for the calendar
event's start and end. The Event object remains the canonical store of the
dates; the calendar leaf MUST read them via the integration registry and MUST
NOT introduce a second persistence path for the date values.

#### Scenario: Editing the event date updates the calendar view

- GIVEN event "Summer LARP 2025" rendered with the calendar leaf
- WHEN the game master changes `endDate` to 2025-06-05 on the object form and saves
- THEN the canonical Event object MUST store `endDate` = 2025-06-05
- AND the calendar leaf MUST reflect the event ending on 2025-06-05 on next render

#### Scenario: Event without dates renders no calendar entry

- GIVEN an event with no `startDate` set
- WHEN the event detail page is opened
- THEN the calendar leaf MUST render without an event entry for that object
- AND MUST NOT raise an error

### Requirement: Calendar leaf MUST degrade gracefully when unavailable

The Event detail page MUST hide the calendar widget and MUST continue to function when the OpenRegister calendar leaf or the integration registry is not available (e.g. OR not installed, or the calendar leaf not registered), mirroring the DocuDesk PDF graceful-degradation pattern in `openspec/specs/pdf-export/spec.md`.

#### Scenario: Calendar leaf hidden when integration registry absent

- GIVEN the OpenRegister integration registry does not expose a calendar leaf
- WHEN the event detail page is opened
- THEN the calendar widget MUST NOT be rendered
- AND the rest of the event detail page MUST render normally

