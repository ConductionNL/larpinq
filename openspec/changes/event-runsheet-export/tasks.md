# Tasks — event-runsheet-export

## 1. Backend

- [ ] 1.1 Extract the DocuDesk dependency check + DI resolution from `CharactersController.downloadPdf()` into a shared helper (behaviour-neutral for PDF-001..010; existing pdf-export tests are the regression net)
- [ ] 1.2 Add `EventsController.downloadRunsheet(string $id, string $template)`: fetch the event, build the run-sheet context, render via DocuDesk `TemplateService::getTemplate()` + `PdfService::renderPdf()` with template format/orientation, return `DataDownloadResponse` named `{eventName}_runsheet.pdf` (fallback `event_runsheet.pdf`)
- [ ] 1.3 Route `GET /events/{id}/runsheet/{template}` in `appinfo/routes.php`; `#[NoAdminRequired]` + explicit GM-group guard in the method body returning 403 for non-GMs (gate-5 route-auth + gate-9 semantic-auth)
- [ ] 1.4 Context builder: `event` block (name, dates, location, ambient effects, castCount, setting name when scoped), `cast[]` from characters whose `events[]` reference the event sorted by name (character name, type, approval status, player name or empty, stored computed stats — no recalculation, condition names, unique item names, slNotesPublic/slNotesPrivate), `uniqueItemsInPlay[]` rollup
- [ ] 1.5 Template category filtering: run-sheet picker lists `namespace=larpingapp` + `category=runsheet` only; character-sheet picker excludes runsheet category (uncategorised legacy = character sheet)
- [ ] 1.6 Seed a starter run-sheet Twig template when no larpingapp runsheet-category template exists (idempotent, never overwrite GM edits)

## 2. Frontend

- [ ] 2.1 "Download run-sheet" action on the event detail page, rendered for GM-group members only (presentation over the server guard); hidden when DocuDesk is absent
- [ ] 2.2 `src/modals/DownloadEventRunsheet.vue` (own file — modal-isolation gate), mirroring `RenderPdfFromCharacter.vue`: runsheet-template select (with `inputLabel`, gate-12), opens the PDF in a new tab; empty-template-list state pointing to DocuDesk template management
- [ ] 2.3 i18n: English source keys + nl translations (ADR-007/ADR-025); bump `appinfo/info.xml` `<version>` (immutable-cache bust)

## 3. Quality

- [ ] 3.1 Annotate new/changed methods with `@spec openspec/changes/event-runsheet-export/...` (gate-16) and SPDX headers (gate-1)
- [ ] 3.2 `composer check:strict` green; run hydra gates (incl. gate-5/9 on the new route, gate-16, gate-19) — fix any pre-existing issues encountered in touched files

## 4. Tests

- [ ] 4.1 PHPUnit: context builder (cast derivation + sorting, player-name gap tolerance, unique-items rollup, stored-stats passthrough), filename fallback, GM-group guard (403), shared-helper regression for `downloadPdf`
- [ ] 4.2 Newman (`tests/integration/*.postman_collection.json`): GM GET → 200 application/pdf; non-GM GET → 403; DocuDesk-absent → 424; template-category filtering (API assertions belong in Newman, not Playwright)
- [ ] 4.3 Playwright `tests/e2e/spec-coverage/`: GM sees and uses the run-sheet action + modal; non-GM sees no action; empty-template-list message (download/byte-level and backend-only scenarios get `@e2e exclude` on their own line)

## 5. Spec sync

- [ ] 5.1 On archive, sync the RUN-001..022 additions into `openspec/specs/pdf-export/spec.md`
- [ ] 5.2 Update `docs/FEATURES.md` (event run-sheet / cast-list export)

## Acceptance criteria

- A GM downloads a per-event run-sheet PDF from the event detail page through a DocuDesk runsheet-category template; the cast list carries player names, stored computed stats, conditions, unique items, GM notes, and the unique-items-in-play rollup.
- A non-GM gets a 403 from the endpoint and never sees the action; DocuDesk-absent degrades to hidden action + 424 exactly like character sheets.
- Character-sheet and run-sheet template pickers are cleanly separated by category; a starter run-sheet template is seeded idempotently.
- The shared DocuDesk helper leaves `downloadPdf()` behaviour untouched (existing pdf-export tests stay green).
- All new strings ship in en + nl; PHPUnit, Newman, and Playwright coverage as in section 4; `composer check:strict` and hydra gates pass.
