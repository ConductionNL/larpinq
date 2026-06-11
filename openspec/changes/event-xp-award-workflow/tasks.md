# Tasks — event-xp-award-workflow

## 1. Schema + RBAC

- [ ] 1.1 Add the `xpAward` schema to `lib/Settings/larpingapp_register.json`: `event` (uuid, required), `character` (uuid, required), `amount` (number, exclusiveMinimum 0, required), `reason` (string, optional), `awardedBy` (string), `awardedAt` (date-time); register it in the register's schema list and `src/store/store.js` SCHEMA_SLUGS
- [ ] 1.2 Configure OpenRegister schema-level RBAC on `xpAward` in the register config: create/update/delete restricted to the GM group (`gamemasters`, aligned with `larpingapp-notifications`); read for authenticated app users — no app-local authorization code (ADR-022)
- [ ] 1.3 Server-side stamping of `awardedBy`/`awardedAt` (ignore/overwrite client-supplied values); add `docs/Schema/XpAward.json`

## 2. Stat engine

- [ ] 2.1 Preload xpAwards into an indexed map keyed by character in `CharacterService` (CHAR-080..082 pattern)
- [ ] 2.2 Apply awards as the fifth stage in `calculateCharacter()` after skills → items → conditions → events: sum each award's amount onto the XP ability with audit entries `{type: "xpAward", award: {...}, old, new}`; existing four-stage arithmetic and audit ordering byte-identical (CALC-002 extension, CALC-008)
- [ ] 2.3 Resolve the XP ability via the app register config with name-match fallback — share one resolver with `skill-requirement-enforcement` (whichever lands first), no hardcoded UUIDs
- [ ] 2.4 Graceful skips (CALC-009): unresolvable character → award ignored; missing event → award still counts; never throw
- [ ] 2.5 Trigger recalculation of the affected character on xpAward create/update/delete (CHAR-045 analogue; OR object events scoped to the xpAward schema)

## 3. Event detail awarding UI

- [ ] 3.1 GM-only "Award XP" tab/action on the event detail page (visibility = presentation; enforcement = schema RBAC): roster of characters whose `events[]` contain the event, with linked player name where available
- [ ] 3.2 Batch modal in `src/modals/` (modal-isolation gate): default amount, checkbox per row, per-row amount override + optional reason; save creates one xpAward per checked character; rows with an existing award for this event pre-unchecked
- [ ] 3.3 Inline existing-awards list per event (character, amount, reason, awardedBy) with edit + delete (corrections as normal object updates)
- [ ] 3.4 Surface awards in the character's XP provenance (audit-trail entries of type "xpAward" rendered with event name and reason)

## 4. Quality

- [ ] 4.1 Annotate new/changed methods with `@spec openspec/changes/event-xp-award-workflow/...` (gate-16) and SPDX headers (gate-1); no redundant pass-through controllers (gate-17 — plain OR object CRUD for awards)
- [ ] 4.2 i18n: English source keys + nl translations (ADR-007/ADR-025)
- [ ] 4.3 Bump `appinfo/info.xml` `<version>` (immutable-cache bust)

## 5. Tests

- [ ] 5.1 PHPUnit `CharacterServiceTest` additions: fifth-stage arithmetic + audit entry shape/order, four-stage regression (existing CALC scenarios unchanged), dangling character/event award handling, XP-ability resolution via config + name fallback
- [ ] 5.2 PHPUnit: recalculation trigger on award create/update/delete
- [ ] 5.3 Newman (`tests/integration/*.postman_collection.json`): GM creates award (201, server-stamped awardedBy/awardedAt); non-GM create → 403; amount 0/negative → validation error; award visible on character XP after recalc (API assertions belong in Newman, not Playwright)
- [ ] 5.4 Playwright `tests/e2e/spec-coverage/`: batch award flow (default + per-row override + reason), re-open shows existing awards with unchecked rows, edit/delete correction flow, non-GM does not see the surface, character XP provenance shows the award (backend-only scenarios get `@e2e exclude` on their own line)
- [ ] 5.5 `composer check:strict` green; run hydra gates (incl. gate-16, gate-19) — fix any pre-existing issues encountered in touched files

## 6. Spec sync

- [ ] 6.1 On archive, sync the `event-xp-awards` capability spec into `openspec/specs/` and the rpg-system delta (CALC-002 order extension, CALC-008/009)
- [ ] 6.2 Update `docs/FEATURES.md` ("experience score" now backed by a real awarding workflow)

## Acceptance criteria

- A GM can batch-award per-participant XP from the event detail page, with per-row amounts and reasons; one auditable `xpAward` record per grant, server-stamped grantor/timestamp.
- Non-GM writes to `xpAward` are rejected server-side via OR schema RBAC; players can read their characters' award provenance.
- `calculateCharacter()` sums awards onto the XP ability as a fifth stage with `xpAward` audit entries; all pre-existing CALC arithmetic and audit ordering is unchanged; dangling references never crash.
- Award create/update/delete immediately recalculates the affected character, and the computed XP feeds the `skill-requirement-enforcement` budget by construction.
- All new strings ship in en + nl; PHPUnit, Newman, and Playwright coverage as in section 5; `composer check:strict` and hydra gates pass.
