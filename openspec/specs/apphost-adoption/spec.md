# Larpinq AppHost adoption

## Purpose

`\OCA\OpenRegister\AppHost\Routes::standard()` builds larpinq's route table, and
it emits `health#index` and `metrics#index` for every adopter. Larpinq routed
both from the day it adopted the builder and implemented neither, so both URLs
resolved to a controller class that did not exist. gate-14 route-reachability
named them `controller-class-not-found`.

This spec owns the two observability endpoints and, more importantly, the way
they are allowed to reach OpenRegister. The engine that answers them belongs to
a sibling app that larpinq does not declare as a dependency and cannot assume is
installed, so the shape of the coupling is the requirement, not an implementation
note.

## Requirements

### Requirement: Health answers on an instance without OpenRegister

`GET /api/health` SHALL return the ADR-006 envelope `{status, app, version,
checks}` and SHALL answer whether or not the OpenRegister AppHost observability
engine can be resolved.

A health probe that fails when its dependency is missing reports nothing about
the thing being probed. Monitoring cannot tell "larpinq is down" from "larpinq
cannot answer", so the degraded case is a documented 200 rather than an error.

#### Scenario: OpenRegister absent

- **WHEN** the AppHost observability engine cannot be resolved from the container
- **THEN** the endpoint responds HTTP 200
- **AND** the envelope carries `status: degraded`
- **AND** `checks.openregister` reads `unavailable`
- **AND** `version` falls back to larpinq's own `installed_version`

#### Scenario: Engine available

- **WHEN** the engine resolves and executes the manifest-declared checks
- **THEN** the endpoint responds with the status code the engine's policy resolved
- **AND** the envelope carries the engine's `status`, `version` and `checks`
- **AND** CORS headers are emitted only when the manifest opts in

### Requirement: Metrics are admin-only and degrade to 503

`GET /api/metrics` SHALL render Prometheus text exposition 0.0.4 for
administrators, and SHALL NOT expose metric data to anonymous callers.

The posture comes from the deliberate absence of `#[NoAdminRequired]`. Nextcloud
requires an admin session for a controller method that does not carry it, so an
anonymous caller gets the login redirect and never reaches the engine.

#### Scenario: Engine unavailable

- **WHEN** the AppHost metrics engine cannot be resolved
- **THEN** the endpoint responds HTTP 503
- **AND** the body is a Prometheus comment line naming the missing engine
- **AND** the response is never a 500

#### Scenario: Content type

- **WHEN** the endpoint answers at all, degraded or not
- **THEN** the `Content-Type` header is `text/plain; version=0.0.4; charset=utf-8`

### Requirement: The observability controllers never bind an OpenRegister class at declaration time

Both controllers SHALL resolve OpenRegister collaborators from the DI container
by fully-qualified name at dispatch time, and SHALL NOT name an OpenRegister
class in any position the autoloader resolves: no `extends`, no `implements`, no
`use` import, no typed parameter or return.

This is a whole-app property, not a per-endpoint one. Nextcloud's router
reflects every file in `lib/Controller/` while matching a route, so a single
unresolvable parent class makes **every** route in larpinq return HTTP 500, not
only the one being matched. `extends` is resolved by the autoloader rather than
the container, so no amount of lazy registration rescues it. Larpinq does not
declare `<app>openregister</app>` in `appinfo/info.xml`, so an administrator can
create exactly the instance where this bites.

#### Scenario: Class declaration is inspected

- **WHEN** either observability controller is loaded on an instance without OpenRegister
- **THEN** the class declaration resolves
- **AND** every OpenRegister name it uses appears only as a string constant

See also [apphost-autoload-prelude](../apphost-autoload-prelude/spec.md), which
owns the matching invariant for `Application::register()`.
