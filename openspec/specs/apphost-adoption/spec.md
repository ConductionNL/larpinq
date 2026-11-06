# apphost-adoption Specification

## Purpose
Larpinq serves ADR-006 health and metrics endpoints, which it never had, by
borrowing OpenRegister's AppHost generic health and metrics controllers under
its own controller names. The adoption is partial on purpose: Larpinq keeps its
own dashboard, settings, preferences and setup controllers and services, and
does not call `Bootstrap::register()`, because that call would alias those
names onto generics that do not match Larpinq's own behaviour.

## Requirements

### Requirement: Health and metrics are served through the AppHost generics

Larpinq SHALL serve `/apps/larpinq/api/health` (public) and
`/apps/larpinq/api/metrics` (admin only, Prometheus text 0.0.4) by registering
`OCA\Larpinq\Controller\HealthController` and
`OCA\Larpinq\Controller\MetricsController` in `Application::register()` as
factories that build OpenRegister's `GenericHealthController` and
`GenericMetricsController` with `appName: larpinq`. Each registration MUST be
guarded by a `class_exists()` probe on the generic, so an instance without the
AppHost still boots. The routes MUST come from `Routes::standard()`. The
`observability` block in `src/manifest.json` MUST declare the health checks
explicitly: `database` (type `database`, severity `critical`) and
`openregister` (type `orAvailable`, severity `degraded`), with
`statusCodePolicy: adr006`. Metrics MUST be the engine's implicit
`larpinq_info` and `larpinq_up` gauges; no app-specific metric descriptor is
declared.

#### Scenario: Public health endpoint answers

- **GIVEN** a healthy instance with OpenRegister enabled
- **WHEN** `GET /apps/larpinq/api/health` is called anonymously
- **THEN** the response MUST be HTTP 200 with `status: "ok"` and `checks` reporting `database` and `openregister` as `"ok"`
- @e2e exclude API-only endpoint, covered by the OR AppHost Newman contract collection

#### Scenario: Health reports the declared checks, never an empty set

- **GIVEN** the generic health controller reads its checks from the manifest `observability.health.checks` block
- **WHEN** the health endpoint runs
- **THEN** it MUST execute the two declared checks, so a database outage turns the response critical instead of answering `ok` over an empty `checks` object
- @e2e exclude API-only endpoint, covered by the OR AppHost Newman contract collection

#### Scenario: Metrics endpoint is admin gated

- **GIVEN** an instance with OpenRegister enabled
- **WHEN** an admin calls `GET /apps/larpinq/api/metrics`
- **THEN** the response MUST be Prometheus text exposition 0.0.4 containing `larpinq_info` and `larpinq_up`
- **AND WHEN** an authenticated non-admin calls the same URL, the request MUST be rejected without metric data
- @e2e exclude API-only endpoint, covered by the OR AppHost Newman contract collection

#### Scenario: Without OpenRegister the endpoints are not advertised

- **GIVEN** an instance where OpenRegister is absent, so `OCA\OpenRegister\AppHost\Routes` does not load
- **WHEN** `appinfo/routes.php` builds its local fallback table
- **THEN** the table MUST NOT contain the health and metrics routes, because no class would answer them, and every other Larpinq route, the SPA catch-all included, MUST still resolve
- @e2e exclude route-table behaviour without OpenRegister, covered by gate-14 route-reachability

### Requirement: Larpinq keeps its own boilerplate and does not adopt Bootstrap::register()

Larpinq SHALL NOT call `OCA\OpenRegister\AppHost\Bootstrap::register()`. Its
own `DashboardController`, `SettingsController`, `PreferencesController`,
`SetupController`, `SettingsService`, `SettingsLoadService`,
`SettingsMapBuilder`, `ConfigFileLoaderService` and
`DeepLinkRegistrationListener` MUST stay the classes bound under their names,
and every pre-existing route MUST keep its URL, verb and target method,
`settings#reimport` on `SettingsController::reimport()` included.

#### Scenario: Existing routes keep resolving to Larpinq's own controllers

- **GIVEN** `appinfo/routes.php` passes Larpinq's routes to `Routes::standard($extra)`, where an `$extra` route overrides a canonical route of the same name
- **WHEN** the dashboard page, settings index/create/update/reimport, preference get/set or the character PDF download is requested
- **THEN** each route MUST resolve to the method on Larpinq's own controller with an unchanged URL and verb, and the info.xml navigation entry `larpinq.dashboard.page` MUST keep working
- @e2e exclude route resolution, covered by gate-14 route-reachability

#### Scenario: Registering the generics never silences Larpinq's listeners

- **GIVEN** `larpinq` registers before `openregister`, and `Application::register()` runs `OpenRegisterAutoloader::register()` before any probe
- **WHEN** the health and metrics generics are registered
- **THEN** the registration MUST NOT throw out of `register()`, and `DeepLinkRegistrationListener` and `CharacterRequirementListener` (on `ObjectCreatingEvent` and `ObjectUpdatingEvent`) MUST still be registered after it
- @e2e exclude bootstrap-time registration, covered by the apphost-autoload-prelude spec
