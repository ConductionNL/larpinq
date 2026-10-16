# PDF Character Sheet Export — Frontend Download Flow Delta

**Spec refs**: `pdf-export` (base spec §"PDF Download Flow (Frontend)", PDF-040–PDF-046)

## MODIFIED Requirements

### Requirement: PDF Download Flow (Frontend)

The character detail page MUST expose a working "Download as PDF" action that a player, GM, or
admin can actually click — not merely a spec description. The action MUST be implemented as an
isolated `NcModal` component (`src/modals/CharacterPdfDownloadModal.vue`), MUST be hidden when
DocuDesk is not installed/enabled, MUST fetch the available templates from DocuDesk's
`namespace=larpingapp`-scoped template API, MUST disable the "Download PDF" confirm button until
a template is selected, and MUST open the character's download URL
(`/characters/{id}/download/{template}`) via `generateUrl()` in a new browser tab on confirm. The
requirement is not satisfied by backend endpoint existence alone.

**Feature tier**: MVP

#### Scenario: Player downloads their own character sheet from the UI

- GIVEN a logged-in player who owns a character, and DocuDesk is installed and enabled
- WHEN they open the character's detail page
- THEN a "Download as PDF" action MUST be visible
- WHEN they click it and select a template
- THEN the "Download PDF" button MUST become enabled
- WHEN they click "Download PDF"
- THEN the character's PDF download URL MUST open in a new browser tab

#### Scenario: DocuDesk not installed hides the action entirely

- GIVEN DocuDesk is not installed or not enabled
- WHEN a user opens any character's detail page
- THEN no "Download as PDF" action MUST be rendered anywhere on the page

#### Scenario: Download disabled until a template is chosen

- GIVEN the "Download as PDF" modal is open and templates have loaded
- WHEN no template is yet selected
- THEN the "Download PDF" confirm button MUST be disabled

## ADDED Requirements

### Requirement: E2E Traceability for the PDF Download Flow

The frontend PDF download flow MUST be covered by an executable Playwright spec under
`tests/e2e/spec-coverage/` (or `spa-ui.spec.ts`) that drives the action button, template
selection, and download trigger — the base spec's `@e2e exclude` annotation citing "SPA fails to
mount" MUST be removed once the SPA-mount fix (#202) and this change are both in place, since
that reason no longer describes reality (confirmed live by `spa-ui.spec.ts`'s other scenarios).

#### Scenario: The PDF download flow has a real Playwright test, not an exclusion

- GIVEN the frontend download action described above is implemented
- WHEN the e2e suite runs
- THEN a spec-coverage test MUST exercise the button → modal → download path
- AND the `pdf-export/spec.md` header MUST NOT carry a stale "SPA fails to mount" exclusion reason
