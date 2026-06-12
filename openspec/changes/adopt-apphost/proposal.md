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

## Impact

- **Deleted**: 8 files, ~1,350 lines of drifted boilerplate. **Stubbed**: 3 files to one-liners. **Rewritten**: `Application.php`, `routes.php`. **Modified**: `src/manifest.json` (observability block + deepLinks), `templates/index.php` only if the generic dashboard controller requires it (parity rule says it doesn't).
- **Gained**: `/apps/larpingapp/api/health` (public, ADR-006) and `/apps/larpingapp/api/metrics` (admin, Prometheus text 0.0.4) — endpoints this app has never had. The OR Newman contract collection guards them from day one.
- **Unit tests**: tests for the deleted services (`SettingsServiceTest`, `ConfigFileLoaderServiceTest`, `SettingsMapBuilderTest`, `RegisterFragmentMergeTest`) are deleted with their subjects; behaviour is covered by AppHost's own suites.
- **Risk**: behavioural drift between the deleted copies and the generics — mitigated by the binding parity rules in `apphost-boilerplate-controllers` (route names, response shapes, preference keys, chunk-loading order) plus the existing 113-test behavioural e2e suite, which must stay green.

## Dependencies

Chained on the OpenRegister changes `apphost-observability-engine` and `apphost-boilerplate-controllers` (see `hydra.json`). ADR-040 defines the manifest block; ADR-022 is the architectural basis; ADR-037 fragment-merge parity is binding on the generic load path.
