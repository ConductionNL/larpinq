---
status: done
---

# PDF Character Sheet Export — Tasks

- [x] Implement CharactersController.downloadPdf() method
- [x] Implement DocuDesk dependency check (424 when not installed)
- [x] Implement character data retrieval via RegisterObjectFetcher
- [x] Implement template resolution via DocuDesk TemplateService
- [x] Implement PDF rendering via DocuDesk PdfService
- [x] Implement DataDownloadResponse with proper filename and MIME type
- [x] Implement error handling (404 character, 404 template, 500 render failure)
- [x] Add PDF download route in appinfo/routes.php
- [x] Unit tests: CharactersControllerTest covering downloadPdf scenarios (ADR-009)
- [x] Feature documentation: docs/features/pdf-export.md (ADR-010)
- [x] i18n: Verify PDF-related frontend strings use t() function (ADR-005)
