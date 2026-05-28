## ADDED Requirements

### Requirement: useRuntimeManifest composable
The composable `useRuntimeManifest(appId, stubManifest?, options?)` SHALL load a v2 manifest from `GET /apps/{appId}/api/manifest` at runtime via `@nextcloud/axios`. The fetched manifest SHALL replace the stub entirely with no deep-merge semantics. On a successful 200 response the composable SHALL validate the response data against the v2 JSON Schema (via `validateManifest`) before setting `manifest.value`. On a 404 response or network error the composable SHALL fall back to `stubManifest` when provided; when no stub is provided `manifest.value` SHALL remain `null`. The composable SHALL return `{ manifest, isLoading, validationErrors }` where `manifest` and `isLoading` are Vue `ref`s and `validationErrors` is a `ref<string[]|null>`.

#### Scenario: Successful runtime manifest load
- **WHEN** `useRuntimeManifest('YOUR_APP_ID')` is called and `GET /apps/YOUR_APP_ID/api/manifest` returns HTTP 200 with a valid v2 manifest object
- **THEN** `manifest.value` SHALL be set to the response data
- **THEN** `isLoading.value` SHALL transition from `true` to `false`
- **THEN** `validationErrors.value` SHALL be `null`

#### Scenario: Runtime manifest load with stub fallback on 404
- **WHEN** `useRuntimeManifest('YOUR_APP_ID', stubManifest)` is called and the API returns HTTP 404
- **THEN** `manifest.value` SHALL be set to `stubManifest`
- **THEN** `isLoading.value` SHALL be `false`
- **THEN** `validationErrors.value` SHALL be `null`

#### Scenario: Runtime manifest validation failure
- **WHEN** the API returns HTTP 200 but the response does not pass v2 schema validation
- **THEN** `manifest.value` SHALL be set to `stubManifest` (or `null` when no stub is provided)
- **THEN** `validationErrors.value` SHALL be a non-empty `string[]` listing the schema errors
- **THEN** a `console.warn` SHALL be emitted identifying the composable and listing the errors

#### Scenario: Runtime manifest load — no merge
- **WHEN** both `stubManifest` and a successful API response are present
- **THEN** `manifest.value` SHALL equal the API response data verbatim, not a deep-merge of API data over the stub

#### Scenario: Custom fetcher override
- **WHEN** `useRuntimeManifest('YOUR_APP_ID', stub, { fetcher: myFetcher })` is called
- **THEN** `myFetcher` SHALL be called with the generated URL in place of `@nextcloud/axios`

---

### Requirement: CnAppRoot registry prop
`CnAppRoot` SHALL accept a new `registry` prop of type `Object` (runtime shape: `Record<string, ComponentRegistration>`). The prop SHALL default to an empty object `{}`. Each value in the registry SHALL conform to the shape `{ kind: string, component: object, ...kindMetadata }`. The `registry` value SHALL be provided to descendants via Vue `provide` under the key `cnRegistry`, alongside the existing `cnCustomComponents` provide.

#### Scenario: Registry provided to descendants
- **WHEN** `CnAppRoot` is mounted with a non-empty `registry` prop
- **THEN** descendants injecting `cnRegistry` SHALL receive the registry object
- **THEN** the existing `cnCustomComponents` inject SHALL continue to resolve from the `customComponents` prop

#### Scenario: Empty registry default
- **WHEN** `CnAppRoot` is mounted without a `registry` prop
- **THEN** descendants injecting `cnRegistry` SHALL receive an empty object `{}`

---

### Requirement: Registry kind validation at init
`CnAppRoot` SHALL validate every entry in the `registry` prop at `mounted()` time. An entry with an unrecognised `kind` SHALL throw a `RegistryKindError` (a named Error subclass exported from the library). An entry with a recognised `kind` but missing required metadata fields SHALL emit a `console.warn` (non-fatal). Recognised kinds are: `widget`, `modal`, `page`, `form-field`, `cell-renderer`.

Required metadata per kind:

| kind | Required fields |
|---|---|
| `widget` | `defaultSize` (`{ w, h }`), `minSize` (`{ w, h }`), `maxSize` (`{ w, h }`), `allowedSlots` (string[]), `propsSchema` (object or null) |
| `modal` | `propsSchema` (object or null) |
| `page` | *(none beyond `component`)* |
| `form-field` | `appliesTo` (object with at least one of `format` or `property`) |
| `cell-renderer` | `appliesTo` (`{ schema: string, property: string }`) |

#### Scenario: Unknown kind throws RegistryKindError
- **WHEN** `CnAppRoot` is mounted with `registry = { myWidget: { kind: 'unknown-kind', component: MyComp } }`
- **THEN** a `RegistryKindError` SHALL be thrown at mount time
- **THEN** the error message SHALL identify the offending registry key and the unrecognised `kind` value

#### Scenario: Known kind with missing metadata emits warning
- **WHEN** `CnAppRoot` is mounted with a `widget` entry that omits `allowedSlots`
- **THEN** no exception SHALL be thrown
- **THEN** a `console.warn` SHALL be emitted identifying the registry key and the missing field

#### Scenario: Valid registry passes silently
- **WHEN** all registry entries have recognised kinds and all required metadata fields
- **THEN** no errors and no warnings SHALL be emitted

#### Scenario: RegistryKindError is exported
- **WHEN** `import { RegistryKindError } from '@conduction/nextcloud-vue'` is evaluated
- **THEN** `RegistryKindError` SHALL be a constructor whose instances pass `instanceof RegistryKindError` and `instanceof Error`

---

### Requirement: customComponents prop deprecation for v2 manifests
When `CnAppRoot` is mounted with a non-empty `customComponents` prop AND the loaded manifest is a v2 manifest (detected by `$schema` containing `app-manifest-v2`), `CnAppRoot` SHALL emit a single `console.warn` per mount lifecycle: "CnAppRoot: `customComponents` prop is deprecated when using v2 manifests. Use the `registry` prop instead (see ADR-036)." The prop SHALL continue to function for v1 manifests without any warning.

#### Scenario: Deprecation warning on v2 manifest + customComponents
- **WHEN** `CnAppRoot` receives a non-empty `customComponents` prop and the `manifest` prop has `$schema` identifying a v2 schema
- **THEN** exactly one `console.warn` SHALL be emitted at mount time
- **THEN** the warning SHALL not repeat on subsequent re-renders

#### Scenario: No warning on v1 manifest with customComponents
- **WHEN** `CnAppRoot` receives a non-empty `customComponents` prop and the manifest has no `$schema` field
- **THEN** no deprecation warning SHALL be emitted

---

### Requirement: CnPageRenderer v2 manifest detection
`CnPageRenderer` SHALL detect whether the effective manifest is a v2 manifest by inspecting `manifest.$schema`. When `$schema` is a string containing `app-manifest-v2`, the renderer SHALL enter the v2 render pipeline. When `$schema` is absent or does not contain `app-manifest-v2`, the renderer SHALL enter the existing v1 render pipeline unchanged.

#### Scenario: V2 manifest activates v2 pipeline
- **WHEN** the effective manifest has `$schema` containing `app-manifest-v2`
- **THEN** `CnPageRenderer` SHALL render the current page's `widgets[]` array via the v2 slot dispatcher and grid renderer, not via the v1 `resolvedComponent` / `resolvedProps` path

#### Scenario: V1 manifest uses v1 pipeline unchanged
- **WHEN** the effective manifest has no `$schema` field
- **THEN** `CnPageRenderer` SHALL behave exactly as it does today (route on `pages[].type`; dispatch to page component; forward `config` as props)

---

### Requirement: CnPageRenderer v2 unified widgets rendering
For v2 manifests, `CnPageRenderer` SHALL render the current page's `widgets[]` array. Each widget entry SHALL be dispatched by its `slot` value to the appropriate zone. The renderer SHALL support the following slot values: `body`, `sidebar`, `header-actions`, `footer`, `modal`, any string matching `tab:<id>`, and any string matching `section:<id>`.

#### Scenario: Widgets grouped by slot
- **WHEN** a v2 page has `widgets` with entries having `slot: "body"`, `slot: "sidebar"`, and `slot: "tab:general"`
- **THEN** each slot group SHALL be rendered in its corresponding zone
- **THEN** `body` widgets SHALL appear in the main content area
- **THEN** `sidebar` widgets SHALL appear in the sidebar zone (gated by `cnPageSidebarVisible`)
- **THEN** `tab:general` widgets SHALL appear inside the tab surface with id `general`

#### Scenario: Unknown slot value is ignored with warning
- **WHEN** a widget entry has a `slot` value that is not one of the recognised slot patterns
- **THEN** that widget SHALL not be rendered
- **THEN** a `console.warn` SHALL be emitted identifying the page id, widget `widgetKey`, and unrecognised slot value

---

### Requirement: Per-slot grid constraint enforcement
`CnWidgetGrid` SHALL enforce the per-slot `gridColumns` convention defined in ADR-036 Decision 2 at render time. The number of grid columns SHALL be determined by the slot name as follows:

| Slot pattern | gridColumns |
|---|---|
| `body` | 12 |
| `sidebar` | 1 |
| `header-actions` | 12 |
| `footer` | 12 |
| `modal` | 12 |
| `tab:<id>` | 12 |
| `section:<id>` | 12 |

When a widget's `gridWidth` exceeds the slot's `gridColumns`, `CnWidgetGrid` SHALL clamp `gridWidth` to `gridColumns` and emit a `console.warn` identifying the page id, widget key, and clamped value.

#### Scenario: Sidebar widget gridWidth clamped
- **WHEN** a widget entry has `slot: "sidebar"` and `gridWidth: 4`
- **THEN** the widget SHALL be placed in a 1-column CSS grid cell (`grid-column: span 1`)
- **THEN** a `console.warn` SHALL be emitted noting the clamp

#### Scenario: Body widget grid placement
- **WHEN** a widget entry has `slot: "body"`, `gridX: 0`, `gridY: 0`, `gridWidth: 6`, `gridHeight: 2`
- **THEN** the widget cell SHALL have CSS `grid-column: 1 / span 6` and `grid-row: 1 / span 2`

---

### Requirement: Built-in widget — object-table
`CnWidgetObjectTable` SHALL render a data table for index pages. It SHALL accept the same props as `CnDataTable` (register, schema, columns) and delegate rendering to `CnDataTable`. The widget SHALL be referenced in manifests via `widgetKey: "object-table"` and SHALL be registered in the library's internal built-in registry.

#### Scenario: object-table renders CnDataTable
- **WHEN** a v2 page widget has `widgetKey: "object-table"` with `props: { register: "my-register", schema: "my-schema" }`
- **THEN** a `CnDataTable` SHALL be mounted with `register="my-register"` and `schema="my-schema"`

---

### Requirement: Built-in widget — form-renderer
`CnWidgetFormRenderer` SHALL render a form for form-type pages. It SHALL wrap the form rendering sub-component from `CnFormPage`. The widget SHALL be referenced in manifests via `widgetKey: "form-renderer"` and SHALL be registered in the library's internal built-in registry.

#### Scenario: form-renderer renders form content
- **WHEN** a v2 `form` page widget has `widgetKey: "form-renderer"` with `props: { register: "r1", schema: "s1" }`
- **THEN** a form surface SHALL be mounted targeting register `r1` and schema `s1`

---

### Requirement: Built-in widget — wiki-renderer
`CnWidgetWikiRenderer` SHALL render wiki page content. It SHALL wrap the wiki content sub-component from `CnWikiPage`. The widget SHALL be referenced in manifests via `widgetKey: "wiki-renderer"` and SHALL be registered in the library's internal built-in registry.

#### Scenario: wiki-renderer renders wiki content
- **WHEN** a v2 `wiki` page widget has `widgetKey: "wiki-renderer"` with `props: { pageId: "home" }`
- **THEN** wiki content for `pageId: "home"` SHALL be rendered

---

### Requirement: Built-in widget — map-viewer
`CnWidgetMapViewer` SHALL render a map view. It SHALL wrap the map sub-component from `CnMapPage`. The widget SHALL be referenced in manifests via `widgetKey: "map-viewer"` and SHALL be registered in the library's internal built-in registry.

#### Scenario: map-viewer renders map
- **WHEN** a v2 `map` page widget has `widgetKey: "map-viewer"`
- **THEN** a map surface SHALL be rendered with the props forwarded from the widget entry

---

### Requirement: Built-in widget — card-grid
`CnWidgetCardGrid` SHALL render a card grid for index pages, replacing the v1.3.0 `cardComponent` field. It SHALL render a grid of `CnObjectCard` items. The widget SHALL be referenced in manifests via `widgetKey: "card-grid"` and SHALL be registered in the library's internal built-in registry.

#### Scenario: card-grid renders object cards
- **WHEN** a v2 page widget has `widgetKey: "card-grid"` with a list of objects passed via props
- **THEN** each object SHALL render as a `CnObjectCard` within a grid layout

---

### Requirement: Widget key resolution — built-in vs custom
The renderer SHALL resolve a widget's `widgetKey` first against the built-in widget registry, then against the `cnRegistry` inject. When a `widgetKey` is not found in either registry, the widget SHALL not be rendered and a `console.warn` SHALL be emitted identifying the missing key and page id.

#### Scenario: Custom widget overrides built-in
- **WHEN** `cnRegistry` contains an entry with the same key as a built-in widget (e.g. `"object-table"`)
- **THEN** the custom registry entry SHALL take precedence over the built-in

#### Scenario: Unknown widgetKey emits warning
- **WHEN** a widget entry has `widgetKey: "does-not-exist"` not present in built-in or custom registry
- **THEN** that widget SHALL not be rendered
- **THEN** a `console.warn` SHALL be emitted

---

### Requirement: Unified actions dispatcher — handler type
`dispatchAction(action, context)` SHALL, when `action.type === "handler"` (or `action.type` is absent for v1 backward compatibility), call `context.handlers[action.handler]` passing `action.args ?? []` as a spread argument array. When `action.handler` is not found in `context.handlers`, a `console.warn` SHALL be emitted and no exception SHALL be thrown.

#### Scenario: handler type dispatches to registered function
- **WHEN** `dispatchAction({ type: 'handler', handler: 'openDialog', args: ['confirm'] }, context)` is called and `context.handlers.openDialog` is a function
- **THEN** `context.handlers.openDialog('confirm')` SHALL be called

#### Scenario: Missing handler emits warning
- **WHEN** `dispatchAction({ handler: 'missingFn' }, context)` is called and `context.handlers` does not have a `missingFn` key
- **THEN** a `console.warn` SHALL be emitted
- **THEN** no exception SHALL be thrown

---

### Requirement: Unified actions dispatcher — open-modal type
`dispatchAction` SHALL, when `action.type === "open-modal"`, look up `action.target` in `context.registry`. The registry entry MUST have `kind: "modal"`. The dispatcher SHALL call `context.openModal(action.target, action.props ?? {})`. When `action.target` is not found in the registry or does not have `kind: "modal"`, a `console.warn` SHALL be emitted.

#### Scenario: open-modal opens registered modal
- **WHEN** `dispatchAction({ type: 'open-modal', target: 'confirm-archive', props: { title: 'Archive?' } }, context)` is called and the registry has `confirm-archive` with `kind: "modal"`
- **THEN** `context.openModal('confirm-archive', { title: 'Archive?' })` SHALL be called

#### Scenario: open-modal with unknown target emits warning
- **WHEN** `action.target` does not exist in `context.registry`
- **THEN** a `console.warn` SHALL be emitted
- **THEN** `context.openModal` SHALL NOT be called

---

### Requirement: Unified actions dispatcher — open-page type
`dispatchAction` SHALL, when `action.type === "open-page"`, call `context.router.push({ name: action.target })`. When `context.router` is absent, a `console.warn` SHALL be emitted.

#### Scenario: open-page navigates to named route
- **WHEN** `dispatchAction({ type: 'open-page', target: 'meetings-index' }, context)` is called and `context.router` is a Vue Router instance
- **THEN** `context.router.push({ name: 'meetings-index' })` SHALL be called

---

### Requirement: Unified actions dispatcher — navigate type
`dispatchAction` SHALL, when `action.type === "navigate"`, call `context.router.push(action.target)` where `action.target` is a string URL or route location object. When `context.router` is absent, a `console.warn` SHALL be emitted.

#### Scenario: navigate dispatches arbitrary route
- **WHEN** `dispatchAction({ type: 'navigate', target: '/apps/decidesk/settings' }, context)` is called
- **THEN** `context.router.push('/apps/decidesk/settings')` SHALL be called

---

### Requirement: form-field and cell-renderer — minimal registry render
Registry entries with `kind: "form-field"` and `kind: "cell-renderer"` SHALL be validated at init (per the registry kind validation requirement above) and SHALL be renderable by the widget grid when referenced by `widgetKey`. Full automatic property-to-field binding (JSON Schema introspection driving which field renders for which property) is explicitly deferred and SHALL NOT be implemented in this spec. The registered component SHALL receive the widget's `props` from the manifest entry as-is.

#### Scenario: form-field renders with manifest props
- **WHEN** a widget entry references a registry key of `kind: "form-field"` via `widgetKey`
- **THEN** the registered component SHALL be mounted with the widget entry's `props` forwarded
- **THEN** no automatic property-binding logic SHALL be applied

#### Scenario: cell-renderer renders with manifest props
- **WHEN** a widget entry references a registry key of `kind: "cell-renderer"` via `widgetKey`
- **THEN** the registered component SHALL be mounted with the widget entry's `props` forwarded
