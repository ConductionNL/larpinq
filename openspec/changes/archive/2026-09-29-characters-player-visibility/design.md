# Design: characters-player-visibility

## Context

Read at development `2af18d8`.

- The `character` schema (`lib/Settings/larpinq_register.json`, schema
  `character`, version 1.4.0) has no `authorization` block. `xpAward` has
  one (`{"create": ["gamemasters"], "update": ["gamemasters"], "delete":
  ["gamemasters"]}`), and `larping_attendance` in
  `lib/Settings/register.d/event-checkin-roster.json` has the same shape.
- `character.ownerUid` is `visible:false`, read-only, and materialised from
  `@ref.player.userUid` by `x-openregister-calculations`, through the
  `x-openregister-references.player` link on `ocName` (the `$ref` player
  dropdown). The `character-approved` notification already targets it.
- The private fields are plain properties with descriptions that promise a
  visibility nothing enforces: `background` ("visible to GMs only"),
  `slNotesPublic` ("visible to players"), `slNotesPrivate` ("NOT visible to
  players"). `visible:false` only hides a column from generic lists.
- `src/manifest.json` CharacterDetail: the `char-identity` data widget
  includes `background`; `char-progress` includes `slNotesPublic` and
  `slNotesPrivate`; `char-related` lists skills, items, conditions and events.
  The Characters index is a plain `index` page on schema `character`.
- App access is the Nextcloud group `larpers` (`appinfo/info.xml:111-133`); the
  game master tier is the one group `gamemasters` (ADR-002).
- OpenRegister's `row-field-level-security` spec (done) gives schema
  `authorization` rules of the form `{"group": "<g>", "match": {"<prop>":
  "$userId"}}` evaluated in SQL, property `authorization` blocks with the same
  rules, and stripping of unreadable fields from API responses and exports.
- `lib/Service/RegisterObjectFetcher.php` reads with the OpenRegister mapper
  and writes through `saveObject` with RBAC on (`:396-410`).

## Goals / Non-Goals

**Goals**

- A player reads and edits their own sheet and cannot read another sheet's
  private fields, on every read path.
- Private game master notes never reach a player.
- Players can browse the cast before an event.

**Non-Goals**

- Player skill purchases, player self-signup, the portal projection.

## Decisions

### D1. Row rules on the character schema

```json
"authorization": {
  "read":   [{"group": "gamemasters"},
             {"group": "larpers", "match": {"ownerUid": "$userId"}},
             {"group": "larpers", "match": {"approved": "approved"}}],
  "create": ["larpers"],
  "update": [{"group": "gamemasters"},
             {"group": "larpers", "match": {"ownerUid": "$userId"}}],
  "delete": ["gamemasters"]
}
```

The third read rule is the cast: a player can open another approved
character, and the field rules in D2 reduce it to a cast entry. Alternative
considered: a separate cast projection endpoint in larpinq; rejected because it
would duplicate what OpenRegister's field stripping already does on every
path, and a new controller is a new place for the rule to drift.

### D2. Field rules

| Field | Read | Update |
|---|---|---|
| `slNotesPrivate`, `requirementOverrides` | gamemasters | gamemasters |
| `background`, `faith` | gamemasters, owner | gamemasters, owner |
| `slNotesPublic`, `card`, `notice`, `itemsAndMoney` | gamemasters, owner | gamemasters |
| `gold`, `silver`, `copper`, `skills`, `items`, `conditions`, `events` | gamemasters, owner | gamemasters |
| `approved` | everyone who can read the row | gamemasters (and the lifecycle transitions) |
| `name`, `description` | everyone who can read the row | gamemasters, owner |
| `ocName`, `ownerUid` | gamemasters, owner | gamemasters (ownerUid is derived) |

"Owner" is the rule `{"group": "larpers", "match": {"ownerUid": "$userId"}}`.
`name`, `type`, `description` and the photo leaf are what a cast entry shows.
The player's real name (`ocName`) is not shown to other players: data
minimisation, and a LARP cast is about characters.

### D3. The cast page

A new index page `Cast` at `/cast` on schema `character`, filtered to
`approved = approved`, with columns name, type and description, sorted by
name, and the active world filter when `events-world-scope-and-upcoming` has
landed. Menu entry under the characters group. The Characters index keeps
working for game masters; for a player the row rules already reduce it to
their own characters plus cast entries, so no second index is needed for
them.

### D4. Server reads that need every field

| Path | Reads as | Effect of the rules |
|---|---|---|
| `EventsController::downloadRunsheet` (game master only) | calling user | a game master reads all fields, unchanged |
| `CharactersController::downloadPdf` | calling user | the owner gets the owner fields and no `slNotesPrivate`, which is what `player-character-sheet-access` asks for |
| `CharacterService::calculateAllCharacters()` / `calculateCharacter()` | calling user | a player's recalculation of their own sheet sees their own skills, items and conditions; the engine needs nothing from other sheets |
| `CharacterRequirementListener` | the write's user | validates the candidate object it is handed; no read of other sheets |

Each row gets a PHPUnit or Newman check in the tasks. If a path turns out to
need a system read, it uses OpenRegister's trusted internal read (field
stripping bypass for trusted reads, `row-field-level-security`), never a
disabled RBAC flag in a user-facing response.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Who reads and writes which character | Declarative, schema `authorization` | OpenRegister row level security expresses owner and group rules with `$userId`. |
| Which fields a player sees | Declarative, property `authorization` | OpenRegister field level security strips fields on every read path. |
| The cast list | Declarative, manifest index page | A filtered index over the same schema. |

No larpinq PHP is added.

## Seed data

No new schema. The demo data needs a player account in `larpers` linked to
player "Anna de Vries", who owns character "Mirela the Wanderer" (approved,
with an `slNotesPrivate` of "Secretly the heir of Aldmoor"), and a second
approved character "Sir Bertram" owned by another player. The Playwright test
logs in as Anna.

## Risks / Trade-offs

- [Existing players lose sight of other sheets] That is the point of the
  change; the release note says so.
- [Characters without an owner] Visible to game masters only until linked.
- [Group names are conventions] `larpers` and `gamemasters` are literals in
  the register (ADR-002 rule 3); the ADR's inventory gets the new occurrences.

## Migration

None. The rules apply on the next register import.

## Build notes (code at 6881f84, 2026-09-29)

The design held, with five corrections found while building:

- **Create includes `gamemasters`.** A game master is not necessarily in
  `larpers`, and D1's `create: ["larpers"]` would stop them creating
  characters. The row rule is `create: ["gamemasters", "larpers"]`.
- **`ownerRef` exists.** `register.d/portal-identity.json` adds it to
  `character`. It gets the owner read rule, so a cast entry does not carry it.
  `setting` stays readable: it is the world, and the world filter needs it.
- **Property rules on a create cannot match the owner.** OpenRegister checks a
  create's fields against an empty stored object, so `ownerUid = $userId` never
  matches. A player creating a character in the app can set name, type and
  description; background and faith follow after the first save. The portal's
  `createCharacter` writes with `_rbac: false` (portaliq `PortalObjectWriter`),
  so it is unaffected. Game masters create characters in the app as before.
- **D4, PDF.** `CharactersController::downloadPdf` is admin-only at HEAD
  (`testDownloadPdfReturns403ForNonAdminUser`), so no player path exists to
  strip. The run sheet stays game master only (`EventsControllerTest::
  testReturns403ForNonGm`). Both read through the mapper as before.
- **Menu.** Manifest fragments add top-level menu entries only, so Cast is a
  top-level entry at order 25, just below Characters.

## Open Questions

None.
