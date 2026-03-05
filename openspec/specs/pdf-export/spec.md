---
status: reviewed
---

# PDF Character Sheet Export

## Purpose

Enables game masters and players to export character data as downloadable PDF files. PDF rendering and template management are delegated to the DocuDesk app — LarpingApp's controller resolves DocuDesk's `PdfService` and `TemplateService` via Nextcloud's DI container. Templates are managed in DocuDesk, scoped to LarpingApp via the `namespace=larpingapp` filter. The PDF download flow gracefully degrades when DocuDesk is not installed.

## Requirements

### PDF Generation (via DocuDesk)

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-001 | Generate a PDF from a character and a selected template by delegating to DocuDesk's `PdfService` | MUST | Implemented |
| PDF-002 | `CharactersController.downloadPdf()` resolves DocuDesk's `PdfService` and `TemplateService` via DI container | MUST | Implemented |
| PDF-003 | Character data (including computed stats) is passed to `PdfService::renderPdf()` as the data context | MUST | Implemented |
| PDF-004 | Template content is fetched from DocuDesk's `TemplateService::getTemplate()` via DI | MUST | Implemented |
| PDF-005 | PDF content is returned as a `DataDownloadResponse` with filename `{characterName}_character_sheet.pdf` and `application/pdf` MIME type | MUST | Implemented |
| PDF-006 | The download URL format is `/characters/{characterId}/download/{templateId}` | MUST | Implemented |
| PDF-007 | Method signature declares return type `DataDownloadResponse\|JSONResponse` | MUST | Implemented |

### DocuDesk Dependency Check

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-010 | `CharactersController.downloadPdf()` checks `IAppManager::isEnabledForUser('docudesk')` before attempting PDF generation | MUST | Implemented |
| PDF-011 | If DocuDesk is not installed/enabled, returns 424 (Failed Dependency) JSONResponse with `{"error": "PDF generation requires the DocuDesk app to be installed and enabled"}` | MUST | Implemented |
| PDF-012 | Character detail page hides "Als pdf downloaden" action button when DocuDesk is not available | MUST | Implemented |
| PDF-013 | Frontend checks DocuDesk availability by probing DocuDesk's template API on component mount | MUST | Implemented |

### Error Handling

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-020 | When character ID does not exist, returns 404 JSONResponse | MUST | Implemented |
| PDF-021 | When template ID does not exist, returns 404 JSONResponse | MUST | Implemented |
| PDF-022 | When PDF generation fails, returns 500 JSONResponse with error message | MUST | Implemented |

### PDF Download Flow (Frontend)

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-030 | Character detail page has "Als pdf downloaden" action button (visible only when DocuDesk available) | MUST | Implemented |
| PDF-031 | Clicking the button opens a modal to select a template | MUST | Implemented |
| PDF-032 | Template selector fetches from DocuDesk API: `GET /apps/docudesk/api/templates?namespace=larpingapp` | MUST | Implemented |
| PDF-033 | Clicking "Download PDF" opens the PDF URL in a new browser tab | MUST | Implemented |
| PDF-034 | Download button disabled until a template is selected and template list is loaded | MUST | Implemented |

### Twig Template Variables

The following variables are available in the Twig template data context (passed to DocuDesk's `PdfService::renderPdf()`):

| Variable | Type | Description |
|----------|------|-------------|
| `character` | object | Full character data including computed stats |
| `character.name` | string | Character name |
| `character.description` | string | Character description |
| `character.background` | string | Character background |
| `character.stats` | object | Computed ability scores (keyed by ability UUID) |
| `character.stats.{id}.name` | string | Ability name |
| `character.stats.{id}.value` | number | Computed ability value |
| `character.stats.{id}.base` | number | Base ability value |
| `character.skills` | array | Associated skill IDs |
| `character.items` | array | Associated item IDs |
| `character.conditions` | array | Associated condition IDs |
| `character.events` | array | Associated event IDs |
| `character.gold` | number | Gold currency |
| `character.silver` | number | Silver currency |
| `character.copper` | number | Copper currency |
| `character.type` | string | Character type (player/npc/other) |
| `character.approved` | string | Approval status |

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

Template CRUD is no longer in LarpingApp — templates are managed via DocuDesk's API (`/apps/docudesk/api/templates`).

## Scenarios

### Download a Character PDF (via DocuDesk)

```
GIVEN character "Sir Lancelot" exists with computed stats
AND template "Standard Character Sheet" exists in DocuDesk with namespace=larpingapp
AND DocuDesk is installed and enabled
WHEN the user opens the character detail and clicks "Als pdf downloaden"
AND selects "Standard Character Sheet" from the template dropdown
AND clicks "Download PDF"
THEN a new browser tab opens with URL /characters/{id}/download/{templateId}
AND CharactersController.downloadPdf() resolves DocuDesk's PdfService and TemplateService via DI
AND fetches the template content from TemplateService
AND calls PdfService.renderPdf() with the template content and character data
AND the PDF is returned via DataDownloadResponse
```

### DocuDesk not installed

```
GIVEN DocuDesk is not installed or not enabled
WHEN the user views a character detail page
THEN the "Als pdf downloaden" action button is hidden
AND if the user navigates to the download URL directly
THEN a 424 JSONResponse is returned with {"error": "PDF generation requires the DocuDesk app to be installed and enabled"}
```

### Template Not Selected

```
GIVEN the user opens the PDF download modal
WHEN no template is selected
THEN the "Download PDF" button is disabled
AND the user must select a template before downloading
```

### Character or Template Not Found

```
GIVEN the user navigates to /characters/{id}/download/{templateId}
AND either the character or template ID does not exist
WHEN CharactersController.downloadPdf() attempts to fetch the objects
THEN it returns a 404 JSONResponse with the error message
```

## Dependencies

- **DocuDesk** (soft dependency): `PdfService` for PDF rendering, `TemplateService` for template lookup. Resolved via DI container. PDF export degrades gracefully when absent.
- **CharactersController**: Handles the `downloadPdf` route, delegates to DocuDesk
- **ObjectService**: Character data retrieval (unchanged)

## Removed (migrated to DocuDesk)

The following were previously part of LarpingApp and have been migrated to DocuDesk:

- `CharacterService.createCharacterPdf()` — replaced by DocuDesk's `PdfService::renderPdf()`
- `mpdf/mpdf` and `twig/twig` composer dependencies — now in DocuDesk
- Template entity (`Template.php`, `TemplateMapper.php`) — templates now in DocuDesk's OpenRegister
- Template views (`TemplatesList.vue`, `TemplateDetails.vue`, `EditTemplate.vue`) — template CRUD now in DocuDesk
- Pinia template store (`template.js`) — replaced by direct DocuDesk API calls
- TypeScript Template entity — no longer needed
- Template navigation entry in sidebar — removed
