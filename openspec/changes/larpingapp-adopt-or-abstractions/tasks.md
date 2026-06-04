# Tasks — larpingapp-adopt-or-abstractions

> Spec-only change. No PR / merge / archive tasks here.

## Implementation note (build 2026-06-04)

The proposal/design/spec were authored against an earlier snapshot of
LarpingApp. During the build the app's actual state was reconciled:

- **Phase 1 is already satisfied** by an even stronger pattern than the
  spec asked for. LarpingApp already ships `src/manifest.json`
  (manifest-v2, `version 0.2.0`, `dependencies: ["openregister"]`), a
  full `menu` + `pages` array with `index` / `detail` pages for every
  entity, a `src/manifest.d/` fragment loader (ADR-037), an
  `npm run check:manifest` validator, and a router built FROM the
  manifest in `src/main.js`. There is no `src/router/index.js` or
  hand-wired nav to collapse. Phase 1 tasks are ticked as
  already-done with the divergence documented inline.
- **Phase 2's hard dependency is unmerged.** OpenRegister's
  `RegisterResolverService` exists only as the unmerged openspec change
  `openregister/openspec/changes/register-resolver-service/`; the class
  is not present in any deployed OR. The spec also invented a
  `resolveForObjectType()` method — the real (proposed) API is
  `resolveRegisterId($appId, $configKey, $default, $orgUuid)` /
  `resolveSchemaId(...)`. This build implements the BC-safe consumption
  the spec's own fallback scenario requires: resolve the service from
  the DI container when the class exists, use the REAL API, and fall
  back to the legacy `IAppConfig::getValueString` path (logging a
  one-shot deprecation note) when it is absent. Hard/mandatory
  injection is deferred until OR ships the class.
- **Phase 3 (i18n) and Phase 4 (multi-tenancy) both depend on
  not-yet-merged cross-app capabilities** and a frontend fetch seam
  that LarpingApp does not own (OR object fetches go through the shared
  `createObjectStore` from `@conduction/nextcloud-vue`, not a local
  client). They are deferred with reasons below.

## Phase 1 — Manifest pilot (Tier 2)

- [x] 1.1 `src/manifest.json` exists with `$schema` (app-manifest-v2),
  `version`, `dependencies: ["openregister"]`, a `menu` for all
  entities + settings, and `index` + `detail` `pages` for every entity
  type. (Already present — pre-dates this change. The app went further
  than the spec: a `dashboard` page and `roadmap` page also exist, and
  `actionsComponent` slot overrides are wired via `src/registry.js`
  per ADR-036 rather than a single `PdfExportAction` string.)
- [x] 1.2 `npm run check:manifest` script present in `package.json`
  (`node tests/validate-manifest.js`). (Already present.)
- [x] 1.3 The manifest is loaded at boot. LarpingApp uses the
  `CnPageRenderer` + manifest-prop + `mergeManifestFragments()`
  pattern in `src/main.js` (router built from the manifest) rather than
  a `useAppManifest()` call — a stronger, fragment-aware adoption.
  (Already present.)
- [x] 1.4 `check:manifest` is wired into the spec CI gate via the
  `check:specs` composite script. (Already present.)
- [ ] 1.5 [DEFERRED — needs live instance] Verify
  `useAppStatus('openregister')` returns `installed/enabled` under
  docker-compose. Requires a running Nextcloud + OR; not reproducible
  in the headless build sandbox.

## Phase 2 — `RegisterResolverService` consumption

- [x] 2.1 Inject the OpenRegister resolver into
  `RegisterObjectFetcher` — done LAZILY and OPTIONALLY via the existing
  `ContainerInterface` (`getRegisterResolver()`), because the class is
  not guaranteed present (unmerged OR change). A `LoggerInterface` was
  added to the constructor for the fallback deprecation log.
- [x] 2.2 The register/schema resolution previously inlined in
  `RegisterObjectFetcher::getMapper()` is extracted into
  `resolveRegisterAndSchema(string $objectTypeLower): array` which:
  prefers the resolver (real API `resolveRegisterId` /
  `resolveSchemaId`, ADR-022) when available; otherwise falls back to
  the legacy `IAppConfig::getValueString` path with identical
  "not configured" error semantics.
  (NB: the spec referenced a `resolveRegisterAndSchema()` method at
  lines 100-127 and a `resolveForObjectType()` resolver method — neither
  existed. The real method was `getMapper()`; the real resolver API is
  `resolveRegisterId`/`resolveSchemaId`. Corrected here.)
- [x] 2.3 `SettingsService` `getValueString` audit (subtask 2.3.1).
- [x] 2.3.1 Inventory of `getValueString` calls:
  | file:line | key(s) | register/schema pair? | migrate? |
  |-----------|--------|-----------------------|----------|
  | `SettingsService.php:112` (`getSettings` loop over `CONFIG_KEYS`) | bulk read of `{type}_register`, `{type}_schema`, `{type}_source`, `register` | mixed | **no** — this is a bulk settings-UI read/round-trip of ~28 keys including non-pair keys (`*_source`, top-level `register`). The resolver resolves ONE pair at a time and throws on missing; it is the wrong shape for a settings dump. Migrating would change UI semantics and lose the source/feature keys. |
  | `SettingsService.php:174` (`getConfigValue`) | arbitrary caller-supplied key | no | **no** — generic single-key reader, not a register/schema pair. |
  Conclusion: `SettingsService` has no resolvable `{type}_register` /
  `{type}_schema` SINGLE-pair call site — its only OR-pair access is
  the bulk settings round-trip. The runtime consumer of those keys is
  `RegisterObjectFetcher`, which IS migrated (2.2). No `SettingsService`
  change is warranted; forcing the resolver there would regress the
  settings UI.
- [x] 2.4 `composer check:strict` green (lint, phpcs, phpmd, psalm,
  phpstan, phpunit). Pre-existing phpcs/phpmd nits in the touched test
  file (named-param sniff, anon-class brace) were also fixed.
- [x] 2.5 Unit test added:
  `tests/unit/Service/RegisterObjectFetcherTest.php::testFallsBackToAppConfigWhenResolverAbsent`
  asserts the legacy `getValueString` path is taken when the resolver
  class is absent (its real state in CI). The existing register/schema
  "not configured" tests continue to exercise the fallback semantics.
  Asserting the resolver-PRESENT branch requires the OR class on the
  classpath (unmerged) and is deferred to an integration test once OR
  ships the resolver.

## Phase 3 — i18n wiring (downstream of OR ADR-025) [DEFERRED]

> **Deferred — blocked on unmerged OR cross-app capability AND a
> library-owned fetch layer.** OpenRegister's object read/write API
> does not yet honour `?_lang=`, the `X-Translation-Target-Language`
> header, or expose `sourceLanguage` object metadata — these live only
> in the unmerged changes `i18n-api-language-negotiation` and
> `i18n-source-of-truth`. Independently, LarpingApp does NOT build OR
> fetch URLs locally: all object CRUD flows through
> `createObjectStore('object')` from `@conduction/nextcloud-vue`
> (`src/store/modules/object.js`). There is no local `orClient.js`
> seam to inject the query param / headers into without forking the
> shared library. The correct home for `?_lang=` /
> `X-Translation-Target-Language` is the shared store, not a per-app
> composable. Tracking issue to be filed against nextcloud-vue +
> openregister; LarpingApp adopts once both ship.

- [ ] 3.1 [DEFERRED] `src/composables/orClient.js` — superseded by the
  library-owned `createObjectStore`; the param/header belong there.
- [ ] 3.2 [DEFERRED] `?_lang={locale}` on fetches — needs OR API support.
- [ ] 3.3 [DEFERRED] `X-Translation-Target-Language` on writes — needs OR
  API support.
- [ ] 3.4 [DEFERRED] Store migration — N/A; single shared object store
  already owns fetches.
- [ ] 3.5 [DEFERRED] "(translated from {lang})" badge — needs
  `sourceLanguage` metadata from OR (unmerged).
- [ ] 3.6 [DEFERRED] e2e for the badge — depends on 3.5.

## Phase 4 — Multi-tenancy wiring (gated on nc-vue release) [DEFERRED]

> **Deferred — explicitly gated by the spec on an unreleased nc-vue
> `useTenantContext()` export** (`multi-tenancy-context` change, no
> versioned release). The spec's own "Pre-release fallback" scenario
> requires that absence MUST NOT crash the app — which is already the
> case, since nothing imports it. Adopt when nc-vue ships the
> composable in a versioned release.

- [ ] 4.1 [DEFERRED] peer constraint on the `useTenantContext` version.
- [ ] 4.2 [DEFERRED] index-view tenant-switch refetch.
- [ ] 4.3 [DEFERRED] detail-view navigate-back on tenant switch.
- [ ] 4.4 [DEFERRED] `X-OpenRegister-Organisation` write stamping.
- [ ] 4.5 [DEFERRED] e2e tenant-switch refetch.

## Phase 5 — Manifest Tier 3 graduation (follow-up tracking)

- [x] 5.1 Tier 3 prerequisites (tracking only): LarpingApp is in
  practice ALREADY past Tier 2 — its nav/router are rendered FROM the
  manifest via `CnPageRenderer` in `src/main.js`, page types are typed
  primitives (`index` / `detail` / `dashboard` / `settings`), and slot
  overrides (incl. the PDF/actions component) resolve through the
  ADR-036 `registry.js`. The remaining Tier-4 step is adopting
  `CnAppRoot` with `customComponents` fully retired; tracked below.
- [ ] 5.2 [DEFERRED — follow-up change] Open
  `larpingapp-manifest-tier-4` (CnAppRoot adoption) once the Phase 5
  prerequisites and nc-vue ADR-036 slot resolver minimum are the
  deployed baseline.

## Phase 6 — Documentation

- [x] 6.1 `docs/architecture.md` created/updated: manifest as the
  declarative route/menu source of truth, the BC-safe
  `RegisterResolverService` consumption pattern, and the deferred i18n
  / multi-tenancy wiring with their blockers.
- [ ] 6.2 [DEFERRED] `docs/features/character-management.md` badge
  screenshots — depends on Phase 3 (deferred).
- [x] 6.3 Architecture doc cross-linked from the app docs index.

## Phase 7 — Verification

- [x] 7.1 `composer check:strict` passes (ALL CHECKS PASSED).
- [x] 7.2 `npm run lint` — no new findings in touched files (no JS
  changed this build).
- [x] 7.3 `npm run check:manifest` runs (structural lint, exit 0;
  pre-existing `roadmap` enum note unrelated to this change).
- [x] 7.4 PHPUnit `RegisterObjectFetcher` resolver-fallback test passes
  (77 tests, 226 assertions, 0 failures) on host vendor; container run
  deferred (no live container in the build sandbox).
- [ ] 7.5 [DEFERRED] i18n / tenant-switch e2e — depends on Phases 3/4.
- [ ] 7.6 [DEFERRED — needs live instance] Manual smoke on a clean dev
  Nextcloud.
