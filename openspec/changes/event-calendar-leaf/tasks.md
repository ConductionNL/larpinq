---
status: draft
---

# Tasks

## 1. Integration wiring
- [ ] 1.1 Confirm the OR calendar leaf is exposed via the integration registry (ADR-019) and accepts a date-range mapping
- [ ] 1.2 Add the calendar leaf host to `src/views/ObjectDetail.vue` for the `event` object type

## 2. Date mapping
- [ ] 2.1 Map Event `startDate`/`endDate` (and `name`, `description`) to the calendar event range, reading from the canonical object (no second write path)
- [ ] 2.2 Handle events with missing `startDate` (render no entry, no error)

## 3. Graceful degradation
- [ ] 3.1 Hide the calendar widget when the calendar leaf / integration registry is unavailable (mirror DocuDesk PDF pattern)

## 4. Tests
- [ ] 4.1 Frontend test: calendar leaf renders for an event with dates
- [ ] 4.2 Frontend test: widget hidden when registry/leaf absent
- [ ] 4.3 Frontend test: event without dates renders no entry and no error
