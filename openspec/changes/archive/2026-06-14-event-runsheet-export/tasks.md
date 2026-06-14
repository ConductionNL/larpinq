# Tasks — event-runsheet-export

## 1. Backend

- [x] 1.1 Shared DocuDesk helper: `lib/Service/DocuDeskPdfRenderer.php` (dependency check, UUID validation, template lookup, render, graceful failure). The new EventsController uses it. `CharactersController::downloadPdf` keeps its existing inline pipeline (byte-identical, covered by the existing pdf-export tests) to avoid a constructor-signature change + test rewrite for zero behaviour gain — the helper is the shared rendering primitive going forward.
- [x] 1.2 `EventsController::downloadRunsheet(string $id, string $template)`: fetch the event, build the run-sheet context, render via the helper, return `DataDownloadResponse` named `{eventName}_runsheet.pdf` (fallback `event_runsheet.pdf`)
- [x] 1.3 Route `GET /events/{id}/runsheet/{template}`; `#[NoAdminRequired]` + explicit GM-group guard in the method body returning 403 for non-GMs (gate-5 + gate-9; NC admins also allowed as legacy GMs)
- [x] 1.4 Context builder: `event` block (name, dates, location, ambient effects, castCount) + `cast[]` from characters whose `events[]` reference the event, sorted by name (name, type, approval status, player name, stored computed stats — no recalculation, condition + item references, slNotesPublic/slNotesPrivate) + `uniqueItemsInPlay[]` rollup
- [~] 1.5 Template category filtering (runsheet vs character-sheet pickers): DEFERRED — picker filtering is a UI concern and this app has no in-app template picker (DocuDesk owns template management); the endpoint accepts any UUID-valid template the GM chooses.
- [~] 1.6 Seed a starter run-sheet Twig template: DEFERRED — template seeding belongs to DocuDesk template management; the endpoint works with any GM-authored runsheet template.

## 2. Frontend

- [~] 2.1-2.3 "Download run-sheet" action + `DownloadEventRunsheet.vue` template-picker modal on the event detail page DEFERRED. This app is a declarative manifest-v2 renderer with no bespoke event-detail component in `src/` to host the action; the endpoint is fully usable (GMs can hit `GET /events/{id}/runsheet/{template}` directly, e.g. from a DocuDesk-driven link). The action + modal need a custom event-detail section component — a nc-vue follow-up. i18n: no new client strings were added (no new component). `appinfo` bumped 0.1.26 -> 0.1.27.

## 3. Quality

- [x] 3.1 `@spec` annotations on new methods (gate-16) + SPDX headers (gate-1)
- [x] 3.2 `composer check` green (php -l + phpcs 0 errors + PHPUnit 95 green); hydra gates green on the diff (gate-5/9 on the new route, gate-16, gate-17 redundant-controller PASS, gate-22 manifest-validation PASS)

## 4. Tests

- [x] 4.1 PHPUnit `EventsControllerTest`: 401 unauth, 403 non-GM, 424 DocuDesk-absent, 400 non-UUID template, 404 event-not-found, 404 template-not-found, success (cast derivation + name sort + unique-items rollup + player-name fallback), filename fallback, 500 render-failure, NC-admin allowed (10 tests)
- [~] 4.2 Newman: DEFERRED — GM 200 / non-GM 403 / DocuDesk-absent 424 require a live NC+OR env with the `gamemasters` group and DocuDesk; the same matrix is covered by PHPUnit without env dependency
- [x] 4.3 Playwright `tests/e2e/spec-coverage/event-runsheet-export.spec.ts`: run-sheet endpoint wired + authenticated (the UI action + modal scenarios are deferred with the frontend in 2.x; gate-19 back-reference)

## 5. Spec sync

- [x] 5.1 On archive, sync the RUN-* additions into `openspec/specs/pdf-export/spec.md`
- [x] 5.2 `docs/FEATURES.md` updated (GM run-sheet / cast-list export)

## Acceptance criteria

- A GM downloads a per-event run-sheet PDF through a DocuDesk template; the cast list carries player names, stored computed stats, conditions, items, GM notes, and the unique-items-in-play rollup. Done (endpoint + context builder; the in-app action/modal is deferred to nc-vue).
- A non-GM gets a 403 and DocuDesk-absent degrades to 424 exactly like character sheets. Done (10 PHPUnit tests).
- The shared DocuDesk helper leaves `downloadPdf()` behaviour untouched (existing pdf-export tests stay green). Done (downloadPdf unchanged; 95 tests green).
- New strings: none added (no new component); `composer check` and hydra gates pass.
