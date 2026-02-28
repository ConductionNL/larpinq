---
status: reviewed
---

# PDF Character Sheet Export

## Purpose

Enables game masters and players to export character data as downloadable PDF files. The system uses mPDF for PDF rendering and Twig for HTML templating. Users select a template from the available templates, and the backend renders the character data into the Twig template, converts the resulting HTML to PDF, and streams it to the browser. Templates are managed as first-class objects with full CRUD support.

## Requirements

### PDF Generation

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-001 | Generate a PDF from a character and a selected template | MUST | Implemented |
| PDF-002 | PDF generation uses mPDF library (v8.2+) | MUST | Implemented |
| PDF-003 | HTML is rendered via Twig templating engine (v3.18+) | MUST | Implemented |
| PDF-004 | Character data (including computed stats) is passed to the Twig template as `character` variable | MUST | Implemented |
| PDF-005 | Template data is intended to be passed to the Twig template as `template` variable, but due to variable reassignment in `createCharacterPdf()`, the `$template` variable is overwritten from the data array to the Twig Template object before `render()` is called. The `template` variable in the Twig context will be the Twig Template object, not the original data array. | MUST | Bug |
| PDF-006 | mPDF temp directory is created at `/tmp/mpdf` with 0777 permissions if it does not exist | MUST | Implemented |
| PDF-007 | PDF content is returned as a `DataDownloadResponse` via Nextcloud's response framework (uses `Mpdf::Output()` with `STRING_RETURN` destination) | MUST | Implemented |
| PDF-008 | The download URL format is `/characters/{characterId}/download/{templateId}` | MUST | Implemented |

### Template Management

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| TMPL-001 | Create templates with name, description, and Twig HTML template content | MUST | Implemented |
| TMPL-002 | Update existing templates | MUST | Implemented |
| TMPL-003 | Delete templates with confirmation dialog | MUST | Implemented |
| TMPL-004 | List templates with search and pagination | MUST | Implemented |
| TMPL-005 | View template details with Content, Relations, and Logging tabs | MUST | Implemented |
| TMPL-006 | Template content is rendered as sanitized rich text in the Content tab (DOMPurify) | MUST | Implemented |

### PDF Download Flow

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-010 | Character detail page has an "Als pdf downloaden" action button | MUST | Implemented |
| PDF-011 | Clicking the button opens a modal to select a template | MUST | Implemented |
| PDF-012 | Template selector is populated from the template store | MUST | Implemented |
| PDF-013 | Clicking "Download PDF" opens the PDF URL in a new browser tab | MUST | Implemented |
| PDF-014 | The modal validates that a template is selected before enabling the download button | MUST | Implemented |

### PDF Download Implementation

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| PDF-020 | `CharactersController.downloadPdf()` catches `\Exception` (fully qualified) for error handling when fetching character or template -- this correctly handles any exception type without needing specific imports | MUST | Implemented |
| PDF-021 | When a character or template is not found, a 404 JSONResponse is returned with `{'error': 'Character or template not found'}` | MUST | Implemented |
| PDF-022 | `downloadPdf()` calls `$pdfContent->Output('', Destination::STRING_RETURN)` to get the PDF as a string, then wraps it in a `DataDownloadResponse` -- this properly integrates with Nextcloud's response framework | MUST | Implemented |
| PDF-023 | `downloadPdf()` returns a `DataDownloadResponse` on the success path with the PDF content, filename (`{characterName}_character_sheet.pdf`), and `application/pdf` MIME type | MUST | Implemented |
| PDF-024 | The method signature correctly declares return type `DataDownloadResponse|JSONResponse` and both paths return the appropriate type | MUST | Implemented |

## Data Model

### Template Entity

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| id | string (UUID) | Auto | Generated | Unique identifier |
| name | string | Yes | "" | Template name |
| description | string | No | "" | Template description |
| template | string | No | "" | Twig HTML template content for PDF rendering |

### Twig Template Variables

The following variables are available inside the Twig template:

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
| `template` | object | The template object itself |

## User Interface

### Template List (`TemplatesList.vue`)

- `NcListItem` rows with template name and description
- Search, refresh, and add-new action buttons
- Click to select and view details

### Template Details (`TemplateDetails.vue`)

- Header with template name and action menu (Edit, Delete)
- Description section
- Three tabs:
  - **Content**: Renders template HTML via `NcRichText` inside `NcGuestContent`, sanitized with DOMPurify
  - **Relations**: Objects that reference this template
  - **Logging**: Audit trail entries

### Template Edit Modal (`EditTemplate.vue`)

- `NcDialog` with Name, Description, and Template (HTML content) fields
- Creates or updates templates

### PDF Download Modal (`RenderPdfFromCharacter.vue`)

- `NcDialog` triggered from character detail "Als pdf downloaden" action
- Instructional text: "Selecteer een template om een PDF te genereren van dit karakter"
- Template selector (`NcSelect`) populated from the template store
- Cancel, Help, and Download PDF action buttons
- Download button disabled until a template is selected and template list is loaded
- On download: opens `/index.php/apps/larpingapp/characters/{id}/download/{templateId}` in a new tab

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/characters/{id}/download/{template}` | Generate and stream character PDF using specified template |
| GET | `/api/objects/template` | List templates |
| POST | `/api/objects/template` | Create template |
| GET | `/api/objects/template/{id}` | Get single template |
| PUT | `/api/objects/template/{id}` | Update template |
| DELETE | `/api/objects/template/{id}` | Delete template |

Template CRUD also supports: `{id}/lock`, `{id}/unlock`, `{id}/revert`, `{id}/audit`, `{id}/relations`, `{id}/uses`, `{id}/files`.

## Scenarios

### Download a Character PDF

```
GIVEN character "Sir Lancelot" exists with computed stats
AND template "Standard Character Sheet" exists with Twig HTML content
WHEN the user opens the character detail and clicks "Als pdf downloaden"
AND selects "Standard Character Sheet" from the template dropdown
AND clicks "Download PDF"
THEN a new browser tab opens with URL /characters/{id}/download/{templateId}
AND CharactersController.downloadPdf() fetches the character and template
AND CharacterService.createCharacterPdf() renders the Twig template with character data
AND mPDF converts the HTML to a PDF document
AND the PDF content is returned via Nextcloud's DataDownloadResponse framework
AND the filename is "{characterName}_character_sheet.pdf"
```

### Create a Custom Template

```
GIVEN a user wants a custom character sheet layout
WHEN they navigate to Templates and click add
AND enter a name, description, and Twig HTML content with {{ character.name }}, {{ character.stats }}, etc.
AND save the template
THEN the template is stored and available for PDF export
AND the template content is visible in the Content tab (rendered as rich text)
```

### Template Not Selected

```
GIVEN the user opens the PDF download modal
WHEN no template is selected
THEN the "Download PDF" button is disabled
AND the user must select a template before downloading
```

### View Template Content

```
GIVEN a template exists with HTML content including Twig syntax
WHEN the user views the template detail page
AND opens the "Content" tab
THEN the raw HTML is displayed via NcRichText with DOMPurify sanitization
```

### Character or Template Not Found

```
GIVEN the user navigates to /characters/{id}/download/{templateId}
AND either the character or template ID does not exist
WHEN CharactersController.downloadPdf() attempts to fetch the objects
THEN it catches \Exception (fully qualified)
AND returns a 404 JSONResponse with {"error": "Character or template not found"}
```

## Dependencies

- **mPDF** (v8.2+): PHP library for PDF generation from HTML
- **Twig** (v3.18+): PHP templating engine for rendering HTML from character data
- **DOMPurify**: Frontend HTML sanitization for template content preview
- **CharactersController**: Handles the `downloadPdf` route
- **CharacterService**: `createCharacterPdf()` method for PDF generation
- **ObjectService**: CRUD for templates and character data retrieval
- **Pinia template store**: Frontend state management for template list and selection
- **TypeScript `Template` entity**: Frontend model with Zod validation
