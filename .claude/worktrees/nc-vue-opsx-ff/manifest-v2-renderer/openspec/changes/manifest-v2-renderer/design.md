# Design: manifest-v2-renderer

## Context

`@conduction/nextcloud-vue` ships `CnAppRoot` + `CnPageRenderer` as the primary manifest-driven rendering layer. V1 (spec `json-manifest-renderer`, ADR-024) routes on `pages[].type` to a per-type Vue component. The v1 page-type map is mature and deployed to five production apps. ADR-036 (2026-05-19) defines v2, which introduces a unified `widgets[]` array on every page, a five-kind component registry, per-slot grid layout, and a unified `actions[]` dispatcher.

This spec adds the v2 render pipeline alongside the existing v1 pipeline. The two pipelines coexist until nc-vue 3.0; the decision of which to run is made at `CnPageRenderer` render time by inspecting the `$schema` field set by `useAppManifest` when the schema spec (`manifest-v2-schema`) dispatches to the v2 validator.

Current GridStack usage: `CnDashboardGrid` wraps `gridstack` for dashboard drag-and-drop. The mock at `tests/__mocks__/gridstack.js` confirms GridStack is already the grid primitive in the library.

## Goals / Non-Goals

**Goals:**

- Add `useRuntimeManifest` — a full-replacement runtime loader (no merge) for mydash-style apps
- Add `registry` prop to `CnAppRoot` with five-kind validation at init
- `RegistryKindError` on unknown `kind`; `console.warn` deprecation of `customComponents` when v2 manifest loaded
- `CnPageRenderer` detects v2 manifest and dispatches to v2 slot+grid pipeline
- Per-slot grid renderer enforcing ADR-036 Decision 2 column conventions
- Five built-in widget components (`object-table`, `form-renderer`, `wiki-renderer`, `map-viewer`, `card-grid`)
- Unified `actions[]` dispatcher (`handler | open-modal | open-page | navigate`)
- Jest tests for all new code
- Zero regression to v1 rendering

**Non-Goals:**

- v2 JSON Schema definition (spec 1: `manifest-v2-schema`)
- `useAppManifest` schema-version dispatch thin-glue (spec 1)
- Codemod CLI (spec 3: `manifest-v2-codemod`)
- Form-field auto-binding beyond registry lookup and basic render (deferred; the `form-field` and `cell-renderer` registry kinds are validated at init and rendered via their registered component, but automatic property-to-field binding logic is a follow-up)
- Drag-and-drop reordering of widgets (dashboard GridStack behavior; stays in `CnDashboardGrid`)
- Any changes to v1 page components (`CnIndexPage`, `CnDetailPage`, etc.)

## Decisions

### Decision 1: V2 pipeline detection via `$schema` field

**Choice:** `CnPageRenderer` reads `manifest.$schema` to determine which pipeline to run. When `$schema` contains `app-manifest-v2` (the v2 schema `$id` set by spec 1), the renderer takes the v2 path; otherwise it falls through to the existing v1 path unchanged.

**Rationale:** The `$schema` field is the canonical version signal agreed in spec 1 and ADR-036. Repeating any other heuristic (e.g. presence of `widgets[]`) risks false positives on edge-case v1 manifests. The detection is a single string-contains check; no breaking change to v1.

**Alternative considered:** Add a `manifestVersion` integer field. Rejected — `$schema` is the JSON Schema standard mechanism and is already set by the v2 validator.

---

### Decision 2: Registry lives on `CnAppRoot`, validated at init

**Choice:** `CnAppRoot` accepts a new `registry` prop typed as `Record<string, ComponentRegistration>` where `ComponentRegistration = { kind, component, ...kindMetadata }`. At `mounted()`, each entry is validated against its kind's required-field schema. Unknown `kind` throws `RegistryKindError` (extends `Error`). Missing required kind-metadata emits `console.warn` (non-fatal — the widget renders with defaults).

**Registry kind required fields:**

| kind | Required metadata |
|---|---|
| `widget` | `defaultSize` (`{ w, h }`), `minSize` (`{ w, h }`), `maxSize` (`{ w, h }`), `allowedSlots` (string[]), `propsSchema` (JSON Schema object or `null`) |
| `modal` | `propsSchema` |
| `page` | *(none beyond `component`)* |
| `form-field` | `appliesTo` (`{ format?: string, property?: string }`) |
| `cell-renderer` | `appliesTo` (`{ schema: string, property: string }`) |

**Rationale:** Fail-fast at init surfaces misconfiguration early (developer console error on page load, not a broken widget at runtime). Throwing only on unknown `kind` (hard error) but warning on missing metadata (soft) keeps partial-registry cases recoverable.

**Alternative considered:** Validate lazily at first render. Rejected — developer experience is worse; manifests may never exercise every widget type in a given session.

---

### Decision 3: `customComponents` deprecation is warn-only, v1-compatible

**Choice:** When `CnAppRoot` detects both `customComponents` (non-empty) and a v2 manifest loaded, it emits a single `console.warn` per mount (not per render): "CnAppRoot: `customComponents` prop is deprecated when using v2 manifests. Use the `registry` prop instead." The `customComponents` value is still passed through to v1 code paths; it is ignored by the v2 path.

**Rationale:** Many existing apps pass `customComponents`. Crashing during a v2 migration is unacceptable. The codemod (spec 3) migrates `customComponents` entries to `registry`; until then, warn-only keeps apps running.

---

### Decision 4: Slot dispatcher as a single computed method

**Choice:** In `CnPageRenderer`, a `widgetsBySlot` computed returns a `Map<string, WidgetEntry[]>` grouping the current page's `widgets[]` by `slot` value. The v2 render template iterates the known slots (body, sidebar, header-actions, footer, plus any `tab:*` and `section:*` keys found in the map) and passes each slot's widget array to a `CnWidgetGrid` child.

**Rationale:** A single grouping step is simpler than per-slot renderer components. The template remains readable; `CnWidgetGrid` handles the CSS grid. Dynamic slot discovery via the map supports arbitrary `tab:<id>` and `section:<id>` keys without hardcoding.

**Alternative considered:** Individual computed properties per known slot. Rejected — `tab:*` and `section:*` are open-ended; a static list cannot cover them.

---

### Decision 5: Grid renderer uses CSS Grid, not GridStack

**Choice:** `CnWidgetGrid` is a new component that renders a CSS grid (`display: grid; grid-template-columns: repeat(var(--cn-grid-columns), 1fr)`) with each widget placed using `grid-column: span N` / `grid-row: span N` derived from `gridWidth`/`gridHeight`. `--cn-grid-columns` defaults to the per-slot convention (12 or 1).

GridStack is **not** used for v2 widget grids. GridStack stays confined to `CnDashboardGrid` for the interactive drag-and-drop dashboard use case.

**Rationale:** V2 widget grids are static (positions come from the manifest; runtime reordering is not in scope for this spec). CSS Grid is lighter, needs no runtime dependency, and avoids the GridStack test-environment mock complexity that already plagues `CnDashboardGrid`. Drag-and-drop is a future concern.

**Alternative considered:** Extend GridStack to all v2 slots. Rejected — GridStack's DOM requirements (specific class names, `grid-stack-item` wrappers) impose constraints that conflict with sidebar and header-actions slot semantics. Per-slot `gridColumns: 1` on the sidebar especially doesn't map cleanly.

---

### Decision 6: Built-in widgets wrap existing page components

**Choice:** Each built-in widget is a thin wrapper that delegates to the corresponding existing page component or composable:

| Built-in widget | Wraps |
|---|---|
| `object-table` | `CnDataTable` (or `CnIndexPage`'s internal table sub-component) |
| `form-renderer` | `CnFormPage`'s form sub-component |
| `wiki-renderer` | `CnWikiPage`'s content sub-component |
| `map-viewer` | `CnMapPage`'s map sub-component (Leaflet) |
| `card-grid` | v1.3.0 `cardComponent` pattern — renders a grid of `CnObjectCard` items |

Props for each widget are a strict subset of the corresponding page component's props, forwarded via `v-bind="$props"`.

**Rationale:** Avoids duplicating complex rendering logic. The built-in widget surface is intentionally minimal at launch; per-widget prop sets can grow in subsequent specs.

**Alternative considered:** Render full page components as widgets. Rejected — full page components pull in sidebars, navigation, and other shell concerns that don't belong inside a widget slot.

---

### Decision 7: `actions[]` dispatcher is a standalone utility

**Choice:** `src/utils/actionsDispatcher.js` exports `dispatchAction(action, context)` where `context = { router, registry, handlers }`. Dispatch by `type`:

- `handler` — calls `context.handlers[action.handler](...action.args ?? [])`; emits `console.warn` if handler not found
- `open-modal` — looks up `action.target` in registry (must be `kind: "modal"`); opens via a provided `openModal(key, props)` injected function
- `open-page` — calls `context.router.push({ name: action.target })`
- `navigate` — calls `context.router.push(action.target)` (accepts string URL or route location object)

Missing `type` is treated as `handler` for v1 backward compatibility.

**Rationale:** A pure utility function is testable in isolation without mounting Vue components. The dispatcher does not hold state; each call is side-effect only. Keeping it separate from `CnPageRenderer` allows widget components to also call it.

---

### Decision 8: `useRuntimeManifest` — no merge semantics

**Choice:** `useRuntimeManifest(appId, stubManifest?, options?)` fetches `GET /apps/${appId}/api/manifest` (via `@nextcloud/router`'s `generateUrl`). On 200, the response replaces the stub entirely (no deep-merge). On 404 or network error, falls back to `stubManifest` if provided (otherwise `manifest.value` stays `null`). Validates the fetched manifest against the v2 schema. Returns `{ manifest, isLoading, validationErrors }`.

**Rationale:** ADR-036 Decision 8 explicitly states "does not merge". Runtime manifests are the canonical source of truth (mydash, OpenBuilt); merging would silently override user-authored layouts with stale bundled defaults — a correctness hazard. The no-merge contract simplifies reasoning about manifest state.

**Alternative considered:** Reuse `useAppManifest` with `merge: false` option. Rejected — `useAppManifest` is a v1 convention with merge semantics as an invariant; adding a no-merge option risks caller confusion. A distinct composable has a clearer contract.

## Risks / Trade-offs

- **GridStack on sidebar slot** — sidebar `gridColumns: 1` means all widgets stack vertically. Not a risk since we use CSS Grid (not GridStack), but any widget that sets `gridWidth > 1` in the sidebar will be clamped to 1 column. The validator (spec 1) should enforce this at schema level. → Mitigation: `CnWidgetGrid` logs a `console.warn` when `gridWidth > gridColumns` and clamps silently.

- **Performance with many widgets** — a page with 30+ widgets all in the body slot means 30+ `<component :is>` mounts. V2 widget grids have no virtualisation. → Acceptable for the current page complexity seen in the fleet (dashboards: 3-8 widgets; index pages: 1-3 supplemental widgets). Virtualisation is deferred.

- **Deprecation warning noise** — apps that pass `customComponents` and load a v2 manifest will see a console warning on every `CnAppRoot` mount. → Mitigation: warn once per mount via a `_customComponentsWarnedOnce` instance flag.

- **Backward-compat regression** — the v2 detection guard (`$schema` contains `app-manifest-v2`) must never fire on v1 manifests. → Mitigation: v1 manifests have no `$schema` field; the check is only positive when spec 1's validator explicitly sets the field.

- **`form-field` and `cell-renderer` partial implementation** — these registry kinds are validated at init and rendered via their registered component, but auto-binding logic (matching form fields to JSON Schema properties) is out of scope. → Mitigation: document the minimal contract in the spec; flag the gap in `RegistryKindError` messages. Full auto-binding is a follow-up spec.

## Migration Plan

1. This spec ships alongside spec 1 (`manifest-v2-schema`) on the `feat/openspec-final-close` branch targeting `beta`.
2. Existing v1 apps are unaffected — `$schema` absent → v1 path → no change.
3. Apps adopting v2 pass the `registry` prop and set `$schema` in their manifest.
4. The codemod (spec 3) handles mechanical migration of `customComponents` → `registry`.
5. Rollback: if a regression surfaces in the v2 path, the `$schema` check is the single gate; removing or disabling it restores pure v1 rendering. The v1 pipeline code is not modified.

## Open Questions

1. **`CnFormPage` internal sub-component** — the `form-renderer` built-in widget needs to reuse the form rendering logic from `CnFormPage` without importing the full page shell. If `CnFormPage` doesn't have an extractable sub-component, the widget may need to duplicate the form render pass. Resolution: inspect `CnFormPage` at implementation time; extract to `CnFormContent.vue` if it doesn't exist yet. (Low risk — same pattern as `CnDataTable` reuse in `object-table`.)

2. **`openModal` injection point** — the `open-modal` action type needs a way to open a modal registered in `registry`. The current `CnAppRoot` uses the two-phase dialog pattern. The implementation should provide an `openModal(key, props)` function via Vue provide/inject, resolved by `CnAppRoot` against the `registry`. (Provisional decision: add to `CnAppRoot`'s `provide()` alongside `cnOpenUserSettings`.)
