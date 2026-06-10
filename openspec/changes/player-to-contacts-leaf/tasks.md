---
status: draft
---

# Tasks

## 1. Integration wiring
- [~] 1.1 Confirm the OR contacts leaf is exposed via the integration registry (ADR-019) — deferred to downstream cycle (handoff)
- [~] 1.2 Add the contacts leaf host to `src/views/ObjectDetail.vue` for the `player` object type — deferred to downstream cycle (handoff)

## 2. Person-data ownership
- [~] 2.1 Source/persist person attributes (email, phone, address, display name) through the contacts leaf, following the OR contacts schema (no parallel person fields) — deferred to downstream cycle (handoff)

## 3. In-game linkage retained
- [~] 3.1 Keep the Player ↔ character `ocName` linkage (PLR-006) and `players[]` participation in LarpingApp — deferred to downstream cycle (handoff)
- [~] 3.2 Verify character `ocName` references still resolve after adoption — deferred to downstream cycle (handoff)

## 4. Legacy migration
- [~] 4.1 Map legacy Player `name` → contact display name, `description` → contact notes (no data loss) — deferred to downstream cycle (handoff)

## 5. Graceful degradation
- [~] 5.1 Fall back to existing `{name, description}` fields when the contacts leaf / registry is unavailable — deferred to downstream cycle (handoff)

## 6. Tests
- [~] 6.1 Frontend test: contacts leaf renders person data — deferred to downstream cycle (handoff)
- [~] 6.2 Frontend test: `ocName` linkage still resolves — deferred to downstream cycle (handoff)
- [~] 6.3 Frontend test: legacy fallback when leaf absent — deferred to downstream cycle (handoff)
