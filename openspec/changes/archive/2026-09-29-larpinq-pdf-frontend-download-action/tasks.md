# Tasks — larpinq-pdf-frontend-download-action

## 1. Preconditions (confirm the gap before building)

- [x] 1.1 Re-run `grep -rli pdf src --include=*.vue --include=*.js` and `grep -rn "Als pdf downloaden" .` against current HEAD to reconfirm no frontend PDF entry point exists (guards against this change racing a parallel fix)
- [x] 1.2 Confirm DocuDesk's template listing contract: `GET /apps/docudesk/api/templates?namespace=larpingapp` (per `pdf-export/spec.md:177`) — read `docudesk/lib/Controller` to verify the route and response shape before building the frontend fetch call

## 2. Frontend: download action + modal

- [x] 2.1 Created as `src/dialogs/CharacterPdfDownloadDialog.vue` (NcDialog, the fleet's dialog convention, opened by an `open-modal` header action; was: `src/modals/CharacterPdfDownloadModal.vue`, NcModal-based, per the modal-isolation house rule — no inline modal markup in `ObjectDetail.vue` or the character view)
- [x] 2.2 (Built as: larpinq endpoint `GET /api/pdf/templates` asks filinq server-side, since the app id moved docudesk to filinq; the header action's `visibleWhen` reads its `available`.) On mount, probe DocuDesk availability (`useAppStatus('docudesk')` if available fleet-wide, else a lightweight HEAD/GET against the template endpoint) and fetch templates scoped to `namespace=larpingapp`
- [x] 2.3 Render a template `<NcSelect>` with `inputLabel` set (ADR-004 hard rule), disable the "Download PDF" button until a template is selected and the list has loaded (PDF-044)
- [x] 2.4 On confirm, open `generateUrl('/apps/larpinq/characters/{id}/download/{template}', { id, template })` in a new tab (`window.open(url, '_blank')`) — use `@nextcloud/router generateUrl()`, never a literal path (ADR-004)
- [x] 2.5 Wire a "Download as PDF" `NcActionButton` (or equivalent) into the character detail page, visible only when DocuDesk is available; hide entirely when unavailable (PDF-023/PDF-040)
- [x] 2.6 (Built as: the template endpoint is admin-only like downloadPdf, so the action hides for anyone who cannot download; widening to owner/GM is player-character-sheet-access.) Gate the action's visibility using the same access rule as `player-character-sheet-access`'s `canAccessCharacter` (own character, GM-group member, or admin) once that change lands — coordinate merge order or add a follow-up task if it lands first
- [x] 2.7 i18n: every label/button/instructional string wrapped in `t('larpinq', ...)` with English source keys (feedback rule)

## 3. Tests

- [x] 3.1 (Built as `tests/e2e/workflows/character-pdf.workflow.spec.ts`.) Extend `tests/e2e/spec-coverage/spa-ui.spec.ts` (or add a new spec-coverage file) with a scenario that: opens a character detail page, clicks "Download as PDF", selects a template, clicks "Download PDF", and asserts a new tab/download is triggered (or, if DocuDesk is not installed in the e2e env, asserts the button is absent and documents that as the tested path)
- [x] 3.2 (`tests/vitest/characterPdf.spec.js` canDownload, and the workflow spec.) Add a Playwright/vitest assertion for the disabled-until-template-selected state (PDF-044)
- [x] 3.3 (New endpoint, so new `tests/unit/Controller/CharactersPdfTemplatesTest.php`.) Update `tests/unit/Controller/CharactersControllerTest.php` only if the frontend change requires a new query param or contract change on the backend (expected: no backend change needed)

## 4. Spec correction

- [x] 4.1 Replace the stale `@e2e exclude` reason at `openspec/specs/pdf-export/spec.md:9` (which still claims "larpinq Vue SPA fails to mount") with a reference to the new e2e coverage from Task 3.1
- [x] 4.2 Re-verify each of PDF-040 through PDF-046 against the shipped implementation; any requirement that still does not hold MUST be changed from `Implemented` to `Planned` rather than left mismarked
- [x] 4.3 `@spec` annotations on the new component/modal methods pointing at this change (gate-16)

## 5. Quality

- [x] 5.1 `npm run check:manifest` / lint / existing frontend test suite green
- [x] 5.2 Hydra gates green on the diff (modal-isolation, nc-input-labels, forbidden-patterns)
