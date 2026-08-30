---
status: draft
---

# PDF Export — delta for event-runsheet-export

## ADDED Requirements

### Requirement: Event Run-Sheet Generation via DocuDesk

The system MUST generate a printable GM run-sheet (cast list) per event by
delegating to the same DocuDesk pipeline as character sheets.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| RUN-001 | `EventsController.downloadRunsheet()` MUST generate a PDF from an event and a selected template via DocuDesk's `PdfService`/`TemplateService`, resolved through the DI container exactly as PDF-002/PDF-003 | MUST | Planned |
| RUN-002 | The DocuDesk dependency check and DI resolution MUST be shared with the character-sheet path (one helper, no copy-paste), behaviour-neutral for PDF-001..010 | MUST | Planned |
| RUN-003 | Run-sheet templates MUST be DocuDesk templates with `namespace=larpingapp` and `category=runsheet`; character-sheet pickers MUST NOT offer them and vice versa (uncategorised legacy templates count as character sheets) | MUST | Planned |
| RUN-004 | A starter run-sheet template MUST be seeded when no larpingapp runsheet-category template exists; GM-edited templates MUST never be overwritten | MUST | Planned |
| RUN-005 | PDF options MUST use the template's format/orientation metadata (PDF-007 analogue) and the response MUST be a `DataDownloadResponse` with `application/pdf` | MUST | Planned |
| RUN-006 | The filename MUST be `{eventName}_runsheet.pdf`, defaulting to `event_runsheet.pdf` for an empty event name | MUST | Planned |
| RUN-007 | The download URL MUST be `/events/{id}/runsheet/{template}` (GET) | MUST | Planned |

#### Scenario: Download an event run-sheet successfully

- GIVEN event "Summer LARP 2026" with three participating characters
- AND a DocuDesk template "Field Run-Sheet" with namespace=larpingapp, category=runsheet
- AND DocuDesk is installed and enabled
- WHEN a GM opens the event detail, clicks "Download run-sheet", selects "Field Run-Sheet", and confirms
- THEN `EventsController.downloadRunsheet()` MUST resolve DocuDesk services via the shared helper
- AND the response MUST be a `DataDownloadResponse` with filename "Summer LARP 2026_runsheet.pdf"

#### Scenario: Run-sheet templates do not pollute the character-sheet picker

- GIVEN templates "Field Run-Sheet" (category=runsheet) and "Standard Character Sheet" (category=charactersheet) exist under namespace=larpingapp
- WHEN the user opens the character PDF download modal
- THEN only "Standard Character Sheet" MUST be offered
- AND the event run-sheet modal MUST offer only "Field Run-Sheet"

### Requirement: Run-Sheet Access MUST Be Restricted to Game Masters

Run-sheet access MUST be restricted to game masters. The run-sheet aggregates
GM-facing material (approval status, GM notes including slNotesPrivate,
whole-cast overview) and MUST therefore be restricted server-side to the GM
group: the controller method MUST verify GM-group membership and return 403
otherwise (route remains
`#[NoAdminRequired]` — GMs are not Nextcloud admins — with the explicit
group guard in the method body). The event-detail action MUST be shown to
GM-group members only, as presentation over the server check.

#### Scenario: Non-GM direct request is rejected

- GIVEN user "bob" is authenticated but not in the GM group
- WHEN bob GETs `/events/{id}/runsheet/{template}` directly
- THEN the response MUST be 403
- AND no PDF MUST be generated

#### Scenario: Non-GM does not see the action

- GIVEN user "bob" is not in the GM group
- WHEN bob opens the event detail page
- THEN the "Download run-sheet" action MUST NOT be offered

### Requirement: Run-Sheet Data Context

The run-sheet Twig context MUST contain the event block and the cast list so
a GM team can run the field from the printed document.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| RUN-010 | The context MUST include `event` with name, start/end dates, location, ambient effects, and `castCount` | MUST | Planned |
| RUN-011 | `cast[]` MUST contain one entry per participating character (characters whose `events[]` reference the event), sorted by character name | MUST | Planned |
| RUN-012 | Each cast entry MUST include character name, type, approval status, linked player name (empty when unresolvable — never an error), computed `stats` as stored by the engine (no recalculation at render time), condition names, unique item names, and GM notes (`slNotesPublic`, `slNotesPrivate`) | MUST | Planned |
| RUN-013 | The context MUST include a `uniqueItemsInPlay[]` rollup mapping each unique item in the cast to its carrying character | MUST | Planned |
| RUN-014 | When `setting-management` is present and the event is scoped, the context MUST include the setting name | SHOULD | Planned |

#### Scenario: Cast list with player names and computed stats

- GIVEN event "Summer LARP 2026" with characters "Lancelot" (player "Alice", HP 27, condition "Blessed") and "Merlin" (player "Bob", unique item "Excalibur")
- WHEN the run-sheet context is built
- THEN cast[] MUST contain both characters sorted by name, each with player name, stored computed stats, condition names, and unique items
- AND uniqueItemsInPlay[] MUST map "Excalibur" to "Merlin"
- AND event.castCount MUST be 2

#### Scenario: Character without a linked player still renders

- GIVEN participating character "Mysterious Stranger" has no resolvable player
- WHEN the run-sheet is generated
- THEN the cast entry MUST render with an empty player cell
- AND generation MUST NOT fail

#### Scenario: GM notes are available to the template

- GIVEN character "Lancelot" has slNotesPrivate "Secretly the traitor"
- WHEN the run-sheet context is built
- THEN the cast entry MUST expose slNotesPublic and slNotesPrivate to the Twig template
- AND whether they print is a template decision (a player-safe template simply omits them)

### Requirement: Run-Sheet Download Flow and Degradation

The event detail page MUST offer the run-sheet download through the same
modal pattern and graceful degradation as the character sheet.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| RUN-020 | A "Download run-sheet" action on the event detail page MUST open a template-picker modal (own file in `src/modals/`, mirroring the character PDF modal) listing runsheet-category templates, and open the rendered PDF in a new tab | MUST | Planned |
| RUN-021 | When DocuDesk is not installed/enabled, the action MUST be hidden and direct endpoint access MUST return 424 with the existing dependency-error shape | MUST | Planned |
| RUN-022 | When no runsheet-category template exists, the modal MUST explain this and point to DocuDesk template management instead of failing | MUST | Planned |

#### Scenario: DocuDesk absent degrades gracefully

- GIVEN DocuDesk is not enabled
- WHEN a GM opens the event detail page
- THEN the "Download run-sheet" action MUST be hidden
- AND a direct GET of `/events/{id}/runsheet/{template}` MUST return 424

#### Scenario: No run-sheet templates available

- GIVEN DocuDesk is enabled but no template with namespace=larpingapp and category=runsheet exists
- WHEN the GM opens the run-sheet modal
- THEN the modal MUST state that no run-sheet templates are available and reference DocuDesk template management
- AND MUST NOT offer character-sheet templates as a fallback
