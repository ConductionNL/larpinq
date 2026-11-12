# Proposal: capability-rows-missing-requirements

## Summary

Six rows of the larpinq capability matrix (`openspec/parity/capabilities.json`) cite a spec that has no requirement saying what the row says. This change writes those requirements, so every row records a requirement whose scenarios describe it. Four of the rows are already built and only lack the requirement. Two are only partly built: they are rated `specified` and get build tasks.

## Motivation

The fleet spec coverage measure of 11 Nov 2026 counts a row as covered only when its `built.requirement` names a requirement heading that exists. These rows had none to name:

| Row | What the row says | State | What this change adds |
|---|---|---|---|
| `wld-world-description` | Describe a world's setting and tone for players. | built | The world page shows its description. |
| `wld-timeline` | Keep a timeline of in-game history and past events. | specified | Dated history entries per world, shown with the world's events in date order. Today the world page only lists its real events by start date. |
| `ply-link-user` | Link a player to their Nextcloud user account. | built | The player record holds the account (`userUid`). |
| `ply-characters-per-player` | See every character a player has played. | built | The player page lists the player's characters. |
| `evt-milestones` | Track the organising milestones of an event with a status and a deadline. | built | Milestones are Deck cards linked to the event, with a stack for status and a due date. |
| `rul-in-game-currency` | Track in-game money per character and let players spend it on in-game items. | specified | Items get a price and buying one takes the money from the character. Today money is tracked but nothing spends it. |

`prg-gm-override` is rated back to `specified` too: the server accepts and audits an override (skill-requirement-enforcement, GM override MUST be explicit and audited), but no page writes `requirementOverrides`, so a game master can only add one through the API. Task 5.1 adds the action (REQ-SRE-001).

`prt-runsheet` is also rated back to `specified`: its requirement exists (pdf-export RUN-020), but the event page has no download action, so a game master can only reach the run sheet by typing its URL. Task 4.1 adds the action.

## Affected Projects

- [ ] Project: `larpinq`: requirements for six rows, build work for the timeline and for buying items with in-game money.

## Impact

- Spec only for the four built rows: their behaviour exists and the scenarios describe it.
- `wld-timeline` and `rul-in-game-currency` need a schema and page change, both on the OpenRegister data path. Buying an item changes no ability, so the stat engine is untouched.
