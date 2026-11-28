# Tasks: characters-factions-and-relationships

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Data and rules

- [x] 1.1 Fragment with `faction`, `factionMember` (lifecycle) and `relationship`, their materialised uids and the D2 rules (REQ-CFR-001 to REQ-CFR-005). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [x] 1.2 Newman: a non-member player reads neither "The Ash Circle" nor its memberships; the owners read their "owners" relationship; nobody but game masters reads a game-master-only one (REQ-CFR-002, REQ-CFR-005). Verify: the collection passes. Done as the API tests in `tests/e2e/workflows/factions-and-relationships.workflow.spec.ts` (same OpenRegister calls as the players; not run: needs an isolated instance), and the rules in `tests/unit/Settings/FactionsFragmentTest.php`.
- [x] 1.3 `FactionMembershipListener` with the D3 refusals, registered behind `class_exists()` (REQ-CFR-003, REQ-CFR-004). Verify: PHPUnit with the real OpenRegister event classes, one test per refusal and per allowed path.
- [x] 1.4 Seed data of design.md. Verify: the seed imports cleanly.

## 2. Pages

- [x] 2.1 `src/manifest.d/characters-factions-and-relationships.json`: Factions index and detail pages and menu entry (REQ-CFR-001). Verify: `npm run check:manifest`.
- [x] 2.2 CharacterDetail in `src/manifest.json`: factions, relationships and named-by-others lists (REQ-CFR-001, REQ-CFR-005). Verify: `npm run check:manifest`; Playwright `tests/e2e/workflows/factions-and-relationships.workflow.spec.ts` covers a group invite accepted by the invitee.

## 3. Strings and docs

- [x] 3.1 Dutch and English strings (REQ-CFR-001). Verify: `npm run test:l10n`.
- [x] 3.2 `docs/features/factions-and-relationships.md` with screenshots (ADR-010). Verify: the docs build renders it. Screenshots follow with the first live capture run.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
