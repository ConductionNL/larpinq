# Design: manifest-v2-schema

## Context

`@conduction/nextcloud-vue` ships a JSON-driven manifest system introduced in `add-json-manifest-renderer`. The v1 schema (`app-manifest.schema.json`, current version 1.5.0) covers 11 page types and a widget system for `dashboard` pages. Subsequent changes added form pages, wiki pages, map pages, settings orchestration, and live-update widgets — but each extended `config` as a free-form object, leaving widget placement unspecified for non-dashboard pages.

Hydra ADR-036 (merged in PR #305) mandates a v2 schema that:
1. Makes `widgets[]` a **uniform first-class array on every page type** (not just dashboard)
2. Introduces a **per-slot grid coordinate system** (`slot`, `gridX`, `gridY`, `gridWidth`, `gridHeight`)
3. Adds a **typed `actions[]` discriminator** (`handler | open-modal | open-page | navigate`)
4. Requires `$schema` at the top level so validators can detect which version to apply

This spec (change 1 of 3) delivers the schema file and the thin-glue validator wiring. The renderer components that consume v2 layout data land in spec 2 (`manifest-v2-renderer`). The per-app codemod migrating v1 manifests to v2 lands in spec 3 (`manifest-v2-codemod`).

**Validator architecture (revised after codebase audit, 2026-05-19):** nc-vue's existing `src/utils/validateManifest.js` is **1106 lines of hand-rolled rule-by-rule validation, NOT Ajv-based**. There is no Ajv anywhere in the dependency tree. To avoid a big-bang migration of the entire v1 validator, this spec keeps v1 hand-rolled and unchanged, and adds **Ajv exclusively for v2**: a new `validateManifestV2(manifest)` function uses Ajv against the v2 schema + applies cross-field post-checks for the `gridX + gridWidth ≤ 12` arithmetic constraint. `validateManifest()` dispatches by reading `$schema`: when it ends with `/app-manifest-v2.schema.json`, delegates to `validateManifestV2`; otherwise the existing v1 path runs unchanged.

This means the spec adds:
- A new `ajv` + `ajv-formats` devDep (small, common, no runtime cost — they are needed by `validateManifestV2` which IS used at runtime)
- `validateManifestV2()` (~150 LOC: Ajv compile + dispatch wrapper + post-schema arithmetic + uniqueness checks + sentinel-path rejection logic mirroring v1's `@resolve:` rules)
- A small dispatch addition to `validateManifest()` (~15 LOC)
- A thin-glue addition to `useAppManifest.js` (none required — `useAppManifest` already calls `validateManifest`, which now dispatches internally; ≤5 LOC for any clarification comment)

The thin-glue exception under ADR-032 covers the validateManifest dispatch + useAppManifest changes. `validateManifestV2` itself is new code attached to the new schema capability — it's the "lifecycle code" that ADR-032 expects to live alongside its declarative artifact.

## Goals / Non-Goals

**Goals:**
- Canonical v2 JSON Schema at a stable URL (GitHub raw, `main` branch)
- v1 schema URL stays unchanged — deployed v1 manifests remain valid
- `validateManifest(manifest)` export selects schema by `$schema` field
- `useAppManifest` thin-glue dispatches to v1 or v2 Ajv instance based on `$schema`
- Jest tests: v1 regression suite green, v2 positive validation cases green
- Docs stub in `docs/architecture/manifest.md` introducing v2

**Non-Goals:**
- Renderer components that consume v2 layout (spec 2)
- Per-app codemod migrating `manifest.json` files (spec 3)
- Full migration guide (spec 3)
- Invalid-case/negative schema tests (land with spec 2 + 3 where failure modes are exercised)
- `registry` prop on `CnAppRoot` (runtime concern, spec 2)

## Decisions

### Decision 1: Keep v1 schema at its existing `$id` URL

**Choice:** `src/schemas/app-manifest.schema.json` is NOT renamed or modified. Its `$id` (`https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest.schema.json`) remains canonical for v1.

**Rationale:** Multiple deployed apps have `$schema` or tooling pointed at the v1 URL. Changing it would silently break editor validation in every consuming app. The v2 schema gets a new `$id` at `…/app-manifest-v2.schema.json`.

**Alternative considered:** Replacing v1 in-place with a `version: "2.0.0"` bump. Rejected because it would be a silent breaking change for any consumer that pins to the raw GitHub URL.

### Decision 2: `$schema` as the version discriminator

**Choice:** The validator reads `manifest.$schema` and selects the Ajv instance accordingly:
- `$schema` ends with `/app-manifest-v2.schema.json` → v2 validator
- anything else (including absent) → v1 validator (back-compat default)

**Rationale:** `$schema` is the JSON Schema standard for declaring the validator. Using the manifest content's own `version` semver would require a separate mapping table. Using `$schema` is idiomatic and self-documenting.

**Edge cases handled:** Malformed `$schema` (not a URI, or not matching known schemas) → fall back to v1 validator and emit a `console.warn`. Mixed v1+v2 manifests in the same monorepo are fine — each manifest declares its own `$schema`.

### Decision 3: `allOf` + `if/then` for per-slot grid constraints

**Choice:** The v2 schema encodes per-slot grid constraints via `$defs/widgetEntry` with an `allOf` array containing one `if/then` clause per slot category:

```jsonc
// sidebar slot: gridWidth must be 1
{
  "if": { "properties": { "slot": { "const": "sidebar" } }, "required": ["slot"] },
  "then": { "properties": { "gridWidth": { "const": 1 } } }
},
// header-actions slot: gridY must be 0
{
  "if": { "properties": { "slot": { "const": "header-actions" } }, "required": ["slot"] },
  "then": { "properties": { "gridY": { "const": 0 } } }
}
```

The cross-field constraint `gridX + gridWidth ≤ 12` cannot be expressed in pure JSON Schema (arithmetic over sibling fields). It is **documented in the schema's description fields** and enforced at runtime by `validateManifest()` with a post-schema check.

**Rationale:** `if/then` clauses in Ajv draft 2020-12 compile cleanly and produce informative error messages. The arithmetic constraint needs imperative code anyway — a thin post-validation check in `validateManifest()` keeps the schema pure and the logic co-located with the validator.

**Alternative considered:** `oneOf` per slot type (full property-set branching). Rejected because it requires repeating all non-slot widget fields in every branch, making the schema ~6× larger and harder to maintain.

### Decision 4: Unified widget shape across all page types

**Choice:** All page types share the same `$defs/widgetEntry` shape:
```jsonc
{
  "widgetKey": "string",        // registry key
  "slot": "body|sidebar|...",   // where to place it
  "gridX": integer,             // 0-based column
  "gridY": integer,             // 0-based row
  "gridWidth": integer,         // 1-12
  "gridHeight": integer,        // 1-N
  "props": object,              // optional widget props
  "tabGroup": string,           // optional: groups into a tab
  "dataSource": object,         // optional: declarative data binding
  "_note": string               // optional: human note; REQUIRED on custom pages
}
```

**Rationale:** One shape means one `$ref`, one TypeScript type, one renderer lookup. The v1 dashboard `widgetDef` shape diverges with `i`, `j`, `w`, `h` grid keys inherited from GridStack — v2 replaces those with `gridX/Y/Width/Height` for readability and slot independence.

### Decision 5: `actions[]` type discriminator with `handler` default

**Choice:** `pages[].actions[]` entries have a `type` field with values `handler | open-modal | open-page | navigate`. When `type` is omitted, it defaults to `handler` for back-compatibility with v1.3.0 manifests that declared actions without a type discriminator.

**Rationale:** The discriminator enables the renderer to dispatch to the correct handler without the consuming app having to inspect each action's shape. The `handler` default means zero churn on existing manifests.

**Schema approach:** The `$defs/action` def uses `"default": "handler"` on the `type` field. AJV applies defaults via `useDefaults: true` at compile time.

### Decision 6: Mixed-spec rationale (thin-glue exception)

This change introduces a schema file (pure config) and ≤20 LOC of validator wiring (code). The code is inseparable from the schema: without the `$schema`-dispatch, the schema file is unreachable and spec 2 cannot depend on it. ADR-032 explicitly permits this pattern as the "thin-glue exception" when glue LOC ≤ 20 and the code has no business logic of its own.

## Schema Design Details

### Schema URL convention

- **v1:** `https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest.schema.json`
- **v2:** `https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest-v2.schema.json`

Both resolve on the `main` branch. During development (on `beta`) editors can point at the raw beta URL; the `$id` always points at `main` to keep the canonical URL stable after merge.

### Slot value taxonomy

```
body             — main content area; gridWidth 1..12
sidebar          — side panel; gridWidth MUST be 1
header-actions   — top-right zone; gridY MUST be 0
footer           — bottom zone; gridWidth 1..12
modal            — overlay; gridWidth 1..12
tab:<id>         — named tab strip; gridWidth 1..12
section:<id>     — named section; gridWidth 1..12
```

`tab:*` and `section:*` are validated via `pattern: "^(tab|section):.+"` on the `slot` field alongside the closed enum of literal values. A single `oneOf` combining a `const` set with a pattern would be unwieldy; instead the schema uses:

```jsonc
"slot": {
  "oneOf": [
    { "enum": ["body", "sidebar", "header-actions", "footer", "modal"] },
    { "type": "string", "pattern": "^(tab|section):.+" }
  ]
}
```

### `@resolve:` sentinels and `dynamicSource`

Both are carry-forward from v1 without schema change. The v2 `$defs` reuse the same pattern-only validation (`"@resolve:"` prefix in any string value is legal; the loader substitutes at runtime). The `menu[].dynamicSource` object is copied verbatim from the v1 `$defs/menuItem`.

### v1.3.0 feature carry-forward

Every v1.3.0 feature is supported in v2 via `$defs` reuse or inline carry-forward:

| v1.3.0 feature | v2 location |
|----------------|-------------|
| `dataSource` on widgets | `widgetEntry.dataSource` |
| `@resolve:` sentinels | pattern validation on any string |
| Dynamic menu (`dynamicSource`) | `$defs/menuItem.dynamicSource` |
| Named-view sidebar (`columnGroups`) | `pages[].config.sidebar.columnGroups` |
| `cardComponent` on index config | `pages[].config.cardComponent` |
| `tabs[]` on settings config | `pages[].config.tabs[]` |
| `dependencies[]` | top-level array |
| `type: "custom"` | `pages[].type` enum |
| `_note` on custom pages | REQUIRED when `type == "custom"` |

### `type: "custom"` + `_note` enforcement

The `_note` requirement on custom pages is encoded as:

```jsonc
{
  "if": {
    "properties": { "type": { "const": "custom" } },
    "required": ["type"]
  },
  "then": { "required": ["_note"] }
}
```

This is in the `$defs/page` definition's `allOf`, not the top-level schema.

## Risks / Trade-offs

- **Malformed `$schema` in wild manifests** → the validator silently falls back to v1 + emits `console.warn`. A malformed `$schema` string (not a URI, or not matching either known schema URL) is treated as v1.
- **`gridX + gridWidth ≤ 12` not in JSON Schema** → documented in description fields; enforced by `validateManifest()` post-schema check. Failure message: `"Widget '{widgetKey}' in slot '{slot}': gridX ({gridX}) + gridWidth ({gridWidth}) exceeds 12"`.
- **Ajv compilation time on large schemas** → v2 schema is larger than v1 (more `$defs`, more `if/then` clauses). Ajv v8 compiles the v1 schema in <5ms on a modern laptop. v2 is estimated at <15ms. Both are compiled once at module init and cached; no per-validation overhead.
- **v1.3.0 feature regression** → mitigated by the v1 regression test suite in the jest test file. Any accidental breakage in the dispatch logic fails the suite before merge.
- **Mixed v1+v2 in monorepo** → each manifest declares its own `$schema`; `validateManifest()` handles each independently. No global state.

## Migration Plan

This spec introduces no migration burden on consuming apps — v1 manifests are unaffected. Consuming apps opt in to v2 by:
1. Adding `"$schema": "…/app-manifest-v2.schema.json"` to their manifest
2. Restructuring widget placement to use the new `widgets[]` + `slot` system

Step 2 is what spec 3 (`manifest-v2-codemod`) automates. The full migration guide also lands in spec 3.

## Open Questions

None blocking this spec. See `DEFERRED_QUESTIONS` in the task report for provisional decisions.
