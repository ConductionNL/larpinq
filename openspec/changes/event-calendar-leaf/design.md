# Design — event-calendar-leaf

## Context

`docs/Schema/Event.json` defines `startDate` and `endDate` as
`format: date-time`. Events are "the place where players come together to have
adventures" — they are dated gatherings. The current UI renders these as two
plain date-time inputs on the generic object form. There is no calendar.

## Decision

Consume the OpenRegister **calendar leaf** (ADR-019 integration registry,
ADR-022 consume-don't-build) on the Event detail page rather than building a
bespoke calendar / scheduling UI in LarpingApp.

### Why a leaf, not in-app

- ADR-022 lists calendar as one of the integration-registry leaves OR provides.
  Building a parallel calendar would be a review-blocking anti-pattern
  ("Duplicate sidebar tab systems" / "App-local ... that mirror an OR
  integration").
- The leaf compounds: when OR improves the calendar leaf (recurrence, ICS feed,
  conflict detection), every consuming app — including LarpingApp — gets it
  free.
- Precedent: PDF export already delegates wholesale to DocuDesk
  (`openspec/specs/pdf-export/spec.md`). Same integrate-don't-build posture.

### Mapping

| Event field | Calendar event field |
|---|---|
| `name` | summary / title |
| `description` | description |
| `startDate` | start (DTSTART) |
| `endDate` | end (DTEND) |
| `location` | location (see `event-location-to-maps-leaf` change) |

The object's own fields remain the canonical store. The calendar leaf reads
them via the integration registry; it does not introduce a second persistence
path for dates.

## Alternatives considered

- **Bespoke FullCalendar component in LarpingApp** — rejected: duplicates an OR
  abstraction, no cross-app scheduling, ADR-022 violation.
- **Direct CalDAV from LarpingApp** — rejected: this is exactly the
  `X-DECIDESK-*` parallel-properties anti-pattern ADR-022 calls out; CalDAV
  linkage belongs behind the OR calendar leaf.

## Risks

- The calendar leaf must accept a date-range mapping config. If the leaf
  currently assumes a single `date` field, an upstream OR change is a
  prerequisite — tracked as a dependency, not built here.
