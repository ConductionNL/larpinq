---
status: draft
---

# Printable GM run-sheet / cast list per event

## Why

On the event weekend the GM team works from paper (or a tablet PDF): who is
on the field, which player belongs to which character, what can each
character do, which conditions and unique items are in play. Every
established LARP ops practice has this document — the run-sheet / cast list —
and the app already holds every byte of it: events with rosters
(`events[]`/`players[]`), characters with engine-computed stats, conditions,
unique items, and GM notes. Yet the only print surface is the per-character
sheet (pdf-export). Printing 40 character sheets is not a cast list; nobody
cross-references 40 pages at the door.

The pdf-export capability explicitly delegates rendering to DocuDesk and the
constraint for this wave is to **extend that spec rather than re-implement
PDF plumbing** — this change adds the event-level export on the exact same
pipeline (DI-resolved `PdfService`/`TemplateService`, `namespace=larpingapp`
templates, graceful 424 degradation).

## What Changes

- **Event run-sheet endpoint.** `EventsController.downloadRunsheet()` at
  `GET /events/{id}/runsheet/{template}` renders a PDF via DocuDesk exactly
  like `CharactersController.downloadPdf()` (PDF-001..010 pattern):
  templates from DocuDesk with `namespace=larpingapp`, category `runsheet`
  (so character-sheet and run-sheet templates don't pollute each other's
  pickers), `DataDownloadResponse`, filename `{eventName}_runsheet.pdf`.
- **GM-only.** Unlike the character sheet, the run-sheet aggregates
  GM-facing material (approval status, GM notes incl. `slNotesPrivate`,
  whole-cast overview), so the endpoint is restricted server-side to the GM
  group and the UI action is shown to GMs only.
- **Run-sheet data context.** `event` (name, dates, location, ambient
  effects) + `cast[]`: one entry per participating character (derived from
  the character↔event link) with character name, linked player name, type,
  approval status, computed stats, conditions, unique items, and GM notes —
  sorted by character name. Plus `castCount` and the unique-items-in-play
  rollup for the props table.
- **UI flow.** A "Download run-sheet" action on the event detail page
  opens a template-picker modal (mirroring `RenderPdfFromCharacter.vue`) and
  opens the PDF in a new tab. Hidden when DocuDesk is absent; direct access
  then returns 424 — the existing dependency-check pattern, reused.

## Impact

- Affected specs: `pdf-export` (ADDED requirements RUN-001.. — same
  capability, event surface; no existing PDF-* requirement changes).
- Affected code (apply phase, NOT here):
  - `lib/Controller/EventsController.php` (new or extended) +
    `appinfo/routes.php` (run-sheet route; correct auth posture per
    gate-5/gate-9 — GM-group check, not just `#[NoAdminRequired]`)
  - Reuse of the DocuDesk dependency check + DI resolution
    (PDF-002/003-equivalent; shared helper rather than copy-paste)
  - Event detail action + `src/modals/DownloadEventRunsheet.vue`
  - A starter `runsheet` Twig template shipped/seeded for DocuDesk
    (namespace=larpingapp, category=runsheet)
  - `l10n/` nl + en strings; `appinfo/info.xml` version bump (cache-bust)
- Depends on: DocuDesk (optional, as today — graceful degradation);
  the character↔event link (CHAR-043/EVT-006, exists).
- Relates to: `event-signup-to-forms-leaf` (signups change who is on the
  roster, not how the roster prints — no duplication);
  `player-to-contacts-leaf` (once landed, emergency-contact data can join
  the cast entry — explicitly a follow-up, not in scope);
  `event-xp-award-workflow` (run-sheet is the during-event document; awards
  are the after-event act); `setting-management` (event's setting name in
  the header context when present).
- Out of scope: editing templates in-app (DocuDesk owns template
  management), per-player handouts, schedule/timetable management (the app
  has no scene/timetable model — a future capability, not smuggled in here).
