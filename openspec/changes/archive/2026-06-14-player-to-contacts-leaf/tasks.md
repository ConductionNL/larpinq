---
status: implemented
---

# Tasks

## 1. Integration wiring
- [x] 1.1 Confirm the OR contacts leaf is exposed via the integration registry (ADR-019) — consumer-only check against `window.OCA.OpenRegister.integrations.isRegistered('contacts')`
- [x] 1.2 Add the contacts leaf host to `src/views/ObjectDetail.vue` for the `player` object type — `ObjectDetail.vue` renders `[data-integration-host="contacts"]` for player objectType when the registry exposes the leaf; manifest `PlayerDetail` slot `contacts-leaf: ObjectDetail`

## 2. Person-data ownership
- [x] 2.1 Source/persist person attributes (email, phone, address, display name) through the contacts leaf, following the OR contacts schema (no parallel person fields) — `player` schema linkedTypes fragment (`lib/Settings/register.d/player-to-contacts-leaf.json`) declares the binding; person attributes are owned by the OR contacts abstraction

## 3. In-game linkage retained
- [x] 3.1 Keep the Player ↔ character `ocName` linkage (PLR-006) and `players[]` participation in LarpingApp — `ocName` and `players[]` remain on the player/character OR schemas; only person attributes move to the contacts leaf
- [x] 3.2 Verify character `ocName` references still resolve after adoption — `ocName` stays a Player field; character detail rendering is unchanged by this leaf

## 4. Legacy migration
- [x] 4.1 Map legacy Player `name` → contact display name, `description` → contact notes (no data loss) — the contacts leaf reads the legacy fields on first open and maps them into the contact record; the OR fields are preserved until a structured contact is confirmed

## 5. Graceful degradation
- [x] 5.1 Fall back to existing `{name, description}` fields when the contacts leaf / registry is unavailable — `availableLeaves` returns no entries when registry/leaf absent; the standard OR detail rendering of `name`/`description` continues unchanged

## 6. Tests
- [x] 6.1 Frontend test: contacts leaf renders person data — `tests/e2e/spec-coverage/spa-ui.spec.ts` `player-to-contacts-leaf` describe block
- [x] 6.2 Frontend test: `ocName` linkage still resolves — same describe block, "character ocName still resolves after contacts adoption" scenario
- [x] 6.3 Frontend test: legacy fallback when leaf absent — same describe block, "contacts leaf hidden when integration registry absent" scenario
