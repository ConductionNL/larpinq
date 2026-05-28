# Tasks: manifest-v2-schema

## 1. v2 JSON Schema File

- [x] 1.1 Create `src/schemas/app-manifest-v2.schema.json` with `$schema: "https://json-schema.org/draft/2020-12/schema"`, `$id` pointing to the v2 canonical URL, and `required: ["$schema", "version", "menu", "pages"]`
- [x] 1.2 Add `$defs/widgetEntry` with fields `widgetKey`, `slot`, `gridX`, `gridY`, `gridWidth`, `gridHeight`, `props`, `tabGroup`, `dataSource`, `_note`; apply `allOf` + `if/then` clauses for per-slot grid constraints (sidebar: `gridWidth const: 1`; header-actions: `gridY const: 0`)
- [x] 1.3 Add `slot` field definition using `oneOf` combining a literal `enum` (`body`, `sidebar`, `header-actions`, `footer`, `modal`) and a `pattern`-string (`^(tab|section):.+`)
- [x] 1.4 Add `$defs/action` with `id`, `label`, `type` (enum `handler|open-modal|open-page|navigate`, default `handler`), `target`, `props`, `handler`
- [x] 1.5 Add `$defs/page` with `id`, `route`, `type` (closed enum of all 11 types), `title`, `widgets[]` (`$ref` widgetEntry), `actions[]` (`$ref` action), `config` (open object carrying forward all v1.3.0 config shapes), `permission`, `_note`; add `if/then` clause requiring `_note` when `type == "custom"`
- [x] 1.6 Carry forward `$defs/menuItem` (including `dynamicSource`, `visibleIf`, `children[]`, `section`, `action`, `href`) from v1 schema into the v2 schema via `$defs` (copy, do not `$ref` across schemas)
- [x] 1.7 Add top-level properties: `$schema` (required, URI format), `version` (semver pattern), `menu` (`$ref` menuItem array), `pages` (`$ref` page array), `dependencies` (string array, default `[]`), `runtime` (open object)
- [x] 1.8 Add description text documenting the `gridX + gridWidth ≤ 12` constraint (not enforced by schema itself — enforced by `validateManifest()` post-check) on the `widgetEntry.$defs` and on the `gridX`/`gridWidth` field descriptions

## 2. Validator Export (`validateManifest`)

- [x] 2.1 Locate or create `src/utils/validateManifest.js` (existing location confirmed) (or the existing export location); compile two Ajv instances at module init: `ajvV1` from `app-manifest.schema.json`, `ajvV2` from `app-manifest-v2.schema.json`; both with `useDefaults: true`, `allErrors: true`
- [x] 2.2 Add a new function `validateManifestV2(manifest)` in `src/utils/validateManifest.js` (or a sibling `validateManifestV2.js` module imported by `validateManifest.js`): compile one Ajv instance against `app-manifest-v2.schema.json` at module init with `useDefaults: true`, `allErrors: true`, `strict: false`; load `ajv-formats` for `format: uri`; return `{ valid, errors }` in the same shape v1 returns (string[] error messages, not Ajv ErrorObject array — convert ajv errors to bracket-path strings to match v1's error format)
- [x] 2.3 Add post-schema checks in `validateManifestV2()` (after Ajv pass): iterate `pages[].widgets[]` and assert `gridX + gridWidth ≤ 12`; check `pages[].id` uniqueness; mirror v1's `@resolve:` sentinel rejection on registry-key paths (`pages[].component`, `pages[].headerComponent`, `pages[].actionsComponent`, `pages[].slots.*`, `pages[].id`, `pages[].route`, `menu[].id`, `menu[].route`, `dependencies[]`); push string error entries in the v1 format
- [x] 2.4 Add a dispatch wrapper inside the existing `validateManifest()`: read `manifest.$schema`; when it's a string ending with `/app-manifest-v2.schema.json`, delegate to `validateManifestV2()` and return its result; otherwise fall through to the existing v1 logic unchanged. Emit `console.warn` once when `$schema` is present but matches neither known URL. Export `validateManifestV2` from `src/utils/index.js` (or wherever the existing `validateManifest` is exported) alongside the existing `validateManifest` export.

## 3. `useAppManifest` Thin-Glue Dispatch

- [x] 3.1 Open `src/composables/useAppManifest.js`; confirm that it already calls `validateManifest(mergedManifest)`. If so, no code change is required — `validateManifest`'s new dispatch logic handles v2 transparently. If the composable calls validation differently, add at most 5-10 LOC to route through `validateManifest()`. Aim for **zero new LOC** in this file when possible; the thin-glue exception is about the dispatch logic in `validateManifest`, not the composable.
- [x] 3.2 Verify the total new/changed LOC in `useAppManifest.js` is ≤ 20 (target: 0). The dispatch lives in `validateManifest()`, not the composable.

## 4. Docs Stub

- [x] 4.1 Open `docs/architecture/manifest.md`; add a "v2 Schema" section after the existing v1 content covering: the new `$schema` field, the unified `widgets[]` model, the per-slot grid system (slot taxonomy table), the action type discriminator, and a pointer to the full migration guide (coming in `manifest-v2-codemod`)

## 5. Jest Tests

- [x] 5.1 Create `tests/schemas/app-manifest-v2.schema.spec.js`; write positive ("golden") validation cases:
  - Minimal valid v2 manifest (empty menu/pages + `$schema` + `version`)
  - Manifest with all 11 page types represented
  - Widget entries in each slot type (body, sidebar, header-actions, footer, modal, tab:details, section:info)
  - Per-slot grid constraints: sidebar `gridWidth: 1` passes; header-actions `gridY: 0` passes
  - `type: "custom"` page with `_note` passes
  - Action with no `type` field (default `handler`) passes
  - v1.3.0 feature carry-forward: `@resolve:` sentinel, `dynamicSource`, `cardComponent`, `dependencies[]`
- [x] 5.2 Create `tests/schemas/validate-manifest.spec.js`; write dispatch and post-check cases:
  - v2 manifest dispatches to v2 validator
  - v1 manifest (no `$schema`) dispatches to v1 validator
  - Unknown `$schema` falls back to v1 + triggers `console.warn`
  - `gridX + gridWidth > 12` returns `valid: false` with arithmetic error message
- [x] 5.3 Create `tests/schemas/app-manifest-v1-regression.spec.js`; copy the existing v1 golden and failure fixtures; run them through `validateManifest()` and assert the same pass/fail results as before this spec

## 6. Verification

- [x] 6.1 Run `npm run lint` and fix all warnings/errors
- [x] 6.2 Run `npm run test` (or `npm run test:unit`) and confirm all new tests pass, no existing tests regressed
- [x] 6.3 Run `npm run build` and confirm the build succeeds and `validateManifest` appears in the output
- [x] 6.4 Run `openspec validate manifest-v2-schema --strict` and confirm all 4 artifacts pass
