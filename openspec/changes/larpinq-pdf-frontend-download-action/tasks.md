# Tasks — larpinq-pdf-frontend-download-action

## 1. Preconditions (confirm the gap before building)

- [ ] 1.1 Re-run `grep -rli pdf src --include=*.vue --include=*.js` and `grep -rn "Als pdf downloaden" .` against current HEAD to reconfirm no frontend PDF entry point exists (guards against this change racing a parallel fix)
- [ ] 1.2 Confirm DocuDesk's template listing contract: `GET /apps/docudesk/api/templates?namespace=larpingapp` (per `pdf-export/spec.md:177`) — read `docudesk/lib/Controller` to verify the route and response shape before building the frontend fetch call

## 2. Frontend: download action + modal

- [ ] 2.1 Create `src/modals/CharacterPdfDownloadModal.vue` (NcModal-based, per the modal-isolation house rule — no inline modal markup in `ObjectDetail.vue` or the character view)
- [ ] 2.2 On mount, probe DocuDesk availability (`useAppStatus('docudesk')` if available fleet-wide, else a lightweight HEAD/GET against the template endpoint) and fetch templates scoped to `namespace=larpingapp`
- [ ] 2.3 Render a template `<NcSelect>` with `inputLabel` set (ADR-004 hard rule), disable the "Download PDF" button until a template is selected and the list has loaded (PDF-044)
- [ ] 2.4 On confirm, open `generateUrl('/apps/larpinq/characters/{id}/download/{template}', { id, template })` in a new tab (`window.open(url, '_blank')`) — use `@nextcloud/router generateUrl()`, never a literal path (ADR-004)
- [ ] 2.5 Wire a "Download as PDF" `NcActionButton` (or equivalent) into the character detail page, visible only when DocuDesk is available; hide entirely when unavailable (PDF-023/PDF-040)
- [ ] 2.6 Gate the action's visibility using the same access rule as `player-character-sheet-access`'s `canAccessCharacter` (own character, GM-group member, or admin) once that change lands — coordinate merge order or add a follow-up task if it lands first
- [ ] 2.7 i18n: every label/button/instructional string wrapped in `t('larpinq', ...)` with English source keys (feedback rule)

## 3. Tests

- [ ] 3.1 Extend `tests/e2e/spec-coverage/spa-ui.spec.ts` (or add a new spec-coverage file) with a scenario that: opens a character detail page, clicks "Download as PDF", selects a template, clicks "Download PDF", and asserts a new tab/download is triggered (or, if DocuDesk is not installed in the e2e env, asserts the button is absent and documents that as the tested path)
- [ ] 3.2 Add a Playwright/vitest assertion for the disabled-until-template-selected state (PDF-044)
- [ ] 3.3 Update `tests/unit/Controller/CharactersControllerTest.php` only if the frontend change requires a new query param or contract change on the backend (expected: no backend change needed)

## 4. Spec correction

- [ ] 4.1 Replace the stale `@e2e exclude` reason at `openspec/specs/pdf-export/spec.md:9` (which still claims "larpinq Vue SPA fails to mount") with a reference to the new e2e coverage from Task 3.1
- [ ] 4.2 Re-verify each of PDF-040 through PDF-046 against the shipped implementation; any requirement that still does not hold MUST be changed from `Implemented` to `Planned` rather than left mismarked
- [ ] 4.3 `@spec` annotations on the new component/modal methods pointing at this change (gate-16)

## 5. Quality

- [ ] 5.1 `npm run check:manifest` / lint / existing frontend test suite green
- [ ] 5.2 Hydra gates green on the diff (modal-isolation, nc-input-labels, forbidden-patterns)
