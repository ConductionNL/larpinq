---
kind: config
---

# Proposal: manifest-v2-schema

## Why

The v1 manifest schema has grown through 11 page-type extensions, a widget system, and a settings orchestration change, but widgets are still first-class only on `dashboard` pages. The v2 schema introduces a **unified `widgets[]` array on every page type**, a **per-slot grid coordinate system**, and a **typed `actions[]` discriminator** — turning the manifest into a full layout description that the upcoming `CnPageRenderer` v2 can render without any per-page code. This is spec 1 of a 3-spec chain (see below); it establishes the canonical schema and thin-glue validator wiring before the renderer (spec 2) and the codemod (spec 3) land.

**Dependency**: Hydra ADR-036 (PR #305, merged 2026-05-19) is the upstream decision record. This spec implements the nc-vue side of that ADR.

## What Changes

### Chain overview

| # | Change | Kind | Status |
|---|--------|------|--------|
| 1 | **`manifest-v2-schema`** (this spec) | config | this PR |
| 2 | `manifest-v2-renderer` | code | depends on this |
| 3 | `manifest-v2-codemod` | code | depends on this |

The chain is split per ADR-032 (mixed config+code in one spec is an anti-pattern). The only code in this spec is the thin-glue `$schema`-dispatch in `useAppManifest` and the `validateManifest()` export (≤20 LOC total — the ADR-032 thin-glue exception).

### Changes introduced by this spec

- **New file** `src/schemas/app-manifest-v2.schema.json` — canonical v2 JSON Schema
- **Unchanged** `src/schemas/app-manifest.schema.json` — v1 schema kept as-is; its `$id` URL remains stable so deployed v1 manifests still resolve
- **Thin-glue edit** `src/composables/useAppManifest.js` — detect `$schema` field and dispatch to v1 or v2 validator (≤20 LOC)
- **Updated export** `validateManifest(manifest)` — selects schema by `$schema` field; used by the hydra gate
- **Docs stub** `docs/architecture/manifest.md` — updated to mention v2 alongside v1 (full migration guide lands in spec 3)
- **Tests** — v1 regression suite + v2 golden cases (positive validation)

## Capabilities

### New Capabilities

- `manifest-v2-schema`: v2 JSON Schema with unified widgets, per-slot grid constraints, typed action discriminator, and `$schema`-based validator dispatch

### Modified Capabilities

(none — v1 schema is untouched; validator wiring is new, not a change to existing v1 behavior)

## Impact

- **`src/schemas/`** — one new file (`app-manifest-v2.schema.json`); `app-manifest.schema.json` unchanged
- **`src/composables/useAppManifest.js`** — ≤20 LOC added for `$schema` dispatch; all existing behavior preserved
- **`src/index.js` / barrel** — `validateManifest` export updated or added
- **`docs/architecture/manifest.md`** — docs stub only; no API changes
- **`tests/`** — new test files; no changes to existing tests
- **Consumers** (OpenRegister, OpenCatalogi, Procest, Pipelinq, MyDash) — zero impact; v1 manifests continue to validate unchanged. v2 opt-in via `$schema` field.
- **Hydra gate** — the updated `validateManifest()` export is the only consumer today; gate picks up the change automatically once this lands on `beta`
