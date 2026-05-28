# Tasks: manifest-v2-codemod

## 1. Setup and CLI library

- [x] 1.1 Add `commander` to `devDependencies` in `package.json` (spec_ref: design.md D1; files_likely_affected: `package.json`)
- [x] 1.2 Add `"manifest-migrate": "src/cli/manifest-migrate.js"` to the `bin` field in `package.json` (spec_ref: design.md D2; files_likely_affected: `package.json`)
- [x] 1.3 Add a `build:cli` script to `rollup.config.js` (or equivalent) that compiles `src/cli/manifest-migrate.js` to a CJS executable at `dist/cli/manifest-migrate.js` with a `#!/usr/bin/env node` shebang (spec_ref: design.md D2; files_likely_affected: `rollup.config.js`)

## 2. CLI entry point and argument parsing

- [x] 2.1 Create `src/cli/manifest-migrate.js` with `commander` program, all four flags (`--input`, `--output`, `--validate-only`, `--report`, `--dry-run`), and top-level orchestration logic (spec_ref: specs/manifest-v2-codemod/spec.md "CLI binary shape and flags"; files_likely_affected: `src/cli/manifest-migrate.js`)
- [x] 2.2 Implement file I/O helpers: read JSON from `--input`, write JSON to `--output`, write Markdown to `--report`; ensure `--output` defaults to `--input` path (spec_ref: specs/manifest-v2-codemod/spec.md "CLI binary shape and flags"; files_likely_affected: `src/cli/manifest-migrate.js`)
- [x] 2.3 Implement exit-code logic: 0 on success, 1 on any error; print errors to stderr (spec_ref: specs/manifest-v2-codemod/spec.md "Exit codes"; files_likely_affected: `src/cli/manifest-migrate.js`)

## 3. Idempotency and validate-only mode

- [x] 3.1 Implement schema-version detection: read `$schema` from the parsed manifest and return `"v1"` or `"v2"` (or `"unknown"`); re-use `validateManifest()` from `manifest-v2-schema` spec (spec_ref: specs/manifest-v2-codemod/spec.md "Idempotency on v2 input"; files_likely_affected: `src/cli/manifest-migrate.js`)
- [x] 3.2 Implement idempotency guard: if detected schema is v2, validate and exit 0 without writing (spec_ref: specs/manifest-v2-codemod/spec.md "Idempotency on v2 input"; files_likely_affected: `src/cli/manifest-migrate.js`)
- [x] 3.3 Implement `--validate-only` mode: detect schema, validate, print summary, exit (spec_ref: specs/manifest-v2-codemod/spec.md "validate-only mode"; files_likely_affected: `src/cli/manifest-migrate.js`)

## 4. Transformation functions — migration matrix

- [x] 4.1 Implement `mergeDashboardWidgetsAndLayout(page)` — merge `widgets[]` + `layout[]` into unified `widgets[]`, remove `layout[]` (spec_ref: specs/manifest-v2-codemod/spec.md "Merge widgets and layout per dashboard page"; files_likely_affected: `src/cli/transforms/mergeDashboardWidgetsAndLayout.js`)
- [x] 4.2 Implement `liftSidebarTabWidgets(page)` — lift `sidebarTabs[].widgets[]` to top-level with `slot: "sidebar"` + `tabGroup`, remove `sidebarTabs` (spec_ref: specs/manifest-v2-codemod/spec.md "Lift sidebar tab widgets"; files_likely_affected: `src/cli/transforms/liftSidebarTabWidgets.js`)
- [x] 4.3 Implement `flattenSettingsSectionWidgets(page)` — flatten `sections[].widgets[]` to `slot: "section:<id>"`, remove `sections` (spec_ref: specs/manifest-v2-codemod/spec.md "Flatten settings section widgets"; files_likely_affected: `src/cli/transforms/flattenSettingsSectionWidgets.js`)
- [x] 4.4 Implement `flattenSettingsTabs(page)` — flatten `tabs[].widgets[]` to `slot: "tab:<id>"`, remove `tabs` (spec_ref: specs/manifest-v2-codemod/spec.md "Flatten settings tabs"; files_likely_affected: `src/cli/transforms/flattenSettingsTabs.js`)
- [x] 4.5 Implement `migrateCardComponent(page)` — prepend `card-grid` widget entry from `cardComponent`, remove `cardComponent` (spec_ref: specs/manifest-v2-codemod/spec.md "Migrate cardComponent to card-grid widget"; files_likely_affected: `src/cli/transforms/migrateCardComponent.js`)
- [x] 4.6 Implement `addExplicitActionTypes(page)` — add `type: "handler"` to any action entry missing `type` (spec_ref: specs/manifest-v2-codemod/spec.md "Add explicit action type"; files_likely_affected: `src/cli/transforms/addExplicitActionTypes.js`)
- [x] 4.7 Implement `carryForwardVerbatimFields(manifest)` — ensure `dataSource`, `@resolve:` prefixed keys, `dynamicSource`, and named-view sidebar config pass through unchanged (spec_ref: specs/manifest-v2-codemod/spec.md "Carry forward dataSource verbatim"; files_likely_affected: `src/cli/transforms/carryForwardVerbatimFields.js`)
- [x] 4.8 Implement `handleCustomPages(manifest, reportBuilder)` — distinguish trivial vs non-trivial `type: "custom"` pages; add `_note` markers and emit report entries (spec_ref: specs/manifest-v2-codemod/spec.md "Convert trivial custom page"; files_likely_affected: `src/cli/transforms/handleCustomPages.js`)
- [x] 4.9 Implement `updateSchemaField(manifest)` — set `$schema` to v2 URL (spec_ref: specs/manifest-v2-codemod/spec.md "Update $schema to v2 URL"; files_likely_affected: `src/cli/transforms/updateSchemaField.js`)
- [x] 4.10 Implement `migrateCustomComponents(manifest)` — move `customComponents` entries to `registry` map with `kind: "component"`, remove `customComponents` (spec_ref: specs/manifest-v2-codemod/spec.md "Map customComponents to registry"; files_likely_affected: `src/cli/transforms/migrateCustomComponents.js`)
- [x] 4.11 Create `src/cli/pipeline.js` — compose all transform functions in order, return `{ transformed, reportItems }` (spec_ref: design.md D3; files_likely_affected: `src/cli/pipeline.js`)

## 5. Report writer

- [x] 5.1 Implement `src/cli/reportBuilder.js` — accumulate TODO items and registry suggestions during the transform pipeline (spec_ref: specs/manifest-v2-codemod/spec.md "Report output shape"; files_likely_affected: `src/cli/reportBuilder.js`)
- [x] 5.2 Implement `renderReport(items) → string` — produce the Markdown document with summary, per-page sections, registry suggestions, and carried-forward fields sections (spec_ref: specs/manifest-v2-codemod/spec.md "Report output shape"; files_likely_affected: `src/cli/reportBuilder.js`)
- [x] 5.3 Wire `--report <path>` in the CLI entry point to call `renderReport` and write to file; in `--dry-run` mode write to stderr instead (spec_ref: specs/manifest-v2-codemod/spec.md "dry-run mode"; files_likely_affected: `src/cli/manifest-migrate.js`)

## 6. Test corpus

- [x] 6.1 Copy HEAD snapshot of `procest/src/manifest.json` (from `procest` repo `development` branch) into `tests/fixtures/v1-manifests/procest-v1.json` (spec_ref: specs/manifest-v2-codemod/spec.md "Test corpus validation"; files_likely_affected: `tests/fixtures/v1-manifests/procest-v1.json`)
- [x] 6.2 Copy HEAD snapshot of `pipelinq/src/manifest.json` into `tests/fixtures/v1-manifests/pipelinq-v1.json` (spec_ref: specs/manifest-v2-codemod/spec.md "Test corpus validation"; files_likely_affected: `tests/fixtures/v1-manifests/pipelinq-v1.json`)
- [x] 6.3 Copy HEAD snapshot of `softwarecatalog/src/manifest.json` into `tests/fixtures/v1-manifests/softwarecatalog-v1.json` (spec_ref: specs/manifest-v2-codemod/spec.md "Test corpus validation"; files_likely_affected: `tests/fixtures/v1-manifests/softwarecatalog-v1.json`)
- [x] 6.4 Copy HEAD snapshot of `decidesk/src/manifest.json` into `tests/fixtures/v1-manifests/decidesk-v1.json` (spec_ref: specs/manifest-v2-codemod/spec.md "Test corpus validation"; files_likely_affected: `tests/fixtures/v1-manifests/decidesk-v1.json`)

## 7. Tests

- [x] 7.1 Write Jest unit tests for `mergeDashboardWidgetsAndLayout` — golden-case + edge cases (no layout, empty widgets) (spec_ref: specs/manifest-v2-codemod/spec.md "Merge widgets and layout"; files_likely_affected: `tests/cli/transforms/mergeDashboardWidgetsAndLayout.test.js`)
- [x] 7.2 Write Jest unit tests for `liftSidebarTabWidgets`, `flattenSettingsSectionWidgets`, `flattenSettingsTabs` (spec_ref: specs/manifest-v2-codemod/spec.md; files_likely_affected: `tests/cli/transforms/*.test.js`)
- [x] 7.3 Write Jest unit tests for `migrateCardComponent`, `addExplicitActionTypes`, `migrateCustomComponents` (spec_ref: specs/manifest-v2-codemod/spec.md; files_likely_affected: `tests/cli/transforms/*.test.js`)
- [x] 7.4 Write Jest unit tests for `handleCustomPages` — trivial custom (in customComponents) and non-trivial custom (not in customComponents) (spec_ref: specs/manifest-v2-codemod/spec.md "Convert trivial custom page"; files_likely_affected: `tests/cli/transforms/handleCustomPages.test.js`)
- [x] 7.5 Write Jest unit tests for `updateSchemaField` and `carryForwardVerbatimFields` (spec_ref: specs/manifest-v2-codemod/spec.md; files_likely_affected: `tests/cli/transforms/*.test.js`)
- [x] 7.6 Write Jest integration tests running the full pipeline on each corpus fixture and asserting v2 schema validity (spec_ref: specs/manifest-v2-codemod/spec.md "Test corpus validation"; files_likely_affected: `tests/cli/pipeline.test.js`)
- [x] 7.7 Write Jest unit tests for idempotency: run pipeline twice on a v2 manifest, assert second output equals first (spec_ref: specs/manifest-v2-codemod/spec.md "Idempotency on v2 input"; files_likely_affected: `tests/cli/pipeline.test.js`)
- [x] 7.8 Write Jest unit tests for exit-code and error handling (missing file, invalid JSON, validation failure) (spec_ref: specs/manifest-v2-codemod/spec.md "Exit codes"; files_likely_affected: `tests/cli/manifest-migrate.test.js`)

## 8. Documentation

- [x] 8.1 Create `docs/migrating-to-v2.md` — full migration guide: CLI installation, all flags with examples, migration matrix table, manual-migration patterns, report interpretation, known pitfalls (spec_ref: specs/manifest-v2-codemod/spec.md "Migration documentation"; files_likely_affected: `docs/migrating-to-v2.md`)
- [x] 8.2 Update `docs/architecture/manifest.md` — add "Migrating to v2" section referencing the codemod and linking to `docs/migrating-to-v2.md` (spec_ref: specs/manifest-v2-codemod/spec.md "Migration documentation"; files_likely_affected: `docs/architecture/manifest.md`)

## 9. Build and quality gates

- [x] 9.1 Run `npm run lint` and fix any lint errors in the new CLI files (files_likely_affected: `src/cli/**`)
- [x] 9.2 Run `npm test` and confirm all new tests pass and existing tests are unaffected (files_likely_affected: `tests/cli/**`)
- [x] 9.3 Run `npm run build` and confirm build succeeds (files_likely_affected: `dist/`)
- [x] 9.4 Run `openspec validate manifest-v2-codemod --strict` and confirm 0 errors (files_likely_affected: none)
