## 1. useRuntimeManifest composable

- [x] 1.1 Locate the composables directory (`src/composables/`) — library is Vue 2.7 + JS (no tsconfig; existing composables use `.js`)
- [x] 1.2 Create `src/composables/useRuntimeManifest.js` implementing the no-merge runtime loader per spec: `GET /apps/{appId}/api/manifest` via `@nextcloud/axios` + `generateUrl`, v2 schema validation via `validateManifest`, 404/network-error fallback to `stubManifest`, returns `{ manifest, isLoading, validationErrors }`
- [x] 1.3 Export `useRuntimeManifest` from the composables barrel (`src/composables/index.js` or equivalent) and from the top-level `src/index.js`
- [x] 1.4 Write jest tests in `tests/composables/useRuntimeManifest.spec.js` covering: successful load, 404 fallback, network error fallback, validation failure warning, no-stub null fallback, custom fetcher override, no-merge verification

## 2. RegistryKindError and registry validation

- [x] 2.1 Create `src/errors/RegistryKindError.js` — named `Error` subclass exported from the library barrel
- [x] 2.2 Add `registry` prop to `CnAppRoot` (default `{}`) and provide it to descendants via `provide()` under key `cnRegistry`
- [x] 2.3 In `CnAppRoot.mounted()`, iterate `registry` entries: throw `RegistryKindError` for unknown `kind`; emit `console.warn` for missing required metadata per kind (see design.md Decision 2 for required-fields table)
- [x] 2.4 Export `RegistryKindError` from `src/index.js`
- [x] 2.5 Write jest tests in `tests/components/CnAppRoot/CnAppRoot.registry.spec.js` covering: unknown kind throws, missing metadata warns, valid registry passes silently, RegistryKindError is instanceof Error

## 3. customComponents deprecation warning

- [x] 3.1 In `CnAppRoot.mounted()`, after registry validation: when `customComponents` is non-empty AND `manifest.$schema` contains `app-manifest-v2`, emit a single `console.warn` (guard with an instance flag `_customComponentsWarnedOnce` to prevent repeat on re-render)
- [x] 3.2 Write jest test: warning fires once for v2 manifest + non-empty customComponents; no warning for v1 manifest + customComponents; no repeat warning on re-render

## 4. CnPageRenderer v2 manifest detection and pipeline routing

- [x] 4.1 Add `isV2Manifest` computed to `CnPageRenderer`: returns `true` when `effectiveManifest?.$schema` includes `'app-manifest-v2'`
- [x] 4.2 Add `widgetsBySlot` computed to `CnPageRenderer`: groups `currentPage.widgets ?? []` by `slot` value into a `Map<string, WidgetEntry[]>`; entries with unrecognised slot patterns emit `console.warn` and are excluded
- [x] 4.3 Update the `CnPageRenderer` template: when `isV2Manifest`, render the v2 path (slot dispatcher via `CnWidgetGrid`); otherwise render the existing v1 `<component :is="resolvedComponent">` path unchanged
- [x] 4.4 Write jest tests for `isV2Manifest` (v2 schema string → true, absent $schema → false) and `widgetsBySlot` grouping + unknown-slot warning

## 5. CnWidgetGrid component (per-slot grid renderer)

- [x] 5.1 Create `src/components/CnWidgetGrid/CnWidgetGrid.vue` — accepts props `widgets: Array`, `slot-name: String`, `registry: Object`; computes `gridColumns` from `slotName` per ADR-036 Decision 2 table; renders a CSS grid container with `grid-template-columns: repeat(N, 1fr)` using Nextcloud CSS variables for gaps
- [x] 5.2 Implement widget key resolution in `CnWidgetGrid`: look up `widget.widgetKey` first in the built-in registry (see task 10), then in `registry` prop; emit `console.warn` and skip when not found
- [x] 5.3 Implement `gridWidth` clamping in `CnWidgetGrid`: when `widget.gridWidth > gridColumns`, clamp to `gridColumns` and emit `console.warn` with page id, widget key, and clamped value
- [x] 5.4 Render each resolved widget as `<component :is="widgetComponent" v-bind="widget.props ?? {}" :style="gridCellStyle(widget)" />`
- [x] 5.5 Write jest tests: slot → gridColumns mapping for all 7 slot patterns, gridWidth clamping + warning, unknown widgetKey warning + skip, custom registry override of built-in

## 6. Built-in widget — object-table

- [x] 6.1 Create `src/components/CnWidgetObjectTable/CnWidgetObjectTable.vue` — wraps `CnDataTable`; accepts and forwards props `register`, `schema`, `columns` (and any other `CnDataTable` props)
- [x] 6.2 Register `"object-table"` in the built-in widget registry (create `src/components/CnWidgetGrid/builtInWidgets.js` if not yet created by task 5.2)
- [x] 6.3 Export `CnWidgetObjectTable` from the components barrel
- [x] 6.4 Write jest test: `object-table` widgetKey resolves to `CnWidgetObjectTable`; props are forwarded to `CnDataTable`

## 7. Built-in widget — form-renderer

- [x] 7.1 Inspect `CnFormPage.vue` to identify the form rendering sub-component; extract to `CnFormContent.vue` if no dedicated sub-component exists
- [x] 7.2 Create `src/components/CnWidgetFormRenderer/CnWidgetFormRenderer.vue` — wraps `CnFormContent` (or equivalent); forwards `register`, `schema` props
- [x] 7.3 Register `"form-renderer"` in the built-in widget registry
- [x] 7.4 Export `CnWidgetFormRenderer` from the components barrel
- [x] 7.5 Write jest test: `form-renderer` widgetKey resolves; props forwarded

## 8. Built-in widget — wiki-renderer

- [x] 8.1 Inspect `CnWikiPage.vue` to identify the wiki content sub-component; extract to `CnWikiContent.vue` if needed
- [x] 8.2 Create `src/components/CnWidgetWikiRenderer/CnWidgetWikiRenderer.vue` — wraps the wiki content component; forwards `pageId` (and other relevant props)
- [x] 8.3 Register `"wiki-renderer"` in the built-in widget registry
- [x] 8.4 Export `CnWidgetWikiRenderer` from the components barrel
- [x] 8.5 Write jest test: `wiki-renderer` widgetKey resolves; props forwarded

## 9. Built-in widget — map-viewer

- [x] 9.1 Inspect `CnMapPage.vue` to identify the map sub-component; extract to `CnMapContent.vue` if needed
- [x] 9.2 Create `src/components/CnWidgetMapViewer/CnWidgetMapViewer.vue` — wraps the map content component; forwards all map-relevant props
- [x] 9.3 Register `"map-viewer"` in the built-in widget registry
- [x] 9.4 Export `CnWidgetMapViewer` from the components barrel
- [x] 9.5 Write jest test: `map-viewer` widgetKey resolves; props forwarded

## 10. Built-in widget — card-grid

- [x] 10.1 Create `src/components/CnWidgetCardGrid/CnWidgetCardGrid.vue` — renders a grid of `CnObjectCard` items; accepts `objects: Array` prop and renders each item as `<CnObjectCard :object="item" />`
- [x] 10.2 Register `"card-grid"` in the built-in widget registry
- [x] 10.3 Export `CnWidgetCardGrid` from the components barrel
- [x] 10.4 Write jest test: `card-grid` widgetKey resolves; renders one `CnObjectCard` per object

## 11. Unified actions dispatcher

- [x] 11.1 Create `src/utils/actionsDispatcher.js` exporting `dispatchAction(action, context)` where `context = { router, registry, handlers, openModal }`; implement all four `type` values: `handler` (missing-type backward compat), `open-modal`, `open-page`, `navigate` per spec requirements
- [x] 11.2 Export `dispatchAction` from `src/index.js`
- [x] 11.3 Write jest tests covering: `handler` type calls handler fn, missing handler warns, absent type treated as handler, `open-modal` calls openModal, open-modal unknown target warns, `open-page` calls router.push with name, `navigate` calls router.push with target, absent router warns for open-page/navigate

## 12. Wire actions dispatcher into CnPageRenderer v2 path

- [x] 12.1 In the v2 render path of `CnPageRenderer`, inject `cnRegistry` (falling back to empty object) and provide `dispatchAction` binding to child widget components via a new provide key `cnDispatchAction`
- [x] 12.2 Ensure `context.handlers` comes from the `manifest.actions` handler map or from a new `handlers` prop on `CnAppRoot` (check current handler passing convention in v1; align)
- [x] 12.3 Implement the `openModal` injection in `CnAppRoot`: provide `cnOpenModal(key, props)` function that resolves the key from `cnRegistry`, validates `kind === "modal"`, and opens the modal (use the existing two-phase dialog pattern or a ref-based approach consistent with the library)
- [x] 12.4 Write integration test: mounting a `CnPageRenderer` in v2 mode, dispatching an `open-modal` action reaches `cnOpenModal`

## 13. Barrel exports and build verification

- [x] 13.1 Confirm all new exports are present in `src/index.js`: `useRuntimeManifest`, `RegistryKindError`, `CnWidgetObjectTable`, `CnWidgetFormRenderer`, `CnWidgetWikiRenderer`, `CnWidgetMapViewer`, `CnWidgetCardGrid`, `dispatchAction`
- [x] 13.2 Run `npm run lint` — fix any reported issues before proceeding
- [x] 13.3 Run `npm run test` — all new and existing tests must pass
- [x] 13.4 Run `npm run build` — confirm the library builds without errors; check that new exports appear in the generated bundle entry

## 14. Spec validation

- [x] 14.1 Run `openspec validate manifest-v2-renderer --strict` and confirm zero errors
