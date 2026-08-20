---
kind: code
---

# Proposal: LarpingApp Adopts OpenRegister AppHost (Observability + Boilerplate)

## Problem

LarpingApp has **never been ADR-006 compliant**: it ships no `HealthController`, no `MetricsController`, and no `/api/health` or `/api/metrics` routes at all. The 2026-06-12 fleet observability inventory lists it (with softwarecatalog and zaakafhandelapp) as a **no-endpoint app** — probes and Prometheus scrapes against it 404 today. There is nothing to migrate; there is a hole to fill.

At the same time, LarpingApp carries the full boilerplate stack that the AppHost extraction was built to delete — all drifted copies of the petstore skeleton, differing from pipelinq/procest/docudesk only in namespace tokens:

- `lib/Controller/DashboardController.php` (75 lines — SPA page + catch-all)
- `lib/Controller/PreferencesController.php` (160 lines — per-user key get/set)
- `lib/Controller/SettingsController.php` (341 lines — index/create/reimport over OR config)
- `lib/Service/SettingsService.php` (194 lines — appconfig CRUD over the `{slug}_register/_schema/_source` key set)
- `lib/Service/SettingsLoadService.php` (201 lines — `importFromApp` + ADR-037 fragment-signature version folding + config writeback)
- `lib/Service/SettingsMapBuilder.php` (170 lines — slug→id maps from import results)
- `lib/Service/ConfigFileLoaderService.php` (267 lines — register JSON load + `register.d/` deep-merge + signature, ADR-037)
- `lib/Settings/LarpingAppAdmin.php` + `lib/Sections/LarpingAppAdmin.php` (admin settings + section)
- `lib/Repair/InitializeRegister.php` (97 lines — repair-step register import)
- `lib/Listener/DeepLinkRegistrationListener.php` (103 lines — hardcoded deep-link patterns)
- `lib/AppInfo/Application.php` (~107 lines) and `appinfo/routes.php` (hand-rolled route array)

The `SettingsService`/`SettingsLoadService`/`SettingsMapBuilder` trio is shared drift with pipelinq — the same ADR-037 fragment-merge plumbing copy-pasted twice.

## Proposed Change

Adopt both halves of the AppHost (`apphost-observability-engine` + `apphost-boilerplate-controllers`) in one change.

### Observability: from nothing to compliant with zero descriptors

This is the headline. Because LarpingApp declares OpenRegister in its manifest `dependencies`, the engine's **defaults** already produce a correct ADR-006 surface — `health = database + orAvailable`, `metrics = larpingapp_info + larpingapp_up` — without declaring a single check or metric. Routing `Routes::standard()` is enough to take both endpoints from 404 to compliant.

We additionally declare exactly **one** metric descriptor as the worked example for the app's main entity (the `character` schema, slug `character` in `lib/Settings/larpingapp_register.json`, register slug `larpingapp`):

```jsonc
// src/manifest.json
"observability": {
  "metrics": [
    { "name": "characters_total", "type": "gauge", "help": "Characters in the register",
      "source": { "kind": "objectCount", "register": "larpingapp", "schema": "character" } }
  ]
}
```

No `health.checks` block: the defaults (`database` + `orAvailable`) are precisely what this app needs. Keep it minimal — descriptors are added later only when an operator actually asks for them.

### Boilerplate: deletions and stubs

**Delete** (replaced by AppHost generics via `Bootstrap::register()` aliases):

| Deleted file | Replaced by |
|---|---|
| `lib/Controller/DashboardController.php` | `GenericDashboardController` alias |
| `lib/Controller/PreferencesController.php` | `GenericPreferencesController` alias |
| `lib/Controller/SettingsController.php` | `GenericSettingsController` alias (`reimport` route maps to the generic force-load) |
| `lib/Service/SettingsService.php` | `AppHostSettingsService` (key set derived from the register JSON schema slugs — same `{slug}_register/_schema/_source` convention) |
| `lib/Service/SettingsLoadService.php` | generic load path (importFromApp + config writeback) |
| `lib/Service/SettingsMapBuilder.php` | generic slug→id mapping |
| `lib/Service/ConfigFileLoaderService.php` | generic register JSON + `register.d/` fragment loader |
| `lib/Listener/DeepLinkRegistrationListener.php` | `GenericDeepLinkRegistrationListener` (patterns move to a manifest `deepLinks` block) |

**Shrink to one-line stubs** (NC demands concrete classes in the app namespace — info.xml `<repair-steps>` / `<settings>`):

- `lib/Repair/InitializeRegister.php` → `extends GenericInitializeSettings {}`
- `lib/Settings/LarpingAppAdmin.php` → `extends GenericAdminSettings {}`
- `lib/Sections/LarpingAppAdmin.php` → `extends GenericSettingsSection {}`

**Rewrite**:

- `lib/AppInfo/Application.php` → ~20-line stub: `APP_ID` const + `Bootstrap::register($context, self::APP_ID)`
- `appinfo/routes.php` → `return \OCA\OpenRegister\AppHost\Routes::standard($extra);` with `$extra` carrying the two genuinely app-specific routes: `characters#downloadPdf` and `settings#reimport`. `Routes::standard()` is what introduces the **new** `/api/health` and `/api/metrics` routes.

### Settings* trio scoping verdicts

- **`SettingsService`** — DELETE. Pure appconfig CRUD over the `{slug}_*` key convention; the `CONFIG_KEYS` list is mechanically derivable from the register JSON's nine schema slugs. `AppHostSettingsService` covers it; nothing app-specific.
- **`SettingsLoadService`** — DELETE, with one binding parity requirement: the generic load path MUST preserve the ADR-037 fragment-signature version folding (`<ver>+frag.<hash>`), or fragment edits stop triggering re-imports. This is shared drift with pipelinq, which is exactly the argument for owning it in AppHost rather than keeping a local copy.
- **`SettingsMapBuilder`** — DELETE. Slug→id normalisation over import results; the only "app" content is the `larpingapp` register slug constant, which the generic derives from appId.
- **`ConfigFileLoaderService`** — DELETE, same ADR-037 parity requirement (deep-merge semantics: key-keyed objects union, lists concatenate, scalars overwrite; deterministic fragment ordering for a stable signature).

**Kept — genuinely app-specific** (untouched by this change):

- `lib/Controller/CharactersController.php` + `lib/Service/CharacterService.php` — character stat computation and PDF download are LarpingApp's actual domain.
- `lib/Service/RegisterObjectFetcher.php` — the backend data-access path for `CharacterService` (per-type mapper resolution with the resolver/legacy fallback and the #212 UUID-only IDOR guard). It is not in the AppHost class inventory and serves only domain code; flagged as a *future* generalisation candidate, out of scope here.

## Binding adoption hazard: registration-name displacement (last registration wins)

`Bootstrap::register()` does not add the generics *alongside* the leaf's classes — it
`registerService()`s **the leaf's own fully-qualified class names** and points them at the
generics. There is no `class_exists()` guard on the leaf side, so the displacement is
unconditional; and because it happens inside `Application::register()`, **only registrations
made AFTER the `Bootstrap::register()` call survive** (last registration wins). Doriath hit
exactly this and now re-registers its domain-divergent concretes in an explicit override block
directly after the call — see the override block in `doriath/lib/AppInfo/Application.php`.

Names LarpingApp loses the moment it calls `Bootstrap::register($context, self::APP_ID)`
(source: `openregister/lib/AppHost/Bootstrap.php`, methods `registerControllers()`,
`registerServices()`, `registerRepairSteps()`, `registerAdminSettings()`,
`registerDeepLinkListener()`):

| Displaced name | Bound to |
|---|---|
| `OCA\LarpingApp\Controller\DashboardController` | `GenericDashboardController` |
| `OCA\LarpingApp\Controller\PreferencesController` | `GenericPreferencesController` |
| `OCA\LarpingApp\Controller\SettingsController` | `GenericSettingsController` |
| `OCA\LarpingApp\Service\SettingsService` | `AppHostSettingsService` |
| `OCA\LarpingApp\Service\ActionAuthService`, `OCA\LarpingApp\Service\RegisterConfigResolver` | AppHost generics |
| `OCA\LarpingApp\Listener\DeepLinkRegistrationListener` | `GenericDeepLinkRegistrationListener` |
| `OCA\LarpingApp\Controller\HealthController` / `MetricsController` | observability generics (new names, no conflict) |

Four concrete defects follow, none of them covered by the plan above. All four surface as
runtime 500s or silent regressions, never as unit-test failures — the unit suite mocks these
classes and never resolves them through the DI container.

**H1 — `SetupController` breaks: HTTP 500 on all three `/api/setup/*` routes.**
`lib/Controller/SetupController.php` is a *kept* file (ADR-042 first-time setup wizard; it
appears nowhere in the deletion table above) and declares
`private readonly SettingsService $settingsService`. `AppHostSettingsService` is a standalone
`class AppHostSettingsService` — it does **not** extend `OCA\LarpingApp\Service\SettingsService`.
Once the name is displaced, the container hands `SetupController` an `AppHostSettingsService`
and PHP throws a `TypeError` at construction. Identical mechanism to the doriath
`/api/dashboard/summary` 500.

**H2 — `settings#reimport` returns HTTP 500 (`ReflectionException`).**
`GenericSettingsController` exposes `index()`, `create()`, `update()` and `load()` — there is
**no `reimport()`**. Task 2.2 keeps the route name `settings#reimport` in `$extra`; Nextcloud
resolves that name to a `reimport` method on the *aliased* controller, which does not exist.
The generic force-load verb is `load()` (`loadConfiguration(force: true)`), so the extra route
must be declared as `['name' => 'settings#load', 'url' => 'api/settings/reimport', 'verb' => 'POST']`
to keep the URL while resolving to a method that exists.

**H3 — the three "one-line stubs" are not autowirable, and are not displaced either.**
Bootstrap registers `Repair\InitializeSettings`, `Settings\AdminSettings` and
`Sections\SettingsSection`. LarpingApp's `appinfo/info.xml` names `Repair\InitializeRegister`,
`Settings\LarpingAppAdmin` and `Sections\LarpingAppAdmin` — **different names, so the aliases
never fire for them**. A bare `class InitializeRegister extends GenericInitializeSettings {}`
inherits a constructor whose first parameter is `string $appId`; `GenericAdminSettings`
requires `string $appId, string $sectionId, int $priority`; `GenericSettingsSection` requires
`string $sectionId, string $name, string $appId, string $iconFile, int $priority`. Nextcloud's
`DIContainer` registers exactly five scalar parameters (`appName`, `urlParams`, `corsMethods`,
`corsAllowedHeaders`, `corsMaxAge`) plus the server container's `isCLI` / `serverRoot`; none of
`appId` / `sectionId` / `priority` / `name` / `iconFile` is among them, and
`SimpleContainer::buildClassConstructorParameters()` rethrows for a builtin-typed parameter
with no default value. Result: the install and post-migration repair steps throw on
`occ app:enable larpingapp`, and the admin settings section throws when Settings is opened.

**H4 — `CharacterRequirementListener` silently stops registering (security regression).**
Apps register alphabetically, so `larpingapp` registers *before* `openregister` and
`OCA\OpenRegister\AppHost\Bootstrap` is not yet autoloadable through Nextcloud's app loader.
An unguarded `Bootstrap::register()` throws `\Error`, which aborts the whole
`Application::register()` — every `registerEventListener()` placed after it silently never runs.
For LarpingApp that means the server-authoritative skill-requirement / XP-budget enforcement on
character writes (`CharacterRequirementListener`, bound to OpenRegister's `ObjectCreatingEvent`
and `ObjectUpdatingEvent`) is **off**, with no error logged anywhere. This is precisely the
incident documented in the `LOAD-ORDER HAZARD` comment in `doriath/lib/AppInfo/Application.php`,
where the audit listener recorded zero dispatched events.

**Remedy (binding).** For every displaced name the app still owns, either (i) re-register the
concrete class **after** `Bootstrap::register()` with an explicit factory closure — doriath's
override-block pattern, last registration wins — or (ii) prove the concrete is genuinely
deleted *and* the generic is behaviour-identical, method-for-method, for every route that
targets it. **Autowirability is not a defence.** LarpingApp's `DashboardController`
(`__construct($appName, IRequest $request)` — the untyped `$appName` resolves because
`DIContainer` registers the `appName` parameter and `SimpleContainer` falls back to the
parameter *name* for untyped parameters), `PreferencesController` and `SettingsController` are
all autowirable today, and it changes nothing: `registerService()` short-circuits autowiring
entirely, and `SettingsController` additionally becomes unconstructible because its own
`SettingsService` dependency name has been displaced out from under it.

**Checked and NOT applicable to LarpingApp** (recorded so nobody re-checks): the CSP half of
the doriath failure does not apply here — LarpingApp calls `allowEvalWasm()` nowhere (a sweep
that finds doriath's two call sites, `DashboardController.php:119` and
`PublicShellController.php:79`, returns nothing for larpingapp), so no served CSP can lose
`wasm-unsafe-eval`. Likewise LarpingApp's `DashboardController` has no `summary()` method and
`appinfo/routes.php` has no `/api/dashboard/summary` route, so there is no dashboard-summary
endpoint to break; its `page()` is a bare `TemplateResponse(APP_ID, 'index')`, which is exactly
what `GenericDashboardController::page()` returns.

## Impact

- **Deleted**: 8 files, ~1,350 lines of drifted boilerplate. **Stubbed**: 3 files to one-liners. **Rewritten**: `Application.php`, `routes.php`. **Modified**: `src/manifest.json` (observability block + deepLinks), `templates/index.php` only if the generic dashboard controller requires it (parity rule says it doesn't).
- **Gained**: `/apps/larpingapp/api/health` (public, ADR-006) and `/apps/larpingapp/api/metrics` (admin, Prometheus text 0.0.4) — endpoints this app has never had. The OR Newman contract collection guards them from day one.
- **Unit tests**: tests for the deleted services (`SettingsServiceTest`, `ConfigFileLoaderServiceTest`, `SettingsMapBuilderTest`, `RegisterFragmentMergeTest`) are deleted with their subjects; behaviour is covered by AppHost's own suites.
- **Risk**: behavioural drift between the deleted copies and the generics — mitigated by the binding parity rules in `apphost-boilerplate-controllers` (route names, response shapes, preference keys, chunk-loading order) plus the existing 113-test behavioural e2e suite, which must stay green.

## Dependencies

Chained on the OpenRegister changes `apphost-observability-engine` and `apphost-boilerplate-controllers` (see `hydra.json`). ADR-040 defines the manifest block; ADR-022 is the architectural basis; ADR-037 fragment-merge parity is binding on the generic load path.
