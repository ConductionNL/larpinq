# PDF Character Sheet Export

## Overview

Enables game masters and players to export character data as downloadable PDF files. PDF rendering is delegated to the DocuDesk app.

## Download a sheet

Open a character and choose **Download as PDF** in the Actions menu. Pick a template and press **Download PDF**. The sheet opens in a new tab.

The action appears when the document app is installed and has a template for Larpinq. For now only administrators can download sheets; players follow in a later release.

## Features

- **PDF generation** via DocuDesk's PdfService
- **Template selection** from DocuDesk templates scoped to Larpinq
- **Character data** including computed stats included in PDF context
- **Configurable format** (A4, Letter) and orientation (Portrait, Landscape) from template
- **Graceful degradation** when DocuDesk is not installed (424 error, hidden button)
- **Named filename**: `{characterName}_character_sheet.pdf`

## Route

`GET /characters/{id}/download/{template}`

## Error Handling

| Status | Condition |
|--------|-----------|
| 424 | DocuDesk not installed |
| 404 | Character not found |
| 404 | Template not found |
| 500 | PDF rendering failed |

## Technical Details

- Controller: `CharactersController.downloadPdf()`
- Cross-app: DocuDesk `PdfService` and `TemplateService` resolved via DI container
- Response: `DataDownloadResponse` with `application/pdf` MIME type
- Template variables: `character` (full data), `template` (metadata)
