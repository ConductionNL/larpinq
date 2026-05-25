---
status: draft
---

# Tasks

## 1. Integration wiring
- [ ] 1.1 Confirm the OR maps leaf is exposed via the integration registry (ADR-019)
- [ ] 1.2 Add the maps leaf host to `src/views/ObjectDetail.vue` for the `event` object type

## 2. Location ownership
- [ ] 2.1 Persist structured location through the maps leaf / OR maps abstraction (no parallel geo model)
- [ ] 2.2 Render the map widget + address for events with a confirmed location

## 3. Legacy migration
- [ ] 3.1 Pre-fill the legacy free-text `location` as the address hint on first edit
- [ ] 3.2 Preserve the legacy string until a structured location is confirmed

## 4. Graceful degradation
- [ ] 4.1 Fall back to read-only plain `location` string when the maps leaf / registry is unavailable

## 5. Tests
- [ ] 5.1 Frontend test: maps leaf renders for an event with a location
- [ ] 5.2 Frontend test: legacy free-text pre-fills and is preserved
- [ ] 5.3 Frontend test: read-only fallback when leaf absent
