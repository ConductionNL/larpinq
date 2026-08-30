# Architecture

Larpinq is a schema-driven Nextcloud app. It stores no data in its
own database tables: every domain object (character, player, ability,
skill, item, condition, effect, event, setting) lives in
[OpenRegister](https://github.com/ConductionNL/openregister) (ADR-001),
and the UI is built exclusively from `@conduction/nextcloud-vue`
components (ADR-012). This document describes the three cross-cutting
adoption patterns that follow from those decisions.

## 1. Manifest as the declarative source of truth

Larpinq's navigation and routes are NOT hand-wired. They are derived
from a single declarative manifest:

- `src/manifest.json` — the bundled base manifest (manifest-v2). It
  declares the `menu`, and a `pages` array with one `index` and one
  `detail` page per entity type, plus a `dashboard` page and a
  `roadmap` page. `dependencies: ["openregister"]` makes the ADR-001
  dependency on OpenRegister explicit.
- `src/manifest.d/*.json` — additive fragments (ADR-037). Concurrent
  builds drop their own fragment file here instead of editing the
  shared `manifest.json`, so disjoint changes never conflict.
  `mergeManifestFragments()` in `src/main.js` deep-merges every
  fragment's `pages[]` and `menu[]` onto the base at build time.
- `src/main.js` builds the vue-router routes directly from the merged
  manifest (`routesFromManifest()`) and renders each page through
  `CnPageRenderer`. Adding a new entity is a manifest edit, not a code
  change across router + nav + view files.
- `src/registry.js` is the ADR-036 kind-tagged component registry
  (replacing the deprecated `customComponents` prop). Slot overrides,
  action components (e.g. the dashboard/PDF actions), and section
  components are resolved from it by `CnPageRenderer`.

Validation: `npm run check:manifest` (`node tests/validate-manifest.js`)
runs as part of the `check:specs` composite gate.

## 2. Register / schema resolution (`RegisterResolverService`)

`lib/Service/RegisterObjectFetcher.php` is the single seam between
Larpinq and OpenRegister's `ObjectService`. For each object type it
must resolve a `(register, schema)` pair from the
`{type}_register` / `{type}_schema` app-config keys.

Resolution is delegated to OpenRegister's `RegisterResolverService`
when it is available, consolidating the per-call
`IAppConfig::getValueString` pattern (ADR-022 — consume OR
abstractions, never reinvent them). The real resolver API is
`resolveRegisterId($appId, $configKey, $default, $orgUuid)` and
`resolveSchemaId(...)`.

Because that service ships with newer OpenRegister releases, the
consumption is **backward-compatible**:

- `getRegisterResolver()` resolves the service lazily from the DI
  container, guarded by `class_exists(...)`. It returns `null` when the
  class is absent (older OR, or during an upgrade window) and never
  fails open on a missing dependency.
- `resolveRegisterAndSchema()` uses the resolver when present and falls
  back to the legacy `IAppConfig::getValueString` path otherwise,
  preserving identical "not configured" error semantics. The fallback
  logs a one-shot deprecation note via `LoggerInterface`.

This keeps a single deployable artifact working against both old and
new OpenRegister, and removes the duplicated resolution logic from
`getMapper()`.

`SettingsService` is intentionally NOT migrated to the resolver: its
only OR-pair access is a bulk round-trip of the full settings key set
(including non-pair `*_source` and top-level `register` keys) on behalf
of the settings UI. The resolver resolves one pair at a time and throws
on missing config — the wrong shape for a settings dump. The runtime
consumer of those keys (`RegisterObjectFetcher`) is the correct place
for the resolver, and it is migrated.

## 3. i18n and multi-tenancy (planned)

Two further OR/nc-vue adoptions are specified but deferred until their
cross-app dependencies ship:

- **i18n (ADR-025).** Passing `?_lang={locale}` on reads, stamping
  `X-Translation-Target-Language` on non-default-language writes, and
  rendering a `(translated from {lang})` badge from `sourceLanguage`
  metadata. These depend on OpenRegister's object API honouring those
  signals (unmerged `i18n-api-language-negotiation` /
  `i18n-source-of-truth`). Larpinq fetches objects through the shared
  `createObjectStore` from `@conduction/nextcloud-vue`
  (`src/store/modules/object.js`), so the correct home for these
  signals is the shared store, not a per-app HTTP client.
- **Multi-tenancy.** Adopting nc-vue's `useTenantContext()` to clear
  caches and refetch on tenant switch, and stamping
  `X-OpenRegister-Organisation` on writes. Gated on a versioned nc-vue
  release that exports the composable. Until then the app behaves as
  single-tenant; nothing imports the composable, so its absence cannot
  crash the app.

See `openspec/changes/larpinq-adopt-or-abstractions/` for the full
requirements, scenarios, and deferral rationale.
