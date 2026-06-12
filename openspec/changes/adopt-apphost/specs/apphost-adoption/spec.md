---
status: proposed
---

# LarpingApp AppHost Adoption

## Purpose

LarpingApp serves ADR-006-compliant health and metrics endpoints — which it has never had — through the OpenRegister AppHost engine, and replaces its drifted boilerplate controllers/services/plumbing with the AppHost generics while keeping its domain code (characters, stat computation, PDF) untouched.

**Cross-references**: `openregister/openspec/changes/apphost-observability-engine/specs/`, `openregister/openspec/changes/apphost-boilerplate-controllers/specs/`

---

## Requirements

### Requirement: Observability Endpoints Exist Where None Did

LarpingApp SHALL serve `/apps/larpingapp/api/health` (public) and `/apps/larpingapp/api/metrics` (admin-only, Prometheus text 0.0.4) through the AppHost engine, relying on engine defaults (`database` + `orAvailable` health checks; implicit `larpingapp_info`/`larpingapp_up`) plus exactly one declared `objectCount` metric for the `character` schema.

#### Scenario: Public health endpoint goes 404 to 200

- **GIVEN** a healthy instance with OpenRegister enabled, where this URL returned HTTP 404 before adoption
- **WHEN** `GET /apps/larpingapp/api/health` is called anonymously (no session, no CSRF token)
- **THEN** the response MUST be HTTP 200 with `status: "ok"`, `app: "larpingapp"`, and `checks` reporting the default `database` and `orAvailable` checks as `"ok"`
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Metrics endpoint exists and is admin-gated

- **GIVEN** a seeded instance with characters in the `larpingapp` register, where this URL returned HTTP 404 before adoption
- **WHEN** `GET /apps/larpingapp/api/metrics` is called by an admin
- **THEN** the response MUST be Prometheus text exposition 0.0.4 containing `larpingapp_info`, `larpingapp_up`, and `larpingapp_characters_total` with a value matching the character object count
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Metrics rejected for non-admin

- **GIVEN** an authenticated non-admin user
- **WHEN** that user calls `GET /apps/larpingapp/api/metrics`
- **THEN** the request MUST be rejected by the engine-owned admin posture (no `NoAdminRequired`), never returning metric data
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

#### Scenario: Degraded health when OpenRegister is unavailable

- **GIVEN** an instance where OpenRegister is disabled
- **WHEN** `GET /apps/larpingapp/api/health` is called anonymously
- **THEN** the response MUST follow the ADR-006 `statusCodePolicy` (HTTP 503 with `status: "error"` for the critical `orAvailable` failure) and NC bootstrap MUST NOT have fataled (lazy alias registration)
- @e2e exclude API-only endpoint — covered by the OR AppHost Newman contract collection

### Requirement: Boilerplate Replaced by AppHost Generics

LarpingApp SHALL delete its local DashboardController, PreferencesController, SettingsController, SettingsService, SettingsLoadService, SettingsMapBuilder, ConfigFileLoaderService, and DeepLinkRegistrationListener, serving the equivalent behaviour through AppHost generics wired by `Bootstrap::register()` and `Routes::standard()`, with route names, URLs, response shapes, and preference keys unchanged.

#### Scenario: Existing routes keep resolving after the switch

- **GIVEN** the rewritten `appinfo/routes.php` returning `Routes::standard($extra)`
- **WHEN** the app's existing surfaces are exercised — the SPA dashboard page, settings index/create/reimport, per-user preference get/set, character PDF download
- **THEN** every pre-adoption route (`dashboard#page`, `settings#index`, `settings#create`, `settings#reimport`, `preferences#getPreference`, `preferences#setPreference`, `characters#downloadPdf`) MUST resolve with unchanged URLs, verbs, and response shapes, and the info.xml navigation entry (`larpingapp.dashboard.page`) MUST keep working

#### Scenario: Register import parity through the generic load path (ADR-037)

- **GIVEN** a clean instance and LarpingApp's `lib/Settings/larpingapp_register.json` plus the four `register.d/*.json` leaf fragments
- **WHEN** `occ app:enable larpingapp` runs the repair-step import through `GenericInitializeSettings`, and later a fragment file is edited
- **THEN** the register (slug `larpingapp`) and all nine schemas MUST import with `{slug}_register`/`{slug}_schema`/`{slug}_source` appconfig keys written, and the fragment edit MUST change the folded import version (`<ver>+frag.<hash>`) so the version-gated import re-runs
- @e2e exclude install-time occ/repair-step behaviour — verified by task 3.4 fresh-install check and AppHost unit suites, no UI surface

#### Scenario: Domain code is untouched

- **GIVEN** the adoption is complete
- **WHEN** a user computes character stats or downloads a character PDF
- **THEN** `CharactersController`, `CharacterService`, and `RegisterObjectFetcher` MUST behave exactly as before adoption — they are out of scope and remain app-owned (verified by the existing 113-test behavioural e2e suite staying green)
