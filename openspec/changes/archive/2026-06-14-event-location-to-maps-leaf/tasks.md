---
status: implemented
---

# Tasks

## 1. Integration wiring
- [x] 1.1 Confirm the OR maps leaf is exposed via the integration registry (ADR-019) — consumer-only check against `window.OCA.OpenRegister.integrations.isRegistered('maps')`
- [x] 1.2 Add the maps leaf host to `src/views/ObjectDetail.vue` for the `event` object type — `ObjectDetail.vue` renders `[data-integration-host="maps"]` for event objectType when the registry exposes the leaf; manifest `EventDetail` slot `maps-leaf: ObjectDetail`

## 2. Location ownership
- [x] 2.1 Persist structured location through the maps leaf / OR maps abstraction (no parallel geo model) — `event` schema linkedTypes fragment (`lib/Settings/register.d/event-location-to-maps-leaf.json`) declares the binding; no app-local geo fields are introduced
- [x] 2.2 Render the map widget + address for events with a confirmed location — handled by the leaf widget inside CnObjectSidebar via the host marker

## 3. Legacy migration
- [x] 3.1 Pre-fill the legacy free-text `location` as the address hint on first edit — leaf widget reads the legacy string from the OR event object on first open
- [x] 3.2 Preserve the legacy string until a structured location is confirmed — the OR event `location` field is not cleared by this app; the leaf widget overwrites only when the user confirms a structured value

## 4. Graceful degradation
- [x] 4.1 Fall back to read-only plain `location` string when the maps leaf / registry is unavailable — `availableLeaves` returns no entries when registry/leaf absent; existing OR detail rendering of the `location` field continues unchanged

## 5. Tests
- [x] 5.1 Frontend test: maps leaf renders for an event with a location — `tests/e2e/spec-coverage/spa-ui.spec.ts` `event-location-to-maps-leaf` describe block
- [x] 5.2 Frontend test: legacy free-text pre-fills and is preserved — same describe block, "legacy free-text location" scenario
- [x] 5.3 Frontend test: read-only fallback when leaf absent — same describe block, "maps leaf hidden when integration registry absent" scenario
