# Design — event-runsheet-export

## Context

pdf-export (PDF-001..063) already defines the whole rendering stack for
character sheets: DocuDesk `PdfService`/`TemplateService` resolved via the
DI container, templates filtered by `namespace=larpingapp`, Twig data
context, `DataDownloadResponse`, dependency check with hidden-button +
424 degradation. The run-sheet is the same stack with an event-shaped
context and a stricter audience. The wave constraint is explicit: extend
pdf-export, do not re-implement.

## Decision

### 1. Same pipeline, second surface

`EventsController.downloadRunsheet(string $id, string $template)` mirrors
`CharactersController.downloadPdf()` one-to-one: resolve DocuDesk services
via the container, fetch the template, render with format/orientation from
template metadata, return `DataDownloadResponse`. The DocuDesk dependency
check and DI resolution move into a small shared helper (used by both
controllers) instead of copy-paste — the only refactor this change makes to
existing code, behaviour-neutral for PDF-001..010.

### 2. Templates separated by category, not by namespace

Run-sheet templates live in DocuDesk under the existing
`namespace=larpingapp` with `category=runsheet` (character sheets:
`category=charactersheet`, with uncategorised legacy templates treated as
character sheets for backwards compatibility). One namespace keeps DocuDesk
administration per-app; the category keeps the two pickers clean. A starter
run-sheet Twig template is seeded so the feature works out of the box.

### 3. GM-only, server-side

The character sheet is player-facing; the run-sheet is not. It aggregates
approval status, GM notes (`slNotesPublic` **and** `slNotesPrivate`), and
the whole cast — exactly the data a player must not casually pull for
another team. The endpoint therefore checks GM-group membership in the
controller (the route stays `#[NoAdminRequired]` — GMs are not NC admins —
with an explicit group guard in the body, satisfying gate-9
semantic-auth), returning 403 for non-GMs. The event-detail action is
rendered for GMs only as presentation sugar over that server check.

Including `slNotesPrivate` is deliberate: the run-sheet is the GM's own
field document. A player-safe cast list variant is a template decision
(a template that omits the GM-note variables), not an authorization split.

### 4. Context derived from the existing link model

`cast[]` = characters whose `events[]` contain the event (the same
back-reference the relations tab uses — CHAR-043/EVT-006). Per entry:
character name, type, approval status, linked player name (via `ocName` /
player object where resolvable, empty otherwise — never an error), computed
`stats` (as stored by the engine, PDF-006 analogue — the controller does
not recalculate), conditions (names), unique items (names, flagged), GM
notes. Sorted by character name for door-list scanning. Event block: name,
start/end dates, location, ambient effects, `castCount`, plus a
`uniqueItemsInPlay[]` rollup (item → carrying character) for the props
table. Stale stats are accepted (same trade-off as the character sheet);
the GM recalculates from the character page if needed.

### 5. Degradation identical to character sheets

DocuDesk absent → the run-sheet action is hidden and direct endpoint access
returns 424 with the same error shape (PDF "DocuDesk Dependency Check"
requirement, reused via the shared helper). No templates with
category=runsheet → the modal explains and points at DocuDesk template
management.

## Alternatives considered

- **New capability spec (`event-runsheet`)** — rejected: it would duplicate
  pdf-export's dependency/degradation/template requirements nearly verbatim;
  this is the same capability rendering a second document type.
- **Client-side PDF (jsPDF/print CSS)** — rejected: forks the print stack,
  loses Twig templating and DocuDesk management, contradicts PDF-001..005.
- **Zip of all character sheets per event** — rejected: that's 40 pages,
  not a cast list; it answers a different need and can be a later
  convenience action.
- **Player-visible run-sheet with field-level redaction** — rejected:
  authorization complexity for no demand; a player-safe variant is just a
  template without GM-note variables, printable by the GM.
- **Timetable/scene schedule inside the run-sheet** — rejected: the app has
  no scene model; smuggling one into a PDF context would be spec-by-
  side-effect. Separate future capability.

## Risks

- **Large casts** — rendering N cast entries is linear template work in
  DocuDesk; stats are read as stored (no N recalculations). Hundred-player
  events produce a long but unremarkable PDF.
- **Player-name resolution gaps** — characters without a linked player
  render an empty player cell (explicitly specced); no join failure modes.
- **Shared-helper refactor touching downloadPdf** — behaviour-neutral by
  contract; the existing pdf-export PHPUnit/Newman assertions are the
  regression net.
- **Template seeding idempotency** — seed only when no larpingapp
  runsheet-category template exists; never overwrite GM-edited templates.
