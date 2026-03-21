---
status: approved
---

# PDF Character Sheet Export — Design

## Architecture

PDF export delegates rendering to DocuDesk. LarpingApp's `CharactersController.downloadPdf()` resolves DocuDesk's services via Nextcloud's DI container and returns a `DataDownloadResponse`.

## Component Map

| Layer | Component | Responsibility |
|-------|-----------|----------------|
| PHP Controller | `CharactersController.downloadPdf()` | Orchestrates PDF generation |
| PHP Service | `RegisterObjectFetcher` | Fetches character data |
| Cross-app | DocuDesk `PdfService` | Renders PDF from Twig template |
| Cross-app | DocuDesk `TemplateService` | Retrieves template content |
| Route | `/characters/{id}/download/{template}` | GET endpoint for PDF download |

## Flow

1. Frontend opens new tab with URL `/characters/{id}/download/{template}`
2. Controller checks if DocuDesk is enabled (returns 424 if not)
3. Fetches character data via `RegisterObjectFetcher.getObject('character', id)`
4. Fetches template via `TemplateService.getTemplate(template)`
5. Renders PDF via `PdfService.renderPdf()` with character + template data
6. Returns `DataDownloadResponse` with filename `{name}_character_sheet.pdf`

## Error Handling

- DocuDesk not installed: 424 with error message
- Character not found: 404
- Template not found: 404
- PDF generation failed: 500 with exception message

## Graceful Degradation

When DocuDesk is not installed, the download button should be hidden in the frontend. If accessed directly, a 424 error is returned.
