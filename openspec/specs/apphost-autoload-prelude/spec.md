# apphost-autoload-prelude Specification

## Purpose

LarpingApp probes for classes owned by a sibling app — OpenRegister — from its
own composition root, and registers every one of its event listeners behind
those probes. Nextcloud does not guarantee that the sibling's autoloader is in
place at that moment, and the failure when it is not is completely silent: the
probes answer `false`, the listeners are never registered, and the app keeps
serving as if nothing were wrong.

This spec owns that one invariant: OpenRegister's PSR-4 prefix is on the
autoloader before any `OCA\OpenRegister\…` name is resolved during
`Application::register()`.

## Requirements

### Requirement: OpenRegister's autoloader is registered before its classes are probed

`AppInfo\OpenRegisterAutoloader::register()` SHALL put OpenRegister's PSR-4
prefix on the composer autoloader — via
`OC_App::registerAutoloading('openregister', …)` — before any
`class_exists('OCA\OpenRegister\…')` probe in `Application::register()`.

Nextcloud registers apps in sorted order: `OC_App::getEnabledApps()` does
`sort($apps)` and `Coordinator::registerApps()` walks that list calling
`OC_App::registerAutoloading($appId, $path)` and then `$app->register()` for one
app at a time. Every app's `register()` therefore runs before the PSR-4 prefix of
every alphabetically-later app exists.

`larpingapp` sorts before `openregister`, so without the prelude
`OCA\OpenRegister\` is not autoloadable inside LarpingApp's own `register()` on
a healthy instance with OpenRegister enabled. Every probe answers `false` — not
"not loaded yet", just `false`, indistinguishable from OpenRegister being absent
— and LarpingApp registers no event listeners at all:

- the `DeepLinkRegistrationEvent` listener (unified-search deep links), and
- the `ObjectCreatingEvent` / `ObjectUpdatingEvent` listeners carrying the
  server-authoritative skill-requirement and XP-budget enforcement on character
  writes (see `skill-requirement-enforcement`).

The second is a security property, not a feature: that validation is server-side
precisely because the client cannot be trusted, and a validation that is never
invoked is indistinguishable from having no validation.

`OC_App::registerAutoloading()` is idempotent and touches only the autoloader.
`IAppManager::loadApp('openregister')` MUST NOT be used instead: it marks
OpenRegister loaded and calls `Coordinator::bootApp()`, booting OpenRegister
before its own `register()` has run.

The prelude MUST NOT throw under any instance state — an exception escaping it
would abort the whole of `register()` and leave every listener unregistered,
which is the very outcome it exists to prevent.

This requirement carries no scenarios by design. Both of its behaviours live in
the app-registration phase, which completes before the first request is
dispatched, so neither is reachable from a browser, and the absent-OpenRegister
path cannot be set up on an instance that must have OpenRegister to serve this
app at all. They are asserted directly in
`tests/unit/AppInfo/OpenRegisterAutoloaderTest.php`.

**Notes:** ADR-040. Same defect class as hydra gate-64
(`apphost-autoload-prelude`), one namespace over — that gate's `class_exists`
rule matches only `OCA\OpenRegister\AppHost\…` names, so it does not flag
LarpingApp's `OCA\OpenRegister\Event\…` probes. The load order itself was
measured on the sibling app `openbuild`, which also sorts before `openregister`
and logged `OpenRegister AppHost\Bootstrap is not autoloadable` on every `occ`
call in CI while OpenRegister was installed and enabled the whole time.
