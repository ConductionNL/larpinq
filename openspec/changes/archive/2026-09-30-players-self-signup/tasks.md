# Tasks: players-self-signup

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Data and contribution

- [x] 1.1 Fragment: `player.portalSubjectRef`, `selfRegistered`, `reviewedAt`, `reviewedBy` (REQ-PSS-001, REQ-PSS-004). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [x] 1.2 `createPlayerProfile` and `myProfile` in `PortalContributionProvider` (REQ-PSS-001). Verify: the provider's PHPUnit manifest-shape tests extended; the field whitelist test covers the new entries.

## 2. Linking and rules

- [x] 2.1 `PlayerProfileClaimListener` dispatching portaliq's claim event (REQ-PSS-002). Verify: PHPUnit with the real `ObjectCreatedEvent` and a spy dispatcher; `git grep` in portaliq finds the listener for the event before this task is closed.
- [x] 2.2 One profile per portal account (REQ-PSS-003). Verify: PHPUnit: a second create is refused.

## 3. Pages

- [x] 3.1 `src/manifest.d/players-self-signup.json`: New players page with "Mark reviewed" (REQ-PSS-004). Verify: `npm run check:manifest`; Playwright `tests/e2e/new-players.spec.ts` marks "Lotte Bakker" reviewed.

## 4. Strings and docs

- [x] 4.1 Dutch and English strings (REQ-PSS-004). Verify: `npm run test:l10n`.
- [x] 4.2 `docs/features/player-self-signup.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push; a live portal run once portaliq's claim event exists.
