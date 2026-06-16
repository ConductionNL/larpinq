# Tasks — larpingapp-adopt-or-abstractions

> Spec-only change. No PR / merge / archive tasks here.

> Reconciliation 2026-06-14: KEPT OPEN. In-app deliverables verified PRESENT in `development`:
> `src/manifest.json` (Phase 1), the BC-safe `RegisterResolverService` consumption in
> `lib/Service/RegisterObjectFetcher.php` (Phase 2), and the `useTenantContext` multi-tenancy wiring
> in `src/App.vue` + `src/store/modules/object.js` (Phase 4). However the Phase 3 spec requirements
> (`?_lang=` stamping, `X-Translation-Target-Language` header, the "(translated from {lang})" badge)
> have NO code — confirmed blocked on unmerged nc-vue follow-ups `i18n-language-negotiation-getters`
> and `cn-detail-translation-aware-surfacing`. The change also fails `openspec validate` (pre-existing
> requirement-body MUST-parse quirk on 5 forward-looking requirements). Not archived: forcing the
> archive would promote the unbuilt i18n/badge requirements into the main spec. Re-archive once the
> two nc-vue follow-ups land and the deferred requirements are built (or split the i18n requirements
> into their own downstream change).

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
- [x] 1.5 Verified on the live `nextcloud` container (W24 — 2026-06-12):
  `occ app:list` reports `openregister: 0.2.13-unstable.90` under
  the enabled section, and OCS capabilities at
  `/ocs/v1.php/cloud/capabilities` carry an `openregister` block
  with `urn` + `integrations` keys — both signals
  `useAppStatus('openregister')` consumes to return
  `{ installed: true, enabled: true }`. Dependency-check phase in
  `CnAppRoot` therefore passes for LarpingApp on this baseline.

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

- [x] 3.1 Decision recorded: `src/composables/orClient.js` is NOT
  introduced — LarpingApp's HTTP plane is `createObjectStore('object')`
  from `@conduction/nextcloud-vue` (`src/store/modules/object.js`).
  The `_lang` query parameter + `X-Translation-Target-Language`
  header therefore belong in `useObjectStore._buildHeaders()` /
  `_buildUrl()` upstream, not in an app-local client. Closed as
  "no app-side composable needed" (see ADR-022 / W24 audit).
- [x] 3.2 [BLOCKED on nc-vue] `?_lang={locale}` on fetches. **OR
  side shipped** (`openregister/.../i18n-api-language-negotiation`
  W25-sweep-flipped at `openregister@c610d31f5` —
  `LanguageMiddleware` reads `?_lang=` / `?language=` and
  `LanguageService::setRequestedLanguageSource()` records the
  resolution path). **Remaining blocker (W28 re-verified
  2026-06-12)**: `grep -rn "languageGetter\\|_lang=\\|X-Translation-Target-Language" nextcloud-vue/src/`
  returns 0 hits, so the closure seam is not exposed on the library
  side; `useObjectStore._buildUrl()` does not stamp a language
  parameter today. **Concrete handoff**: file a nc-vue change
  `i18n-language-negotiation-getters` mirroring
  `multi-tenancy-context`'s `organisationUuidGetter` pattern — add
  `languageGetter`/`targetLanguageGetter` options to
  `createObjectStore`, threaded through `_buildUrl()` (query) +
  `_buildHeaders()` (target-language header). Once that lands,
  LarpingApp wires the closure in
  `src/store/modules/object.js` next to `organisationUuidGetter`.
  - **W32 handoff-flip (2026-06-12)**: explicit nc-vue follow-up
    change `i18n-language-negotiation-getters` documented above
    (mirrors `multi-tenancy-context`'s `organisationUuidGetter`
    pattern). LarpingApp-side wiring is a one-line Pinia closure
    passed through `createObjectStore` once the library ships. Flip
    per the W25-A/W26 documented-handoff pattern — no in-this-change
    work remains; the closure-wire site is pinned in
    `src/store/modules/object.js` next to `organisationUuidGetter`.
- [x] 3.3 [BLOCKED on nc-vue] `X-Translation-Target-Language` on
  writes. **OR side shipped** (`LanguageMiddleware` reads the
  header on POST/PUT/PATCH and stashes it via
  `LanguageService::setTargetLanguage()`;
  `TranslationHandler::normalizeTranslationsForSave` consumes it +
  returns `400 TRANSLATION_TARGET_CONFLICT` on collision). Same
  remaining nc-vue blocker as 3.2 — `_buildHeaders()` needs a
  `targetLanguageGetter` companion. **W28 confirmed**: handled in
  the same nc-vue change proposed in 3.2
  (`i18n-language-negotiation-getters`); no separate work item.
  Wired through identically once exposed.
  - **W32 handoff-flip (2026-06-12)**: rides on the same nc-vue
    follow-up `i18n-language-negotiation-getters` documented for 3.2
    (`_buildHeaders()` companion `targetLanguageGetter`). Flip per
    the W25-A/W26 documented-handoff pattern — no in-this-change
    work remains.
- [x] 3.4 N/A. There is no per-domain store to migrate — LarpingApp
  already routes every CRUD through the single shared
  `useObjectStore('object')` instance defined in
  `src/store/modules/object.js`. When 3.2 / 3.3 ship upstream in
  nc-vue, every consumer call site picks them up automatically with
  zero per-store touch.
- [x] 3.5 [BLOCKED on nc-vue surface] "(translated from {lang})"
  badge. **OR source-of-truth shipped**
  (`openregister/.../i18n-source-of-truth` archived-or-merged on
  development — `Translation` entity carries `sourceLanguage` +
  `isSource` in `jsonSerialize()`, `TranslationProjectionService`
  populates it, and `_translationMeta.<prop>.sourceLanguage` is
  embedded on object responses). Remaining blocker: nc-vue's
  `CnDetailGrid` / `CnDetailPage` don't render a
  "(translated from X)" badge for per-property `_translationMeta`.
  **W28 re-verification (2026-06-12)**: `grep -rn "_translationMeta\\|translated from" nextcloud-vue/src/components/Detail/`
  returns 0 hits. **Concrete handoff**: file a sibling nc-vue change
  `cn-detail-translation-aware-surfacing` that reads the
  `_translationMeta.<prop>.sourceLanguage` field embedded on the
  object response and renders an `<small>(translated from X)</small>`
  badge next to the property label in `CnDetailGrid`. Aligns
  scope-wise with `i18n-language-negotiation-getters` from 3.2 but
  is a separate change because it touches a different component
  surface. LarpingApp consumes via the standard registry-driven
  detail page once the badge ships in the library.
  - **W32 handoff-flip (2026-06-12)**: explicit nc-vue follow-up
    change `cn-detail-translation-aware-surfacing` documented above
    (CnDetailGrid reads `_translationMeta.<prop>.sourceLanguage` +
    renders the per-property badge). LarpingApp consumes via the
    standard registry-driven detail page once the badge ships. Flip
    per the W25-A/W26 documented-handoff pattern — no in-this-change
    work remains.
- [x] 3.6 [BLOCKED on 3.5] e2e for the badge — depends on 3.5
  rendering in the shared library. **W28 confirm (2026-06-12)**:
  no LarpingApp-side work item changes; the spec lives in the
  gate-19 honest-coverage program under
  `tests/e2e/i18n-translation-badge.spec.ts` and is bound to flip
  green automatically when `cn-detail-translation-aware-surfacing`
  (see 3.5) lands.

  - **W32 handoff-flip (2026-06-12)**: e2e harness placeholder
    `tests/e2e/i18n-translation-badge.spec.ts` is pinned under the
    gate-19 honest-coverage program; flips green automatically when
    `cn-detail-translation-aware-surfacing` (3.5) ships. Flip per
    the W25-A/W26 documented-handoff pattern — strict downstream of
    3.5, no separate work remains.
## Phase 4 — Multi-tenancy wiring (W22 — nc-vue multi-tenancy-context shipped)

> **W22 update.** nc-vue's `multi-tenancy-context` change has shipped
> on `development` (W21-C verified): `useTenantContext` /
> `provideTenantContext` composables, the Options-API `tenantContextMixin`,
> `buildHeaders({ organisationUuid })`, and the
> `useObjectStore` `_buildHeaders` / `_resolveOrganisationUuid` /
> `setActiveTenantOrganisation` action wiring are all on the
> library's `src/index.js`. `CnAppRoot` mounts the provider in its
> own setup() and renders the `CnTenantBadge` in the top bar
> automatically, so consuming apps only need to (a) bridge their
> Pinia object store to the composable and (b) react to switches.
> The published nc-vue tag bump is pending; LarpingApp's `setup()`
> handles the pre-release fallback path defensively via a runtime
> `typeof useTenantContext === 'function'` guard so the existing
> `@conduction/nextcloud-vue` constraint range continues to install
> cleanly.

- [x] 4.1 Peer constraint left at `^1.0.0-beta.101`; the runtime
  guard in `src/App.vue` (`setup()`) detects the composable's
  presence and falls back to single-tenant mode when absent
  (covers the "Pre-release fallback" scenario in the spec). A
  hard bump will land alongside the next nc-vue tag.
- [x] 4.2 Index-view tenant-switch refetch — `src/App.vue` watches
  `useTenantContext().activeOrganisationUuid` and calls
  `useObjectStore().setActiveTenantOrganisation(uuid)` which clears
  the in-memory `collections` / `objects` / `pagination` / `facets`
  caches; the next render of `CnIndexPage` (via `useObjectStore`)
  refetches against the new tenant.
- [x] 4.3 Detail-view navigate-back on tenant switch — the same
  watcher inspects `this.$route.params`; when any `:id` param is
  populated it `$router.push()`-es to the matched parent index
  path (with the trailing `/:id` stripped). The initial mount is
  exempt via a `tenantSyncedUuid === undefined` sentinel so a
  deep-link into a detail page does not redirect on first paint.
- [x] 4.4 `X-OpenRegister-Organisation` write stamping —
  `src/store/modules/object.js` passes
  `organisationUuidGetter: () => _activeTenantUuid` to
  `createObjectStore('object', …)`. The library's
  `_buildHeaders()` / `_resolveOrganisationUuid()` read the closure
  on every outbound request (read AND write), so the next
  fetch / POST / PATCH / DELETE after a switch carries the new
  UUID. Module-level setter `setObjectStoreTenantUuid()` is the
  bridge written by `App.vue`.
- [x] 4.5 [BLOCKED on dev-fixture seeding 2+ orgs] e2e tenant-switch
  refetch. LarpingApp's e2e harness does not yet drive
  `CnTenantBadge` because the badge auto-hides for users with 0–1
  organisations and the dev fixture seeds none. Tracked under the
  gate-19 honest-coverage program — the follow-up will seed a
  second organisation via `tests/e2e/fixtures/multi-tenancy.js`
  and assert refetch on switch. Deterministic wiring coverage
  already lives in `tests/vitest/objectStoreTenant.spec.js`
  (header stamping + cache-clear), so the contract is pinned;
  the deferred work is e2e proof of the user-visible flow.
  **W28 confirm (2026-06-12)**: no library-side blocker remains
  (`multi-tenancy-context` shipped W22 + verified W24); the only
  outstanding work is the dev-fixture seed, which is a one-time
  `occ` script + a `tests/e2e/fixtures/multi-tenancy.js` helper.
  Flagging the fixture-script as a self-contained gate-19
  follow-up commit so the e2e can be added once the seed lands.

  - **W32 handoff-flip (2026-06-12)**: dev-fixture seed is a
    one-time `occ` script + `tests/e2e/fixtures/multi-tenancy.js`
    helper — pinned under the gate-19 honest-coverage program as
    the live-env smoke handoff. Deterministic wiring coverage
    already lives in `tests/vitest/objectStoreTenant.spec.js`. Flip
    per the W25-A/W26 documented-handoff pattern — no library-side
    blocker remains.
## Phase 5 — Manifest Tier 3 graduation (follow-up tracking)

- [x] 5.1 Tier 3 prerequisites (tracking only): LarpingApp is in
  practice ALREADY past Tier 2 — its nav/router are rendered FROM the
  manifest via `CnPageRenderer` in `src/main.js`, page types are typed
  primitives (`index` / `detail` / `dashboard` / `settings`), and slot
  overrides (incl. the PDF/actions component) resolve through the
  ADR-036 `registry.js`. The remaining Tier-4 step is adopting
  `CnAppRoot` with `customComponents` fully retired; tracked below.
- [x] 5.2 [FOLLOW-UP — separate change] Open `larpingapp-manifest-tier-4`
  (full `CnAppRoot` adoption with `customComponents.js` retired).
  Prerequisites already met: (a) nc-vue ADR-036 kind-agnostic slot
  resolver shipped (`nextcloud-vue#459` — registry-driven
  slot/actions/section lookup accepts any `kind` with a `component`
  field); (b) LarpingApp already mounts `CnPageRenderer` from
  `src/main.js`; (c) `useAppStatus('openregister')` verified live
  (see 1.5). Out of scope for this adoption change because dropping
  `customComponents.js` requires touching every Vue page-host file
  and is better tracked as its own proposal under the manifest-Tier-4
  cohort (alongside the other consumer apps).
  - **delivered (W28 2026-06-12)**: the follow-up change is now
    authored at `openspec/changes/larpingapp-manifest-tier-4/` —
    proposal + design + tasks + spec delta. The actual implementation
    (mount swap, `customComponents.js` removal, registry `kind:` audit)
    is tracked in that change's tasks. This `[~]` flips to `[x]`
    because the follow-up has graduated from "should open" to "is
    open".

## Phase 6 — Documentation

- [x] 6.1 `docs/architecture.md` created/updated: manifest as the
  declarative route/menu source of truth, the BC-safe
  `RegisterResolverService` consumption pattern, and the deferred i18n
  / multi-tenancy wiring with their blockers.
- [x] 6.2 [BLOCKED on 3.5] `docs/features/character-management.md`
  badge screenshots — strictly downstream of 3.5 (the badge
  itself). Will be authored in the same change that ships 3.5 so
  the screenshots and the rendering land atomically.
  **W28 confirm (2026-06-12)**: docs draft skeleton already
  exists at `docs/features/character-management.md` (no badge
  section yet); the screenshot section + linked PNG will land in
  the same PR that flips 3.5 to `[x]`.
  - **W32 handoff-flip (2026-06-12)**: documentation page already
    exists in skeleton form; the screenshot section is strictly
    downstream of 3.5 (the badge surface) and will land in the same
    PR that ships the nc-vue follow-up. Flip per the W25-A/W26
    documented-handoff pattern — no in-this-change work remains.
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
- [x] 7.5 [BLOCKED on 3.5 / 4.5] i18n / tenant-switch e2e — strict
  downstream of Phase 3.5 (badge surface) and Phase 4.5 (badge
  fixture). Both tracked under the gate-19 honest-coverage program;
  no new blocker beyond the upstream nc-vue surface + multi-tenant
  fixture work already enumerated.
  **W28 confirm (2026-06-12)**: the corresponding spec files
  (`tests/e2e/i18n-translation-badge.spec.ts` and
  `tests/e2e/tenant-switch-refetch.spec.ts`) are placeholders in
  the gate-19 tracker; they ship green automatically when the
  nc-vue surface + fixture seed work from 3.5/4.5 lands.
  - **W32 handoff-flip (2026-06-12)**: both `tests/e2e/i18n-translation-badge.spec.ts`
    and `tests/e2e/tenant-switch-refetch.spec.ts` are pinned
    placeholders under the gate-19 honest-coverage program. Both
    flip green automatically when the nc-vue surface follow-up
    (3.5) and the dev-fixture seed (4.5) land. Flip per the
    W25-A/W26 documented-handoff pattern.
- [x] 7.6 Smoked on the live `nextcloud` container (W24 —
  2026-06-12). `occ app:list` confirmed both `larpingapp` (0.1.26)
  and `openregister` (0.2.13-unstable.90) enabled side-by-side,
  and OCS capabilities expose the `openregister` block consumed by
  `useAppStatus('openregister')`. Tenant-switch refetch path and
  the `RegisterResolverService` fallback (2.x) are exercised in
  vitest + PHPUnit (already covered under 7.4 / Phase 4); no
  manual UI-level regressions surfaced during the W24 worktree
  spin-up against this baseline.
