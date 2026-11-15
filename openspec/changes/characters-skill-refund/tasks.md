# Tasks: characters-skill-refund

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 2. -->

## 0. Record what is built

- [x] 0.1 `openspec/parity/capabilities.json`: prg-refund-skill cites this change and REQ-CSP-005 (built: `CharacterService::calculateCharacter()` recomputes from the skills linked now; `CharacterStatsPresenter` derives earned, spent and left).

## 1. Refund line in the XP history

- [ ] 1.1 `lib/Service/CharacterStatsPresenter.php` and `lib/Controller/CharacterStatsController.php`: an `xp.history` list of skill removals, read from the character's OpenRegister audit trail (a `skills` id gone between two versions), with the skill's XP cost, the date and the user (REQ-CSP-006). Verify: `tests/unit/Controller/CharacterStatsControllerTest.php` (a removal yields one refund line; a recompute adds no XP).
- [ ] 1.2 `src/views/CharacterStatSheet.vue`: the XP history list with "{skill} verwijderd: {xp} XP teruggegeven"; strings in `l10n/nl.json` and `l10n/en.json` (REQ-CSP-006). Verify: `tests/vitest/characterStats.spec.js`; `npm run test:l10n`.
