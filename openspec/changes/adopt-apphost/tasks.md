# Tasks: LarpingApp Adopts OpenRegister AppHost

## 0. Baseline — verify absence

- [ ] 0.1 Capture the no-endpoint baseline on a dev instance: `curl -i /apps/larpingapp/api/health` and `curl -i /apps/larpingapp/api/metrics` both return 404 today; record the responses as the before-fixture (this change is from-nothing-to-compliant, not a parity migration)
- [ ] 0.2 Record current route inventory from `appinfo/routes.php` (dashboard#page, characters#downloadPdf, settings#index/create/reimport, preferences#get/setPreference) — all must keep resolving after the switch to `Routes::standard()`

## 1. Manifest observability block

- [ ] 1.1 Add the minimal `observability` block to `src/manifest.json`: no `health.checks` (engine defaults `database` + `orAvailable` apply because the manifest declares OR), one worked-example metric `characters_total` (`objectCount`, register `larpingapp`, schema `character`)
- [ ] 1.2 Add the `deepLinks` block to `src/manifest.json` carrying the patterns currently hardcoded in `lib/Listener/DeepLinkRegistrationListener.php`
- [ ] 1.3 Validate via ManifestService diagnostics (no errors) and gate-22 manifest validation

## 2. Bootstrap, routes, and deletions

- [ ] 2.1 Rewrite `lib/AppInfo/Application.php` to the ~20-line stub: `APP_ID` const + `\OCA\OpenRegister\AppHost\Bootstrap::register($context, self::APP_ID)` (lazy aliases — disabled OR must not fatal NC bootstrap)
- [ ] 2.2 Rewrite `appinfo/routes.php` to `return \OCA\OpenRegister\AppHost\Routes::standard($extra)` with `$extra` = `characters#downloadPdf` + `settings#reimport`; `Routes::standard()` introduces the NEW `/api/health` and `/api/metrics` routes — route names `dashboard#page`, `settings#*`, `preferences#*` unchanged so the info.xml navigation entry keeps working
- [ ] 2.3 Delete `lib/Controller/DashboardController.php`, `lib/Controller/PreferencesController.php`, `lib/Controller/SettingsController.php`, `lib/Service/SettingsService.php`, `lib/Service/SettingsLoadService.php`, `lib/Service/SettingsMapBuilder.php`, `lib/Service/ConfigFileLoaderService.php`, `lib/Listener/DeepLinkRegistrationListener.php`
- [ ] 2.4 Shrink to one-line subclass stubs (NC needs concrete app-namespace classes for info.xml): `lib/Repair/InitializeRegister.php` → `extends GenericInitializeSettings`, `lib/Settings/LarpingAppAdmin.php` → `extends GenericAdminSettings`, `lib/Sections/LarpingAppAdmin.php` → `extends GenericSettingsSection`; info.xml `<repair-steps>`/`<settings>` entries unchanged
- [ ] 2.5 Confirm ADR-037 parity on the generic load path: `register.d/*.json` fragment deep-merge + fragment-signature version folding (`<ver>+frag.<hash>`) still trigger version-gated re-imports — the four leaf JSON fragments (event-calendar, event-location, event-signup, player-to-contacts) must keep importing
- [ ] 2.6 Sweep references: delete the unit tests of the deleted services (`SettingsServiceTest`, `ConfigFileLoaderServiceTest`, `SettingsMapBuilderTest`, `RegisterFragmentMergeTest`); update any imports in kept code (`CharactersController`, `CharacterService`, `RegisterObjectFetcher` keep working untouched)

## 3. Verification

- [ ] 3.1 The endpoints now exist: `curl /apps/larpingapp/api/health` anonymously returns HTTP 200 with `status: ok` and `checks.database`/`checks.openregister` ok (was 404 at 0.1); `curl /apps/larpingapp/api/metrics` as admin returns Prometheus text 0.0.4 containing `larpingapp_info`, `larpingapp_up`, and `larpingapp_characters_total`; metrics as non-admin is rejected
- [ ] 3.2 OR AppHost Newman contract collection green against larpingapp (response shape, auth posture, exposition format)
- [ ] 3.3 Existing 113-test behavioural e2e suite green (dashboard, characters CRUD + stat computation, settings UI, preferences-backed widgets) — proves the generic controllers are behaviour-compatible
- [ ] 3.4 Fresh-install check: `occ app:enable larpingapp` on a clean instance runs the repair-step import through the generic path; register + 9 schemas land and `{slug}_register/_schema/_source` appconfig keys are written

## 4. Docs

- [ ] 4.1 Update app docs: observability section (new endpoints, what they expose, scrape/probe URLs), note that LarpingApp runs on the AppHost and link the manifest block as the per-app example of zero-descriptor compliance

## 5. Quality gates

- [ ] 5.1 `composer check:strict` green; all 18 hydra gates green; gate-22 manifest validation green; `@spec` tags updated on touched files (deleted files take their tags with them)
