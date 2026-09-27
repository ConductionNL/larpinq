# Design: characters-plot-threads-and-writing

## Context

Read at development `2af18d8`.

- Story content today is free text on the character: `background`
  (description "visible to GMs only") and `slNotesPrivate` ("NOT visible to
  players"), plus `slNotesPublic`. None is hidden at read time (see
  `characters-player-visibility`).
- `character.approved` carries the schema's `x-openregister-lifecycle`
  (`no` to `approved`). A schema has one lifecycle block.
- The Character roster report (`src/manifest.json`, page
  `CharacterRosterReport`) already uses declarative chart widgets
  (`roster-by-approval`), so a count by writing step fits the same widget type.
- `format: "user"` renders a Nextcloud user picker (character-player-picker
  change, used by `player.userUid`).

## Goals / Non-Goals

**Goals**: a plot thread with per-character texts, hidden where it must be;
writer and step on plots and characters; one view of progress.

**Non-Goals**: configurable steps, scene scheduling.

## Decisions

### D1. Plot and plot part

- `plot` (slug `larping_plot`): `setting`, `title`, `summary`, `writer`
  (`format: user`), `writingStep` (`draft`, `ready`, `approved`), with an
  `x-openregister-lifecycle` on `writingStep` (`ready`: draft to ready,
  `approve`: ready to approved, `reopen`: back to draft). Read and write:
  `gamemasters` (and the story writer role when it exists).
- `plotPart` (slug `larping_plot_part`): `plot`, `character`, `privateText`,
  `playerText`, `ownerUid` (materialised from the character). Row read:
  `gamemasters`, and larpers where `ownerUid = $userId`. Property rule:
  `privateText` read by `gamemasters` only.

Alternative: plot texts as more free-text fields on the character. Rejected:
one plot touches many characters and needs one place to read it whole.

### D2. Writer and step on the character

`character.writer` (`format: user`) and `character.writingStep` (enum, default
`draft`). No lifecycle (the slot is `approved`'s); the enum is free to move.
Game masters write both (`characters-player-visibility` lists them with the
game-master-only fields). The bulk edit of `characters-status-and-bulk-edit`
offers both fields.

### D3. Pages and widgets

- Fragment: `Plots` index (title, writer, step, world) and `PlotDetail` with
  an object-list of `plotPart` (character, player text shown, private text
  shown to game masters).
- CharacterDetail (in place): writer and writing step in the "Game state &
  notes" widget; an object-list "Plots" of `plotPart` filtered on `character =
  @objectId`.
- CharacterRosterReport (in place): a chart widget counting characters by
  `writingStep`, and an object-table of characters not yet `approved` in
  `writingStep`, sorted by writer.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Plot, plot part, fields | Declarative, register fragment | Properties and relations. |
| Writing steps of a plot | Declarative, `x-openregister-lifecycle` | A state machine on one field. |
| Hiding private texts | Declarative, row and property rules | OpenRegister strips per property. |
| Progress counts | Declarative, chart and table widgets | Aggregations over one schema (hydra ADR-031, ADR-058). |

No PHP.

## Seed data

Plot "The heir of Aldmoor" (writer: game master Joris, step ready) with parts
for "Mirela the Wanderer" (private: "She is the lost heir; the guard captain
knows"; player: "You dream of a crown you never wore") and "Sir Bertram"
(private: "Sworn to find the heir and bring her to the queen"). Characters:
"Mirela the Wanderer" writer Joris, step approved; "Tomas" writer Sanne, step
draft.

## Risks / Trade-offs

- [Two step fields] Characters and plots carry the same enum; a later change
  can make steps configurable for both at once.

## Migration

None. Existing characters read as `draft` with no writer.

## Open Questions

None.
