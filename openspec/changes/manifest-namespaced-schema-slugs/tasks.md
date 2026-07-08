# Tasks: manifest-namespaced-schema-slugs

## Implementation Tasks

### Task 1: Repoint the 7 event schema references in src/manifest.json
- **spec_ref**: `openspec/changes/manifest-namespaced-schema-slugs/specs/events-players/spec.md#requirement-event-crud-operations`
- **files**: `src/manifest.json`
- **acceptance_criteria**:
  - GIVEN line 30 (onboarding `advanceOn` "create-event" rule) WHEN edited THEN `"schema": "event"` becomes `"schema": "larping_event"`
  - GIVEN line 86 (dashboard KPI widget `kpi-events`) WHEN edited THEN `"schema": "event"` becomes `"schema": "larping_event"`
  - GIVEN line 90 (dashboard object-list widget `recent-events`) WHEN edited THEN `"schema": "event"` becomes `"schema": "larping_event"`
  - GIVEN line 383 (Events index page `config.schema`) WHEN edited THEN `"schema": "event"` becomes `"schema": "larping_event"`
  - GIVEN line 392 (Events detail page `schema`) WHEN edited THEN `"schema": "event"` becomes `"schema": "larping_event"`
  - GIVEN line 448 (setting-detail `setting-events-kpi` widget) WHEN edited THEN `"schema": "event"` becomes `"schema": "larping_event"`
  - GIVEN line 450 (setting-detail `setting-events` object-list widget) WHEN edited THEN `"schema": "event"` becomes `"schema": "larping_event"`
- [ ] Implement
- [ ] Test

### Task 2: Repoint the 4 item schema references in src/manifest.json
- **spec_ref**: `openspec/changes/manifest-namespaced-schema-slugs/specs/game-mechanics/spec.md#requirement-item-crud`
- **files**: `src/manifest.json`
- **acceptance_criteria**:
  - GIVEN line 87 (dashboard KPI widget `kpi-items`) WHEN edited THEN `"schema": "item"` becomes `"schema": "larping_item"`
  - GIVEN line 267 (Items index page `config.schema`) WHEN edited THEN `"schema": "item"` becomes `"schema": "larping_item"`
  - GIVEN line 276 (Items detail page `schema`) WHEN edited THEN `"schema": "item"` becomes `"schema": "larping_item"`
  - GIVEN line 358 (effect-detail `effect-items` object-list widget) WHEN edited THEN `"schema": "item"` becomes `"schema": "larping_item"`
- [ ] Implement
- [ ] Test

### Task 3: Verify no residual bare-slug references remain
- **spec_ref**: `openspec/changes/manifest-namespaced-schema-slugs/specs/events-players/spec.md#requirement-event-crud-operations`
- **files**: `src/manifest.json`
- **acceptance_criteria**:
  - GIVEN `src/manifest.json` after Tasks 1-2 WHEN grepped for `"schema": "event"` or `"schema": "item"` THEN zero matches remain
  - GIVEN the same file WHEN grepped for `"schema": "larping_event"` THEN exactly 7 matches, and for `"schema": "larping_item"` THEN exactly 4 matches
- [ ] Implement
- [ ] Test

## Verification
- All tasks checked off
- `openspec validate` passes
- Manual testing against acceptance criteria (see test-plan.md TC-1 through TC-4)
- Code review against spec requirements

## Tests (company-wide ADR-009)
- No PHP business logic changes — pure JSON manifest edit; PHPUnit N/A
- Newman/Postman N/A — no API endpoint changes
- Browser tests (Playwright MCP) covering TC-1 through TC-4 from test-plan.md
- `openspec validate` passes

## Documentation (company-wide ADR-010)
- N/A — internal bug fix, no user-facing behavior change beyond "the pages now show the correct fields" (not a new feature to document)

## i18n (company-wide ADR-005)
- N/A — no new user-facing strings; only a `"schema"` value repoint

## Quality checklist

- All new/changed business logic covered by PHPUnit unit tests (`tests/Unit/`) — N/A, no PHP changes
- New/changed API endpoints covered by Newman/Postman tests — N/A, no API changes
- UI changes covered by Playwright browser tests
- All tests pass (`composer test`, `newman run`)
- Feature documentation updated in `docs/` if user-facing (ADR-010) — N/A, bug fix
- Dutch (`nl_NL`) and English (`en_US`) translation strings added for any new user-facing strings (ADR-007) — N/A, no new strings
- `openspec validate` passes
