---
status: implemented
---

# Tasks

## 1. Integration wiring
- [x] 1.1 Confirm the OR calendar leaf is exposed via the integration registry (ADR-019) and accepts a date-range mapping — relies on the `window.OCA.OpenRegister.integrations` registry contract from ADR-019 Stage 1 (no app-side change; consumer-only)
- [x] 1.2 Add the calendar leaf host to `src/views/ObjectDetail.vue` for the `event` object type — `ObjectDetail.vue` now renders `[data-integration-host="calendar"]` when `event` is the objectType and the registry exposes the leaf; manifest `EventDetail` slot `calendar-leaf: ObjectDetail`

## 2. Date mapping
- [x] 2.1 Map Event `startDate`/`endDate` (and `name`, `description`) to the calendar event range, reading from the canonical object (no second write path) — `event` schema linkedTypes fragment (`lib/Settings/register.d/event-calendar-leaf.json`) declares the binding; the leaf widget reads OR fields without a parallel app model
- [x] 2.2 Handle events with missing `startDate` (render no entry, no error) — leaf widget omits the entry when dates are absent; host div remains rendered but produces no calendar event (graceful empty)

## 3. Graceful degradation
- [x] 3.1 Hide the calendar widget when the calendar leaf / integration registry is unavailable (mirror DocuDesk PDF pattern) — `availableLeaves` computed property filters against `OCA.OpenRegister.integrations`; absent registry → no host div rendered

## 4. Tests
- [x] 4.1 Frontend test: calendar leaf renders for an event with dates — `tests/e2e/spec-coverage/spa-ui.spec.ts` `event-calendar-leaf` describe block
- [x] 4.2 Frontend test: widget hidden when registry/leaf absent — same describe block, "calendar leaf hidden when integration registry absent" scenario
- [x] 4.3 Frontend test: event without dates renders no entry and no error — same describe block, "event without dates" scenario
