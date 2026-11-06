# Tasks: Larpinq Adopts OpenRegister AppHost (rescoped)

> Rescoped 2026-10-07 (decision 89) to the partial adoption that shipped. Ticked from the code on development (0d3e28c7). The wholesale `Bootstrap::register()` adoption, the boilerplate deletions and stubs, the `deepLinks` block and the `characters_total` metric were dropped from this change; see the note at the top of `proposal.md`. The former tasks 2.1, 2.3 to 2.6 and 2b.1 to 2b.5 belonged to that dropped scope and are removed.

## 1. Manifest observability block

- [x] 1.1 `src/manifest.json` carries an `observability` block with `health.statusCodePolicy: adr006` and two declared checks, `database` (critical) and `openregister` (`orAvailable`, degraded) (#671)

## 2. Health and metrics through the AppHost generics

- [x] 2.1 `Application::registerAppHostGenerics()` registers `OCA\Larpinq\Controller\HealthController` and `MetricsController` as factories over `GenericHealthController` and `GenericMetricsController`, each behind a `class_exists()` guard (#666)
- [x] 2.2 `appinfo/routes.php` returns `Routes::standard($extra)` behind a `class_exists()` guard; `$extra` keeps every app-owned route, `settings#reimport` included; the local fallback table omits health and metrics (#651, #653, #666)
- [x] 2.3 `register()` runs `OpenRegisterAutoloader::register()` first and registers `DeepLinkRegistrationListener` and `CharacterRequirementListener` after the generics (apphost-autoload-prelude)

## 3. Verification

- [x] 3.1 gate-14 route-reachability accepts the health and metrics routes because the generics are registered in the app's own code (the reason for #666)
