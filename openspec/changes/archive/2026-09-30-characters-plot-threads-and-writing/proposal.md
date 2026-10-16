---
kind: config
depends_on: []
---

# Proposal: characters-plot-threads-and-writing

## Summary

A story team keeps its secrets and plot threads in two free-text fields on
each character and tracks who writes what in a spreadsheet. This change adds
plots: a story thread with the characters it touches, a private text per
character that game masters see and a text the player may see, an assigned
writer, and writing steps (draft, ready, approved). Characters get the same
writer and writing step, so a game master sees which sheets are still
unwritten before an event.

## Motivation

Two characters rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Characters is a
core area; the OpenSpec pass of 2026-09-27 decided `build` for both.

**`chr-secrets-plots`**, "Attach secrets and personal plot threads to a
character for the story team." Larpinq rates it partial, built. Matrix
evidence: "lib/Settings/larpinq_register.json:82-90 (background, GM-only story
field) and :148-155 (slNotesPrivate, GM-only notes field), both free-text,
GM-facing fields on the character schema; no dedicated 'plot thread' entity or
list exists". The row note: "there is no structured, trackable plot-thread
list, and (per the chr-gm-notes finding) nothing actually keeps these fields
hidden from the player at read time." Two competitors rate it yes, one partial:

- LarpManager (yes): "larpmanager/models/writing.py:533-590 Plot and PlotCharacterRel with per-character text, larpmanager/urls/orga.py:701 orga_plots" (source read at main 36f23d3).
- Kanka (yes): "posts with admin/self visibility on a character (app/Models/Post.php:77-89); quests with elements linking characters (routes/campaigns/entities.php:336-337)" (source read at tag 3.15).
- LARP Portal (partial): "Staff 'Character List' report tracks 'hidden skills' per character ... a dedicated secrets/plot feature is not documented. https://larportal.com/larp-portal-tips.php"

The missing half is the structured plot thread; hiding the existing fields is
`characters-player-visibility`.

**`chr-writing-progress`**, "Track the writing progress of each character or
plot through steps such as draft, ready and approved, and assign a writer."
Larpinq rates it no. Matrix evidence: "character.approved is a yes/no switch
(lib/Settings/larpinq_register.json:100-111); no writer assignment or writing
steps". One competitor rates it yes:

- LarpManager: "larpmanager/models/writing.py:97-111 Writing.progress and assigned staff member, larpmanager/urls/orga.py:1161 orga_progress_steps, progress widget larpmanager/cache/widget.py:229" (source read at main 36f23d3). Its changelog: https://github.com/LoSkana/larpmanager/commit/5835a17035.

Writing steps and writers apply to plots and characters alike; one change keeps
them the same on both.

## Affected Projects

- [ ] Project: `larpinq`: two schemas (plot, plot part), writer and writing step on the character, pages and a report widget.

## Scope

### In Scope

- `plot` per world: title, summary, writer, writing step, and its parts.
- `plotPart` linking a plot to a character with a private text (game masters) and a player text (game masters and the character's owner).
- On `character`: `writer` (a Nextcloud user) and `writingStep` (`draft`, `ready`, `approved`), set by game masters.
- A Plots index and detail page; on the character page the plot parts that touch the character.
- A writing progress widget on the Character roster report: characters and plots per step, per writer.

### Out of Scope

- Merging the writing step with `approved`: approval stays the game master's release of a character for play; the writing step is the story team's workflow.
- Configurable step names per group. The three steps are fixed in this change.
- Plot scheduling within an event (row `evt-schedule-within-event`, deferred).

## Approach

Declarative: schemas, lifecycles and read rules in a register fragment, new
pages in a manifest fragment, widgets as declarative aggregations. Details in
design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/characters-plot-threads-and-writing.json` (new).
- `src/manifest.d/characters-plot-threads-and-writing.json` (new): Plots pages and menu entry.
- `src/manifest.json`: CharacterDetail gains the plot parts list and the writer and step fields; the Character roster report gains the progress widget (existing pages, edited in place).

## Cross-Project Dependencies

OpenRegister row and field level security, lifecycles and aggregations.

## Risks

### Risk 1: The player text and the private text get mixed up
**Severity:** Medium. **Mitigation:** two separate properties with separate read rules; the form labels say who reads each, and the player text is empty by default.

### Risk 2: A writer who is not a game master
**Severity:** Low. **Mitigation:** writers are Nextcloud users; to read private texts they must be in `gamemasters` or in a story writer role once `admin-staff-roles-and-invites` lands. The form warns when the chosen writer has no such role.

## Rollback Strategy

Remove the fragments and the in-place page edits. Objects stay as data.

## Open Questions

None.
