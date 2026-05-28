---
kind: code
depends_on:
  - manifest-v2-schema
---

# Proposal: manifest-v2-renderer

## Why

This is spec **2 of 3** in the `manifest-v2-library` chain (hydra ADR-036, merged 2026-05-19). Spec 1 (`manifest-v2-schema`) established the v2 JSON Schema, the dual validator, and the `$schema`-dispatch in `useAppManifest`. Now the renderer must actually process v2 manifests: unified `widgets[]` on all 11 page types, the five-kind component registry, per-slot grid layout, and the unified `actions[]` dispatcher. Without this spec, v2 manifests parse correctly but render nothing new — they fall back to the v1 page-component pipeline and ignore the `widgets[]` / `registry` / unified-actions fields.

## What Changes

- **New composable** `src/composables/useRuntimeManifest.js` — loads a manifest from `GET /api/manifest/{appId}` at runtime, validates against the v2 schema, falls back to a bundled stub on 404/error; no merge semantics (replaces the bundled manifest entirely).
- **New `registry` prop on `CnAppRoot`** — typed `Record<string, ComponentRegistration>` with `kind` discriminator (`widget | modal | page | form-field | cell-renderer`); replaces `customComponents` for v2 manifests.
- **`customComponents` prop deprecated** when a v2 manifest is loaded — emits `console.warn` on first render; prop continues to work for v1 manifests.
- **`RegistryKindError`** thrown at `CnAppRoot` initialisation when an unknown `kind` is encountered in the registry.
- **Per-kind metadata validation** at init — required fields per kind (`defaultSize`/`minSize`/`maxSize`/`allowedSlots`/`propsSchema` for `widget`; `propsSchema` for `modal`; `appliesTo` for `form-field`/`cell-renderer`).
- **`CnPageRenderer` v2 pipeline** — detects v2 manifest (via `$schema` field set by `useAppManifest`) and routes to a v2 render path; v1 path is completely unchanged.
- **Slot dispatcher** — routes each widget entry by its `slot` field into the appropriate page-component zone: `body`, `sidebar`, `header-actions`, `footer`, `modal`, `tab:<id>`, `section:<id>`.
- **Per-slot grid renderer** — wraps widgets in a CSS grid container enforcing per-slot `gridColumns` conventions (12 for `body/footer/header-actions/modal/tab/section`; 1 for `sidebar`).
- **Five built-in widget components**: `object-table` (index pages), `form-renderer` (form pages), `wiki-renderer` (wiki pages), `map-viewer` (map pages), `card-grid` (replaces v1.3.0 `cardComponent`).
- **Unified `actions[]` dispatcher** — dispatches by `type`: `handler` (v1 behavior), `open-modal` (registry lookup), `open-page` (manifest page nav), `navigate` (arbitrary URL/route).
- **Jest tests** for all new components, composable, and dispatcher.

## Capabilities

### New Capabilities

- `manifest-v2-renderer`: Runtime loading composable, five-kind registry validation, v2 render pipeline in `CnPageRenderer`, slot dispatcher, per-slot grid, five built-in widgets, unified actions dispatcher.

### Modified Capabilities

(none — v1 manifest rendering is completely unchanged; v2 pipeline activates only when `$schema` identifies a v2 manifest)

## Impact

- **`src/composables/useRuntimeManifest.js`** — new file; exported from barrel
- **`src/components/CnAppRoot/CnAppRoot.vue`** — new `registry` prop; `customComponents` deprecation warning; `RegistryKindError` on unknown kind; per-kind metadata validation at init
- **`src/components/CnPageRenderer/CnPageRenderer.vue`** — v2 manifest detection; v2 render path with slot dispatcher and grid renderer; v1 path unchanged
- **`src/components/CnWidgetGrid/`** — new per-slot grid renderer component
- **`src/components/CnWidgetObjectTable/`** — new built-in widget
- **`src/components/CnWidgetFormRenderer/`** — new built-in widget
- **`src/components/CnWidgetWikiRenderer/`** — new built-in widget
- **`src/components/CnWidgetMapViewer/`** — new built-in widget
- **`src/components/CnWidgetCardGrid/`** — new built-in widget
- **`src/utils/actionsDispatcher.js`** — new unified actions dispatch utility
- **`src/index.js` barrel** — exports for `useRuntimeManifest`, new widget components, `RegistryKindError`
- **Consumers** (OpenRegister, OpenCatalogi, Procest, Pipelinq, MyDash) — zero impact on existing v1 apps; opt-in by adopting `registry` prop + v2 manifests
- **mydash** — primary beneficiary of `useRuntimeManifest` (runtime per-user dashboard manifests)
- **OpenBuilt** — primary beneficiary of the five-kind registry (OpenBuilt manages registry entries through its UI)
