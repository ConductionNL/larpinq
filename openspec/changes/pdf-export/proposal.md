# PDF Character Sheet Export

## Problem
Enables game masters and players to export character data as downloadable PDF files. PDF rendering and template management are delegated to the DocuDesk app -- LarpingApp's `CharactersController` resolves DocuDesk's `PdfService` and `TemplateService` via Nextcloud's DI container. Templates are managed in DocuDesk, scoped to LarpingApp via the `namespace=larpingapp` filter. The PDF download flow gracefully degrades when DocuDesk is not installed, hiding the download button and returning a 424 error if accessed directly.
**Key source files:**
- `lib/Controller/CharactersController.php` -- `downloadPdf()` method
- `lib/Service/RegisterObjectFetcher.php` -- Character data retrieval
- `src/views/ObjectDetail.vue` or character detail component -- PDF download action
- `appinfo/routes.php` -- PDF download route

## Proposed Solution
Implement PDF Character Sheet Export following the detailed specification. Key requirements include:
- Requirement: PDF Generation via DocuDesk
- Requirement: DocuDesk Dependency Check
- Requirement: Error Handling
- Requirement: PDF Download Flow (Frontend)
- Requirement: Twig Template Variables

## Scope
This change covers all requirements defined in the pdf-export specification.

## Success Criteria
- Download a character PDF successfully
- PDF includes computed stats
- PDF uses template format and orientation
- PDF filename uses character name
- PDF for character with no name
