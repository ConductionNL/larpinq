---
kind: config
depends_on: []
---

# Proposal: characters-player-visibility

## Summary

A player opens larpinq and sees every character in the campaign in full,
including the game master's private notes about their own character. This
change gives the character schema read and write rules: a player edits their
own sheet, sees the other characters only as a short cast entry, and never
sees a game master note marked private. Game masters keep full access. It
also adds a cast page, so players can study the other characters before an
event.

## Motivation

Three characters rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Characters is a
core area of the matrix (the area of its first 30 rows); the OpenSpec pass of
2026-09-27 decided `build` for all three.

**`chr-player-edits-own`**, "Let a player edit their own character sheet
online without seeing other players' sheets." Larpinq rates it partial,
built. Matrix evidence: "character schema (lib/Settings/larpinq_register.json:44-346)
has no 'authorization' block (contrast xpAward's at :959-971 which restricts
create/update/delete to gamemasters) so any authenticated larpinq user can read
or edit ANY character, not only their own". Reached on: "CharacterDetail page
(/characters/:id), a player can indeed edit their own sheet there, but the same
page and the Characters index equally expose every other player's sheet".
Four competitors rate it yes:

- LarpManager: "larpmanager/fixtures/feature.yaml:818 user_character feature, larpmanager/urls/event.py:99 character_edit guarded by get_char_check (larpmanager/views/user/character.py), private field visibility larpmanager/forms/character.py:1397" (source read at main 36f23d3).
- MyLARP: "Player-facing 'character creation and management' implies players edit their own sheet online. https://mylarp.com"
- LARP Portal: "Overview explicitly lists 'Self-serve character updates.' https://larportal.com"
- Kanka: "per-user entity permissions routes/campaigns/entities.php:414-415; app/Enums/Permission.php View/Update; private entries hidden from other members" (source read at tag 3.15).

**`chr-gm-notes`**, "Keep game master notes on a character that the player
does not see." Larpinq rates it partial, built. Matrix evidence:
"lib/Settings/larpinq_register.json:141-155 (slNotesPublic 'visible to
players', slNotesPrivate 'NOT visible to players', both visible:false at the
schema level); src/manifest.json:660-680 (char-progress widget explicitly lists
BOTH slNotesPublic and slNotesPrivate with no role check); grep -rn
'x-property-rbac' lib/: no hits". The row note calls it a live-defect
candidate: "nothing (no property RBAC, no per-role widget) actually hides
slNotesPrivate from a player who opens their own character's detail page."
Two competitors rate it yes:

- LarpManager: "larpmanager/forms/character.py:1394-1399 custom sheet fields with visibility Hidden 'hidden to all participants', larpmanager/models/form.py:164-170".
- Kanka: "posts with visibility admin/self (app/Models/Post.php:77-89, app/Enums/Visibility.php); lang/en/onboarding/posts.php GM-only secrets".

**`chr-learn-other-characters`**, "Let players study the other characters
before the event, as a book, cards or a quick list." Larpinq rates it no.
Matrix evidence: "src/manifest.json character pages are GM and owner views; a
player sees their own sheet (ply-player-sees-own-sheet), and no cast book or
card view of other characters exists". Two competitors rate it yes:

- LarpManager: "larpmanager/fixtures/feature.yaml:1631 ensemble feature, larpmanager/urls/event.py:52-54 ensemble page" (source read at main 36f23d3). Its changelog: https://github.com/LoSkana/larpmanager/commit/771e21d700.
- Kanka: "character list as cards or table (resources/views/cruds/datagrids/_grid.blade.php) limited to what each member may see, and a printable bundle of selected entries (app/Http/Controllers/Bulks/PrintController.php:20-30, routes/campaigns/bulks.php:33)".

The three rows share one mechanism: who may read and write which character,
and which fields. That is why they are one change.

## Affected Projects

- [ ] Project: `larpinq`: a register fragment with the character schema's row and field rules, and a manifest fragment with the cast page.

## Scope

### In Scope

- Row rules on `character`: game masters read and write every character; a player reads and updates the characters whose `ownerUid` is their own user id, and reads approved characters of other players as cast entries; only game masters delete.
- Field rules on `character`: `slNotesPrivate` and `requirementOverrides` are read and written by game masters only; `background`, `slNotesPublic`, `card`, `faith`, `gold`, `silver`, `copper`, `itemsAndMoney`, `notice`, `skills`, `items`, `conditions` and `events` are read by game masters and the owner only.
- Field write rules: a player may change `name`, `description`, `background` and `faith` on their own character; everything else is written by game masters.
- A cast page listing the approved characters with name, type, description and portrait.

### Out of Scope

- Players buying skills themselves (row `prg-player-spends-xp`, deferred).
- The portal view for players without a Nextcloud account: `portal-contribution` projects its own fields.
- Per-character sharing beyond owner and game master.

## Approach

Declarative only. A fragment `lib/Settings/register.d/characters-player-visibility.json`
adds an `authorization` block to the `character` schema and property
`authorization` blocks on the listed fields, using OpenRegister's row and
field level security (`match` on `ownerUid` with `$userId`). A fragment
`src/manifest.d/characters-player-visibility.json` adds the cast page. See
design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/characters-player-visibility.json` (new).
- `src/manifest.d/characters-player-visibility.json` (new): the Cast page and its menu entry.
- `openspec/architecture/adr-002-gm-authorization-single-group.md`: the new declarative occurrences of `gamemasters` added to its inventory (its rule 3).
- Every read of a character through OpenRegister (pages, REST, GraphQL, exports, MCP) now honours the rules.

## Cross-Project Dependencies

OpenRegister row and field level security (openregister spec
`row-field-level-security`, status done): schema `authorization` rules with
`group` and `match`, the `$userId` variable, property `authorization`, and
field stripping on every read path including exports.

## Risks

### Risk 1: A character without an owner disappears for its player
**Severity:** Medium. **Mitigation:** `ownerUid` is derived from the linked player's Nextcloud account (`x-openregister-calculations` on `character`). A character whose player has no account is visible to game masters only; the cast page and the game master's Characters index show it with an empty player, so a game master can link it.

### Risk 2: Server code that reads characters as the user loses fields
**Severity:** Medium. **Mitigation:** `CharacterService`, `EventRosterService` and the PDF path read through `RegisterObjectFetcher`. Design D4 lists each and whether it reads as the user or as the system; the tasks include a PHPUnit check that the run sheet, which is game master only, still gets `slNotesPrivate`.

### Risk 3: Players who were game masters by habit lose access
**Severity:** Low. **Mitigation:** the rule uses the one GM group `gamemasters` (ADR-002). The release note tells administrators to put their game masters in it before upgrading.

## Rollback Strategy

Delete the two fragments and re-run the register import. The schema goes back
to open access.

## Open Questions

None.
