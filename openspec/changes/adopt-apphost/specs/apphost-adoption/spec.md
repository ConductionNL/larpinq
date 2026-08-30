---
status: proposed
---

# Larpinq AppHost Adoption

## Purpose

Larpinq serves ADR-006-compliant health and metrics endpoints — which it has never had — through the OpenRegister AppHost engine, and replaces its drifted boilerplate controllers/services/plumbing with the AppHost generics while keeping its domain code (characters, stat computation, PDF) untouched.

**Cross-references**: `openregister/openspec/changes/apphost-observability-engine/specs/`, `openregister/openspec/changes/apphost-boilerplate-controllers/specs/`

---

## Requirements

### Requirement: Observability Endpoints Exist Where None Did

Larpinq SHALL serve `/apps/larpinq/api/health` (public) and `/apps/larpinq/api/metrics` (admin-only, Prometheus text 0.0.4) through the AppHost engine, relying on engine defaults (`database` + `orAvailable` health checks; implicit `larpinq_info`/`larpinq_up`) plus exactly one declared `objectCount` metric for the `character` schema.

#### Scenario: Public health endpoint goes 404 to 200

- **GIVEN** a healthy instance with OpenRegister enabled, where this URL returned HTTP 404 before adoption
- **WHEN** `GET /apps/larpinq/api/health` is called anonymously (no session, no CSRF token)
- **THEN** the response MUST be HTTP 200 with `status: "ok"`, `app: "larpinq"`, and `checks` reporting the default `database` and `orAvailable` checks as `"ok"`
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Metrics endpoint exists and is admin-gated

- **GIVEN** a seeded instance with characters in the `larpingapp` register, where this URL returned HTTP 404 before adoption
- **WHEN** `GET /apps/larpinq/api/metrics` is called by an admin
- **THEN** the response MUST be Prometheus text exposition 0.0.4 containing `larpinq_info`, `larpinq_up`, and `larpinq_characters_total` with a value matching the character object count
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Metrics rejected for non-admin

- **GIVEN** an authenticated non-admin user
- **WHEN** that user calls `GET /apps/larpinq/api/metrics`
- **THEN** the request MUST be rejected by the engine-owned admin posture (no `NoAdminRequired`), never returning metric data
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Degraded health when OpenRegister is unavailable

- **GIVEN** an instance where OpenRegister is disabled
- **WHEN** `GET /apps/larpinq/api/health` is called anonymously
- **THEN** the response MUST follow the ADR-006 `statusCodePolicy` (HTTP 503 with `status: "error"` for the critical `orAvailable` failure) and NC bootstrap MUST NOT have fataled (lazy alias registration)
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

### Requirement: Boilerplate Replaced by AppHost Generics

Larpinq SHALL delete its local DashboardController, PreferencesController, SettingsController, SettingsService, SettingsLoadService, SettingsMapBuilder, ConfigFileLoaderService, and DeepLinkRegistrationListener, serving the equivalent behaviour through AppHost generics wired by `Bootstrap::register()` and `Routes::standard()`, with route names, URLs, response shapes, and preference keys unchanged.

#### Scenario: Existing routes keep resolving after the switch

- **GIVEN** the rewritten `appinfo/routes.php` returning `Routes::standard($extra)`
- **WHEN** the app's existing surfaces are exercised — the SPA dashboard page, settings index/create/reimport, per-user preference get/set, character PDF download
- **THEN** every pre-adoption route (`dashboard#page`, `settings#index`, `settings#create`, `settings#reimport`, `preferences#getPreference`, `preferences#setPreference`, `characters#downloadPdf`) MUST resolve with unchanged URLs, verbs, and response shapes, and the info.xml navigation entry (`larpinq.dashboard.page`) MUST keep working

#### Scenario: Register import parity through the generic load path (ADR-037)

- **GIVEN** a clean instance and Larpinq's `lib/Settings/larpinq_register.json` plus the four `register.d/*.json` leaf fragments
- **WHEN** `occ app:enable larpinq` runs the repair-step import through `GenericInitializeSettings`, and later a fragment file is edited
- **THEN** the register (slug `larpingapp`) and all nine schemas MUST import with `{slug}_register`/`{slug}_schema`/`{slug}_source` appconfig keys written, and the fragment edit MUST change the folded import version (`<ver>+frag.<hash>`) so the version-gated import re-runs
- @e2e exclude install-time occ/repair-step behaviour — verified by task 3.4 fresh-install check and AppHost unit suites, no UI surface

#### Scenario: Every route resolves a method that exists on the class actually bound

- **GIVEN** `Bootstrap::register()` has bound `OCA\Larpinq\Controller\{Dashboard,Preferences,Settings}Controller` to the AppHost generics
- **WHEN** any route whose name targets one of those controllers is dispatched
- **THEN** the named method MUST exist on the generic that is actually bound — `GenericSettingsController` provides `index`, `create`, `update`, `load` and NOT `reimport`, so the pre-adoption `settings#reimport` route MUST be re-pointed at `settings#load` while keeping the `api/settings/reimport` URL, and MUST NOT return HTTP 500
- @e2e exclude API-only route resolution — covered by gate-14 route-reachability and task 3.5

### Requirement: AppHost Displacement Never Breaks App-Owned Code

`Bootstrap::register()` re-binds Larpinq's own fully-qualified class names to the AppHost generics, unconditionally and with no `class_exists()` guard, and the binding is order-sensitive (last registration wins). Larpinq SHALL therefore, for every displaced name it still owns, either delete the concrete and prove the generic is behaviour-identical, or re-register the concrete AFTER the `Bootstrap::register()` call; and SHALL guard the call itself so an unloadable AppHost can never abort the app's own registrations.

#### Scenario: Kept code that depends on a displaced service still constructs

- **GIVEN** `SetupController` (ADR-042, kept by this change) type-hints `OCA\Larpinq\Service\SettingsService`, a name Bootstrap re-binds to the non-subclass `AppHostSettingsService`
- **WHEN** `GET /api/setup/status`, `POST /api/setup/config` or `POST /api/setup/action/{actionId}` is dispatched after adoption
- **THEN** the controller MUST construct without a `TypeError` and the response MUST NOT be HTTP 500 — satisfied either by re-registering a concrete `SettingsService` (and `SetupController`) after `Bootstrap::register()`, or by retyping the kept consumers against the AppHost service
- @e2e exclude API-only endpoint — covered by task 3.5

#### Scenario: info.xml-referenced stubs resolve through the container

- **GIVEN** `appinfo/info.xml` names `Repair\InitializeRegister`, `Settings\LarpinqAdmin` and `Sections\LarpinqAdmin`, none of which matches the names Bootstrap aliases (`Repair\InitializeSettings`, `Settings\AdminSettings`, `Sections\SettingsSection`)
- **WHEN** the container is asked to resolve each of those three classes
- **THEN** each MUST return an instance without throwing — a bare `extends Generic… {}` stub MUST NOT be accepted, because the inherited constructors take builtin scalars (`appId`, `sectionId`, `priority`, `name`, `iconFile`) that Nextcloud's `DIContainer` does not register, so the install/post-migration repair step and the admin Settings section would throw
- @e2e exclude install-time and settings-framework construction — covered by tasks 2b.3 and 3.4

#### Scenario: An unloadable AppHost never silences the app's own listeners

- **GIVEN** apps register alphabetically, so `larpinq` registers before `openregister` and `OCA\OpenRegister\AppHost\Bootstrap` may not yet be autoloadable
- **WHEN** `Application::register()` runs and the `Bootstrap::register()` call cannot load the AppHost
- **THEN** the failure MUST be contained (OpenRegister's own autoloader pulled in, the call wrapped in `try`/`catch (\Throwable)`) and `CharacterRequirementListener` MUST still be registered against `ObjectCreatingEvent` and `ObjectUpdatingEvent`, so a character write that violates a skill requirement or the XP budget is still rejected server-side — an aborted `register()` produces no log line, so absence of errors MUST NOT be treated as evidence
- @e2e exclude bootstrap-time registration — covered by task 3.6 (behavioural rejection assertion)

#### Scenario: Domain code is untouched

- **GIVEN** the adoption is complete
- **WHEN** a user computes character stats or downloads a character PDF
- **THEN** `CharactersController`, `CharacterService`, and `RegisterObjectFetcher` MUST behave exactly as before adoption — they are out of scope and remain app-owned (verified by the existing 113-test behavioural e2e suite staying green)
