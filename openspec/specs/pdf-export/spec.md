---
status: implemented
---

# PDF Character Sheet Export

## Purpose

@e2e exclude larpingapp Vue SPA fails to mount at localhost:8080; RenderPdfFromCharacter.vue modal and "Als pdf downloaden" action are inaccessible; CharactersController.downloadPdf backend and DocuDesk integration scenarios are PHPUnit/integration-test scope

Enables game masters and players to export character data as downloadable PDF files. PDF rendering and template management are delegated to the DocuDesk app -- LarpingApp's `CharactersController` resolves DocuDesk's `PdfService` and `TemplateService` via Nextcloud's DI container. Templates are managed in DocuDesk, scoped to LarpingApp via the `namespace=larpingapp` filter. The PDF download flow gracefully degrades when DocuDesk is not installed, hiding the download button and returning a 424 error if accessed directly.

**Key source files:**
- `lib/Controller/CharactersController.php` -- `downloadPdf()` method
- `lib/Service/RegisterObjectFetcher.php` -- Character data retrieval
- `src/views/ObjectDetail.vue` or character detail component -- PDF download action
- `appinfo/routes.php` -- PDF download route
## Requirements

---

### Requirement: PDF Generation via DocuDesk

The system MUST generate PDF character sheets by delegating to DocuDesk's rendering pipeline.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-001 | `CharactersController.downloadPdf()` MUST generate a PDF from a character and a selected template | MUST | Implemented |
| PDF-002 | The controller MUST resolve DocuDesk's `PdfService` via `$this->container->get('OCA\DocuDesk\Service\PdfService')` | MUST | Implemented |
| PDF-003 | The controller MUST resolve DocuDesk's `TemplateService` via `$this->container->get('OCA\DocuDesk\Service\TemplateService')` | MUST | Implemented |
| PDF-004 | Template content MUST be fetched via `TemplateService::getTemplate($template)` | MUST | Implemented |
| PDF-005 | PDF MUST be rendered via `PdfService::renderPdf()` with template content, data context, and options | MUST | Implemented |
| PDF-006 | Data context MUST include `character` (full character data) and `template` (template metadata) | MUST | Implemented |
| PDF-007 | PDF options MUST include `format` (default 'A4') and `orientation` (default 'P') from template metadata | MUST | Implemented |
| PDF-008 | The PDF MUST be returned as a `DataDownloadResponse` with `application/pdf` MIME type | MUST | Implemented |
| PDF-009 | The filename MUST be `{characterName}_character_sheet.pdf` | MUST | Implemented |
| PDF-010 | The download URL MUST be `/characters/{id}/download/{template}` (GET) | MUST | Implemented |

#### Scenario: Download a character PDF successfully

- GIVEN character "Sir Lancelot" exists with computed stats (Strength=15, Mana=8)
- AND template "Standard Character Sheet" exists in DocuDesk with namespace=larpingapp
- AND DocuDesk is installed and enabled
- WHEN the user opens the character detail and clicks "Als pdf downloaden"
- AND selects "Standard Character Sheet" from the template dropdown
- AND clicks "Download PDF"
- THEN a new browser tab MUST open with URL `/characters/{id}/download/{templateId}`
- AND `CharactersController.downloadPdf()` MUST resolve DocuDesk services via DI
- AND `TemplateService::getTemplate()` MUST return the template data
- AND `PdfService::renderPdf()` MUST be called with template content and character data
- AND the response MUST be a `DataDownloadResponse` with filename "Sir Lancelot_character_sheet.pdf"

#### Scenario: PDF includes computed stats

- GIVEN character "Merlin" has stats: Arcane Mana=25 (base 0 + skills), HP=18 (base 20 - conditions)
- WHEN a PDF is generated for Merlin
- THEN the data context MUST include `character.stats` with the computed values
- AND the Twig template can access `{{ character.stats.<uuid>.value }}` for rendering

#### Scenario: PDF uses template format and orientation

- GIVEN template "Landscape Sheet" has format "A3" and orientation "L"
- WHEN `renderPdf()` is called for this template
- THEN the options MUST include `format: 'A3'` and `orientation: 'L'`

#### Scenario: PDF filename uses character name

- GIVEN character name is "Drizzt Do'Urden"
- WHEN the PDF is generated
- THEN the filename MUST be "Drizzt Do'Urden_character_sheet.pdf"

#### Scenario: PDF for character with no name

- GIVEN a character exists with name="" (empty)
- WHEN the PDF is generated
- THEN the filename MUST default to "character_character_sheet.pdf"

---

### Requirement: DocuDesk Dependency Check

The system MUST gracefully handle the absence of the DocuDesk app.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-020 | `downloadPdf()` MUST check `IAppManager::isEnabledForUser('docudesk')` before attempting PDF generation | MUST | Implemented |
| PDF-021 | If DocuDesk is not installed/enabled, the controller MUST return a 424 (Failed Dependency) JSONResponse | MUST | Implemented |
| PDF-022 | The 424 response MUST include `{"error": "PDF generation requires the DocuDesk app to be installed and enabled"}` | MUST | Implemented |
| PDF-023 | The frontend MUST hide the "Als pdf downloaden" action button when DocuDesk is not available | MUST | Implemented |
| PDF-024 | The frontend MUST check DocuDesk availability by probing DocuDesk's template API on component mount | MUST | Implemented |

#### Scenario: DocuDesk not installed -- API access

- GIVEN DocuDesk is not installed or not enabled for the current user
- WHEN the user navigates directly to `/characters/{id}/download/{templateId}`
- THEN a 424 JSONResponse MUST be returned
- AND the body MUST contain `{"error": "PDF generation requires the DocuDesk app to be installed and enabled"}`

#### Scenario: DocuDesk not installed -- UI

- GIVEN DocuDesk is not installed
- WHEN the user views a character detail page
- THEN the "Als pdf downloaden" action button MUST NOT be visible in the actions menu

#### Scenario: DocuDesk installed -- UI

- GIVEN DocuDesk is installed and enabled
- WHEN the user views a character detail page
- THEN the "Als pdf downloaden" action button MUST be visible in the actions menu

#### Scenario: Frontend DocuDesk detection

- GIVEN the character detail component mounts
- WHEN it probes the DocuDesk template API
- AND the API returns a success response
- THEN the PDF download action MUST be shown
- WHEN the API returns an error (DocuDesk not available)
- THEN the PDF download action MUST be hidden

---

### Requirement: Error Handling

The controller MUST handle all error cases with appropriate HTTP status codes.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-030 | When the character ID does not exist, the controller MUST return a 404 JSONResponse with `{"error": "Character not found"}` | MUST | Implemented |
| PDF-031 | When the template ID does not exist, the controller MUST return a 404 JSONResponse with `{"error": "Template not found"}` | MUST | Implemented |
| PDF-032 | When PDF generation fails (exception in PdfService), the controller MUST return a 500 JSONResponse with `{"error": "PDF generation failed: <message>"}` | MUST | Implemented |
| PDF-033 | Character retrieval MUST use `RegisterObjectFetcher::getObject('character', $id)` | MUST | Implemented |
| PDF-034 | Template retrieval errors MUST be caught and returned as 404 | MUST | Implemented |
| PDF-035 | The method signature MUST declare return type `DataDownloadResponse|JSONResponse` | MUST | Implemented |

#### Scenario: Character not found

- GIVEN no character with ID "nonexistent-uuid" exists
- WHEN the user navigates to `/characters/nonexistent-uuid/download/template-123`
- THEN the controller MUST catch the exception from `RegisterObjectFetcher`
- AND return a 404 JSONResponse with `{"error": "Character not found"}`

#### Scenario: Template not found

- GIVEN character "Merlin" exists but template ID "bad-template" does not exist in DocuDesk
- WHEN the user navigates to `/characters/{merlinId}/download/bad-template`
- THEN the controller MUST catch the exception from `TemplateService`
- AND return a 404 JSONResponse with `{"error": "Template not found"}`

#### Scenario: PDF rendering fails

- GIVEN character and template both exist
- BUT `PdfService::renderPdf()` throws an exception (e.g., invalid Twig syntax)
- WHEN the download URL is accessed
- THEN the controller MUST catch the exception
- AND return a 500 JSONResponse with the error message

#### Scenario: Multiple error layers

- GIVEN DocuDesk is installed
- AND the character exists
- AND the template exists
- BUT the template content has a syntax error
- WHEN PDF generation is attempted
- THEN the exception MUST be caught at the `renderPdf()` try/catch block
- AND a 500 response MUST be returned (not a 424 or 404)

---

### Requirement: PDF Download Flow (Frontend)

The frontend MUST provide a user-friendly flow for selecting templates and downloading PDFs.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-040 | Character detail page MUST have "Als pdf downloaden" action button (visible only when DocuDesk available) | MUST | Implemented |
| PDF-041 | Clicking the button MUST open a modal to select a template | MUST | Implemented |
| PDF-042 | Template selector MUST fetch from DocuDesk API: `GET /apps/docudesk/api/templates?namespace=larpingapp` | MUST | Implemented |
| PDF-043 | Clicking "Download PDF" MUST open the PDF URL in a new browser tab | MUST | Implemented |
| PDF-044 | Download button MUST be disabled until a template is selected and template list is loaded | MUST | Implemented |
| PDF-045 | The modal MUST include instructional text explaining the download flow | MUST | Implemented |
| PDF-046 | The modal MUST have Cancel, Help, and Download PDF action buttons | MUST | Implemented |

#### Scenario: Complete PDF download flow

- GIVEN character "Sir Lancelot" is being viewed
- AND DocuDesk has 2 templates with namespace=larpingapp
- WHEN the user clicks "Als pdf downloaden" in the actions menu
- THEN a modal MUST open with instructional text
- AND a template selector MUST show the 2 available templates
- WHEN the user selects "Standard Sheet"
- THEN the "Download PDF" button MUST become enabled
- WHEN they click "Download PDF"
- THEN a new browser tab MUST open with the PDF download URL
- AND the browser MUST download the PDF file

#### Scenario: No templates available

- GIVEN DocuDesk is installed but has no templates with namespace=larpingapp
- WHEN the user opens the PDF download modal
- THEN the template selector MUST be empty
- AND the "Download PDF" button MUST remain disabled

#### Scenario: Template selection required

- GIVEN the PDF modal is open
- WHEN no template has been selected
- THEN the "Download PDF" button MUST be disabled
- AND the user MUST select a template before downloading

#### Scenario: Cancel PDF download

- GIVEN the PDF modal is open with a template selected
- WHEN the user clicks "Cancel"
- THEN the modal MUST close
- AND no PDF request MUST be made

---

### Requirement: Twig Template Variables

The data context passed to DocuDesk's `PdfService::renderPdf()` MUST expose character data in a structure accessible by Twig templates.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-050 | The data context MUST include `character` key with full character data | MUST | Implemented |
| PDF-051 | The data context MUST include `template` key with template metadata | MUST | Implemented |
| PDF-052 | `character.stats` MUST contain per-ability objects with `name`, `base`, `value`, and `audit` | MUST | Implemented |
| PDF-053 | `character.skills`, `character.items`, `character.conditions`, `character.events` MUST be arrays of UUID strings | MUST | Implemented |
| PDF-054 | `character.gold`, `character.silver`, `character.copper` MUST be numeric values | MUST | Implemented |
| PDF-055 | `character.type` and `character.approved` MUST be enum strings | MUST | Implemented |

#### Scenario: Template accesses character stats

- GIVEN a Twig template with `{{ character.name }}` and `{% for stat in character.stats %}{{ stat.name }}: {{ stat.value }}{% endfor %}`
- WHEN the PDF is rendered for character "Merlin" with Strength=15
- THEN the PDF MUST display "Merlin" and "Strength: 15"

#### Scenario: Template accesses currency

- GIVEN a Twig template with `Gold: {{ character.gold }}, Silver: {{ character.silver }}`
- WHEN the PDF is rendered for a character with gold=5, silver=12
- THEN the PDF MUST display "Gold: 5, Silver: 12"

#### Scenario: Template accesses approval status

- GIVEN a Twig template with `Status: {{ character.approved }}`
- WHEN the PDF is rendered for an approved character
- THEN the PDF MUST display "Status: approved"

---

### Requirement: Route and Security Configuration

The PDF download route MUST be properly configured with appropriate access controls.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-060 | The route MUST be defined in `appinfo/routes.php` as GET `/characters/{id}/download/{template}` | MUST | Implemented |
| PDF-061 | The route MUST map to `characters#downloadPdf` | MUST | Implemented |
| PDF-062 | The endpoint MUST have `@NoAdminRequired` (accessible by non-admin users) | MUST | Implemented |
| PDF-063 | The endpoint MUST have `@NoCSRFRequired` (required for direct URL access in new tab) | MUST | Implemented |

#### Scenario: Non-admin user downloads PDF

- GIVEN a regular user has access to LarpingApp
- AND DocuDesk is installed
- WHEN the user navigates to a character PDF download URL
- THEN the PDF MUST be generated and returned
- AND no admin rights MUST be required

#### Scenario: CSRF not required for download

- GIVEN the download URL is opened in a new browser tab
- WHEN the request is made without a CSRF token
- THEN the request MUST succeed because `@NoCSRFRequired` is set

#### Scenario: Unauthenticated user denied

- GIVEN no user is logged in
- WHEN someone navigates to a character PDF download URL
- THEN Nextcloud's authentication MUST redirect to the login page

---

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

## User Interface

### PDF Download Modal (`RenderPdfFromCharacter.vue`)

- `NcDialog` triggered from character detail "Als pdf downloaden" action
- Instructional text: "Selecteer een template om een PDF te genereren van dit karakter"
- Template selector (`NcSelect`) populated from DocuDesk's template API (filtered by `namespace=larpingapp`)
- Cancel, Help, and Download PDF action buttons
- Download button disabled until a template is selected and template list is loaded
- On download: opens `/index.php/apps/larpingapp/characters/{id}/download/{templateId}` in a new tab

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/characters/{id}/download/{template}` | Generate and stream character PDF via DocuDesk |

Template CRUD is no longer in LarpingApp -- templates are managed via DocuDesk's API (`/apps/docudesk/api/templates`).

## Dependencies

- **DocuDesk** (soft dependency): `PdfService` for PDF rendering, `TemplateService` for template lookup. Resolved via DI container. PDF export degrades gracefully when absent.
- **CharactersController**: Handles the `downloadPdf` route, delegates to DocuDesk
- **RegisterObjectFetcher**: Character data retrieval via `getObject('character', $id)`
- **CharacterService**: Injected into CharactersController (available for future use in stat recalculation before PDF)
- **IAppManager**: Checking if DocuDesk is enabled for the current user

## Removed (migrated to DocuDesk)

The following were previously part of LarpingApp and have been migrated to DocuDesk:

- `CharacterService.createCharacterPdf()` -- replaced by DocuDesk's `PdfService::renderPdf()`
- `mpdf/mpdf` and `twig/twig` composer dependencies -- now in DocuDesk
- Template entity (`Template.php`, `TemplateMapper.php`) -- templates now in DocuDesk's OpenRegister
- Template views (`TemplatesList.vue`, `TemplateDetails.vue`, `EditTemplate.vue`) -- template CRUD now in DocuDesk
- Pinia template store (`template.js`) -- replaced by direct DocuDesk API calls
- TypeScript Template entity -- no longer needed
- Template navigation entry in sidebar -- removed
