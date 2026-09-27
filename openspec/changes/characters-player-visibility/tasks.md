# Tasks: characters-player-visibility

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Rules

- [ ] 1.1 `lib/Settings/register.d/characters-player-visibility.json` with the character `authorization` block of D1 (REQ-CPV-001, REQ-CPV-002). Verify: `npm run check:register`; Newman as a player lists only own and approved characters, and a PUT on another player's character is refused.
- [ ] 1.2 Property `authorization` blocks of D2 in the same fragment (REQ-CPV-003, REQ-CPV-004). Verify: Newman as a player on their own character gets no `slNotesPrivate`; on another approved character gets only name, type, description and approved; a CSV export as a player carries no private column.
- [ ] 1.3 Add the new `gamemasters` and `larpers` occurrences to the inventory in `openspec/architecture/adr-002-gm-authorization-single-group.md`. Verify: the inventory lists the fragment.

## 2. Cast page

- [ ] 2.1 `src/manifest.d/characters-player-visibility.json` with the `Cast` index page and its menu entry (REQ-CPV-005). Verify: `npm run check:manifest`; Dutch and English labels via `npm run test:l10n`.

## 3. Proof

- [ ] 3.1 PHPUnit or Newman for each server path in the D4 table (REQ-CPV-006). Verify: the run sheet for a game master still carries `slNotesPrivate`; a player's own PDF context does not.
- [ ] 3.2 Playwright `tests/e2e/player-visibility.spec.ts` as player Anna: edits her own background, cannot open the private note, sees "Sir Bertram" on the Cast page without his background (REQ-CPV-001 to REQ-CPV-005). Verify: passes locally.
- [ ] 3.3 `docs/features/player-visibility.md`: who sees what, with a screenshot of the Cast page (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push; seed the two demo players of design.md.
