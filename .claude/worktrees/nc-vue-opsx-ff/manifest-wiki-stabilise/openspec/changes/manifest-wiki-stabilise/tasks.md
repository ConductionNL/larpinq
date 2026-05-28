# Tasks: Stabilise `type:'wiki'`

## Phase 1 — Schema

- [x] Add 11 typed string fields under `pages[].config` `properties` in `src/schemas/app-manifest-v2.schema.json`:
  - `contentField`, `titleField`, `idParam`, `treeField`, `sidebarTitleField`
  - `sidebarRegister`, `sidebarSchema`
  - `emptyText`, `emptyDescription`, `emptyBodyText`, `emptyBodyDescription`
- [x] Each declared as `{ "type": "string" }` with a description pointing at the matching `CnWikiPage` prop.
- [x] Bump schema `version` 2.0.0 → 2.4.0.

## Phase 2 — Validator

- [x] Extend the `case 'wiki':` branch of `validateTypeConfig` (in `src/utils/validateManifest.js`) to call a new `validateWikiConfigFields(cfg, …)` helper alongside the existing register/schema check.
- [x] The helper iterates the 11 known fields and emits `${pathSlash}/<field>: must be a string when set` for each non-string value present. Omitted fields are tolerated; unknown keys pass.

## Phase 3 — Tests

- [x] `tests/utils/validateManifest.wikiStabilise.spec.js`:
  - omitted optional fields validate.
  - well-formed wiki config validates.
  - missing register/schema rejects (regression check).
  - any single field as non-string rejects with the path.
  - unknown key passes (forward-compat).

## Phase 4 — Docs

- [x] In `docs/components/cn-wiki-page.md`, add a "Manifest config reference" section listing the 11 fields with their CnWikiPage prop counterparts.
- [x] Cross-link from the schema-coverage status doc if present.
