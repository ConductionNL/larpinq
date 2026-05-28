# manifest-v2-schema — Specification

## Purpose

Defines the v2 JSON Schema for the Conduction app manifest, the per-slot grid coordinate system, the unified widget and action shapes, and the `$schema`-based validator dispatch in `useAppManifest`. This spec is the head of the three-spec v2 chain; `manifest-v2-renderer` and `manifest-v2-codemod` depend on it.

**New artifacts introduced by this capability:**
- `src/schemas/app-manifest-v2.schema.json`
- Updated `validateManifest()` export (thin glue, ≤20 LOC)
- Updated `src/composables/useAppManifest.js` dispatch (thin glue, ≤20 LOC)
- `docs/architecture/manifest.md` (docs stub update)
- Jest test files for v1 regression + v2 golden cases

---

## ADDED Requirements

### Requirement: REQ-MV2S-001 — v2 Schema File Location and URL

A JSON Schema file MUST exist at `src/schemas/app-manifest-v2.schema.json`. Its `$id` MUST be `https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest-v2.schema.json`. The v1 schema at `src/schemas/app-manifest.schema.json` MUST NOT be modified; its `$id` URL MUST remain stable.

#### Scenario: v2 schema file resolves at the canonical URL

- **WHEN** the file `src/schemas/app-manifest-v2.schema.json` is read
- **THEN** its `$id` field SHALL equal `https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest-v2.schema.json`

#### Scenario: v1 schema file is unchanged

- **WHEN** `src/schemas/app-manifest.schema.json` is read after this spec is applied
- **THEN** its `$id` and `version` fields SHALL be identical to their pre-change values

---

### Requirement: REQ-MV2S-002 — `$schema` Required at Top Level of v2 Manifests

A v2 manifest MUST include a `$schema` field at the top level whose value points to the v2 schema URL. The `$schema` field MUST be declared `required` in the v2 JSON Schema.

#### Scenario: v2 manifest without `$schema` fails validation

- **WHEN** a manifest `{ "version": "2.0.0", "menu": [], "pages": [] }` (no `$schema`) is validated against the v2 schema
- **THEN** validation SHALL fail with an error citing the missing `$schema` field

#### Scenario: v2 manifest with correct `$schema` passes validation

- **WHEN** a manifest includes `"$schema": "https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest-v2.schema.json"` plus valid `version`, `menu`, and `pages`
- **THEN** validation SHALL pass with no errors

---

### Requirement: REQ-MV2S-003 — All 11 v1.3.0 Page Types Supported

The v2 schema MUST enumerate all 11 page types in the `pages[].type` closed enum: `index`, `detail`, `dashboard`, `logs`, `settings`, `chat`, `files`, `form`, `wiki`, `map`, `custom`. Adding a new type requires a schema release.

#### Scenario: All 11 page types validate successfully

- **WHEN** a manifest contains one page of each type with valid required fields
- **THEN** validation SHALL pass for all 11 entries

#### Scenario: Unknown page type is rejected

- **WHEN** a manifest contains `"type": "wizard"` on a page
- **THEN** validation SHALL fail with an error on the `type` field citing the closed enum

---

### Requirement: REQ-MV2S-004 — Unified `widgets[]` Shape Across All Page Types

Every page entry in a v2 manifest MAY include a `widgets[]` array. The widget entry shape SHALL be uniform across all page types and MUST be defined via a single `$defs/widgetEntry` definition referenced from each page type's schema.

The `$defs/widgetEntry` shape MUST include:
- `widgetKey` (string, required) — registry key identifying the widget component
- `slot` (string, required) — placement slot; see REQ-MV2S-005
- `gridX` (integer ≥ 0, required) — 0-based column start
- `gridY` (integer ≥ 0, required) — 0-based row start
- `gridWidth` (integer 1..12, required) — column span
- `gridHeight` (integer ≥ 1, required) — row span
- `props` (object, optional) — widget-specific props passed at render time
- `tabGroup` (string, optional) — groups widget into a named tab within the slot
- `dataSource` (object, optional) — declarative data binding; shape carried forward from v1 `widgetDef`
- `_note` (string, optional for most widgets; required when `pages[].type == "custom"`) — human note

#### Scenario: Widget entry with all required fields validates

- **WHEN** a widget entry includes `widgetKey`, `slot`, `gridX`, `gridY`, `gridWidth`, `gridHeight`
- **THEN** validation SHALL pass

#### Scenario: Widget entry missing `widgetKey` fails validation

- **WHEN** a widget entry omits `widgetKey`
- **THEN** validation SHALL fail citing the missing required field

#### Scenario: Widget entry on an `index` page validates with the same shape as on `dashboard`

- **WHEN** an `index` page includes a `widgets[]` array with entries using the `$defs/widgetEntry` shape
- **THEN** validation SHALL pass identically to the same entries on a `dashboard` page

---

### Requirement: REQ-MV2S-005 — Per-Slot Grid Constraints Enforced by Schema

The v2 schema MUST enforce per-slot grid constraints via `allOf` + `if/then` clauses on `$defs/widgetEntry`. The following constraints SHALL apply:

| Slot | Constraint |
|------|-----------|
| `sidebar` | `gridWidth` MUST equal `1` |
| `header-actions` | `gridY` MUST equal `0` |
| `body`, `footer`, `modal`, `tab:*`, `section:*` | `gridWidth` MUST be in range 1..12 |

The cross-field constraint `gridX + gridWidth ≤ 12` MUST be documented in the schema's description fields and enforced at runtime by `validateManifest()` as a post-schema check (it cannot be expressed in JSON Schema alone).

Valid slot values SHALL be:
- Literal enum: `body`, `sidebar`, `header-actions`, `footer`, `modal`
- Pattern: `^(tab|section):.+` (e.g. `"tab:details"`, `"section:addresses"`)

#### Scenario: Widget in `sidebar` slot with `gridWidth: 1` validates

- **WHEN** a widget entry has `"slot": "sidebar"` and `"gridWidth": 1`
- **THEN** validation SHALL pass

#### Scenario: Widget in `sidebar` slot with `gridWidth: 3` fails validation

- **WHEN** a widget entry has `"slot": "sidebar"` and `"gridWidth": 3`
- **THEN** validation SHALL fail with an error citing the `gridWidth` constraint for `sidebar`

#### Scenario: Widget in `header-actions` slot with `gridY: 0` validates

- **WHEN** a widget entry has `"slot": "header-actions"` and `"gridY": 0`
- **THEN** validation SHALL pass

#### Scenario: Widget in `header-actions` slot with `gridY: 2` fails validation

- **WHEN** a widget entry has `"slot": "header-actions"` and `"gridY": 2`
- **THEN** validation SHALL fail with an error citing the `gridY` constraint for `header-actions`

#### Scenario: Widget in `tab:main` slot validates

- **WHEN** a widget entry has `"slot": "tab:main"`
- **THEN** validation SHALL pass (pattern `^(tab|section):.+` matches)

#### Scenario: `validateManifest()` rejects manifest where `gridX + gridWidth > 12`

- **WHEN** `validateManifest()` is called with a manifest containing a widget where `gridX: 8` and `gridWidth: 6` (`8 + 6 = 14 > 12`)
- **THEN** `validateManifest()` SHALL return an error listing the offending widget and the arithmetic violation

---

### Requirement: REQ-MV2S-006 — Unified `actions[]` Shape with Type Discriminator

Every page in a v2 manifest MAY include an `actions[]` array. Each action entry MUST follow the `$defs/action` shape:
- `id` (string, required) — unique within the page's actions
- `label` (string, required) — i18n key
- `type` (enum: `handler | open-modal | open-page | navigate`, optional, default `handler`) — action behaviour
- `target` (string, optional) — modal id, page id, or URL depending on `type`
- `props` (object, optional) — additional props forwarded to the handler or modal
- `handler` (string, optional) — registry key for `type: "handler"`

When `type` is omitted, the schema SHALL apply a default of `"handler"` (back-compatibility with v1.3.0 action declarations that predate the discriminator).

#### Scenario: Action without `type` field defaults to `handler`

- **WHEN** an action entry contains `{ "id": "delete", "label": "app.delete" }` (no `type`)
- **THEN** the Ajv-compiled schema with `useDefaults: true` SHALL set `type` to `"handler"` after validation

#### Scenario: Action with `type: "open-modal"` and a `target` validates

- **WHEN** an action entry contains `{ "id": "confirm", "label": "app.confirm", "type": "open-modal", "target": "confirm-dialog" }`
- **THEN** validation SHALL pass

#### Scenario: Action with unknown `type` fails validation

- **WHEN** an action entry contains `"type": "custom-action"`
- **THEN** validation SHALL fail citing the closed `type` enum

---

### Requirement: REQ-MV2S-007 — v1.3.0 Features Carried Forward in v2 Schema

The v2 schema MUST support all v1.3.0 features without regression. Specifically:

- `dataSource` on `widgetEntry` MUST be accepted with the same shape as v1 `widgetDef.dataSource`
- `@resolve:<key>` sentinels MUST be accepted in any string-typed config value (no schema-level rejection)
- `menu[].dynamicSource` MUST be accepted with the same shape as v1
- `pages[].config.sidebar.columnGroups` (index page named-view sidebar) MUST be accepted
- `pages[].config.cardComponent` (index page card grid) MUST be accepted
- `pages[].config.tabs[]` (settings page tab strips) MUST be accepted
- `dependencies[]` top-level array MUST be accepted
- `runtime` top-level object MUST be accepted (same shape as v1)

#### Scenario: v2 manifest with `@resolve:` sentinel passes validation

- **WHEN** a v2 manifest contains `"register": "@resolve:listing_register"` in a page config
- **THEN** validation SHALL pass (sentinel value is a valid string)

#### Scenario: v2 manifest with `menu[].dynamicSource` passes validation

- **WHEN** a v2 manifest contains a menu item with a `dynamicSource` object
- **THEN** validation SHALL pass

---

### Requirement: REQ-MV2S-008 — `type: "custom"` Requires `_note` Field

When a page entry has `"type": "custom"`, the v2 schema MUST require a `_note` field on that page entry documenting why decomposition into a standard page type was not feasible. This requirement is enforced by an `if/then` clause in `$defs/page`.

#### Scenario: Custom page with `_note` validates

- **WHEN** a page entry has `"type": "custom"` and includes `"_note": "Uses bespoke GIS viewer"`
- **THEN** validation SHALL pass

#### Scenario: Custom page without `_note` fails validation

- **WHEN** a page entry has `"type": "custom"` and omits `_note`
- **THEN** validation SHALL fail citing the missing `_note` field

---

### Requirement: REQ-MV2S-009 — `validateManifest()` Export Selects Schema by `$schema`

A `validateManifest(manifest: unknown): ValidationResult` function MUST be exported from `@conduction/nextcloud-vue`. It MUST:
1. Read `manifest.$schema` (if present)
2. If `$schema` ends with `/app-manifest-v2.schema.json` → validate with the v2 Ajv instance
3. Otherwise → validate with the v1 Ajv instance (back-compat default)
4. Emit `console.warn` if `$schema` is present but does not match either known schema URL
5. Return `{ valid: boolean, errors: AjvError[] | null }`
6. As a post-schema check, verify `gridX + gridWidth ≤ 12` for all widget entries in v2 manifests

#### Scenario: v2 manifest dispatches to v2 validator

- **WHEN** `validateManifest({ "$schema": "…/app-manifest-v2.schema.json", … })` is called
- **THEN** the v2 Ajv instance SHALL be used for validation

#### Scenario: v1 manifest (no `$schema`) dispatches to v1 validator

- **WHEN** `validateManifest({ "version": "1.0.0", "menu": [], "pages": [] })` is called (no `$schema`)
- **THEN** the v1 Ajv instance SHALL be used for validation

#### Scenario: Unknown `$schema` falls back to v1 with a warning

- **WHEN** `validateManifest({ "$schema": "https://example.com/unknown.json", … })` is called
- **THEN** the v1 Ajv instance SHALL be used and `console.warn` SHALL be called once

#### Scenario: Post-schema grid check catches arithmetic violation

- **WHEN** a v2 manifest contains a widget with `gridX: 9` and `gridWidth: 5`
- **THEN** `validateManifest()` SHALL return `valid: false` with an error describing the `gridX + gridWidth > 12` violation

---

### Requirement: REQ-MV2S-010 — Thin-Glue `useAppManifest` Dispatch on `$schema`

`useAppManifest` MUST dispatch to the correct validator after loading or merging a manifest. The dispatch logic MUST be ≤ 20 LOC and MUST NOT duplicate business logic already in `validateManifest()`. The composable MUST call `validateManifest(mergedManifest)` internally and act on its `valid` result.

#### Scenario: v2 manifest loaded by useAppManifest passes v2 validation

- **WHEN** `useAppManifest` loads a manifest with `$schema` pointing to v2
- **THEN** it SHALL use `validateManifest()` which dispatches to the v2 validator
- **THEN** if validation passes, `manifest.value` SHALL reflect the loaded v2 manifest

#### Scenario: Invalid v2 manifest causes useAppManifest to fall back to bundled

- **WHEN** `useAppManifest` receives a BE-merged manifest with `$schema` pointing to v2 but invalid (e.g., `gridWidth: 15`)
- **THEN** `manifest.value` SHALL revert to the bundled manifest and `console.warn` SHALL be called

---

### Requirement: REQ-MV2S-011 — v1 Manifests Continue to Validate Against v1 Schema Unchanged

No change to the v1 schema. Any manifest that passed validation before this spec MUST continue to pass. The v1 Ajv instance used by `validateManifest()` MUST be compiled from the unmodified `app-manifest.schema.json`.

#### Scenario: v1 manifest validates identically before and after spec is applied

- **WHEN** a manifest that previously validated against `app-manifest.schema.json` is validated via `validateManifest()` after this spec is applied
- **THEN** the result SHALL be `{ valid: true, errors: null }` with no new errors

#### Scenario: v1 manifest that previously failed validation still fails

- **WHEN** a manifest that previously failed v1 validation (e.g., missing required `pages` field) is validated via `validateManifest()` after this spec is applied
- **THEN** the result SHALL be `{ valid: false, errors: [...] }` with the same errors as before
