# Tasks: events-casting-and-crew-roles

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Data

- [ ] 1.1 Fragment: `character.castingEvent`, `event.castingDeadline`, `castingPreference`, `crewRole`, `crewAssignment` with rules (REQ-ECC-001, REQ-ECC-004). Verify: `npm run check:register`; `npm run check:schema-l10n`; Newman: a player reads only their own preferences.
- [ ] 1.2 Seed the casting and crew data of design.md. Verify: the seed imports cleanly.

## 2. Casting

- [ ] 2.1 `CastingService::propose()` with the Hungarian method and the tie rules (REQ-ECC-002). Verify: PHPUnit on the seed (Anna The Heir, Pieter The Bard, Sanne The Spy), on an unmatched player, and a 500 by 500 timing bound.
- [ ] 2.2 `CastingController` proposal and confirm endpoints, game masters only (REQ-ECC-002, REQ-ECC-003). Verify: PHPUnit for 403 as a player; confirm writes ownership and the registration's character; route-auth, no-admin-idor and semantic-auth gates pass.

## 3. Pages

- [ ] 3.1 `src/views/EventCasting.vue` as a registered section and the Casting tab on EventDetail in `src/manifest.json`; ranking on My registrations (REQ-ECC-001 to REQ-ECC-004). Verify: `npm run check:manifest`; vitest for the proposal table.
- [ ] 3.2 Playwright `tests/e2e/event-casting.spec.ts`: three players rank, the game master proposes and confirms, Anna owns "The Heir" (REQ-ECC-001 to REQ-ECC-003). Verify: passes locally.

## 4. Strings and docs

- [ ] 4.1 Dutch and English strings (REQ-ECC-001). Verify: `npm run test:l10n`.
- [ ] 4.2 `docs/features/casting-and-crew.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
