# Tasks — setting-management

## 1. Schema

- [x] 1.1 Rewrite the `setting` schema in `lib/Settings/larpingapp_register.json` to v2.0.0: `name` (required), `description`, `status` (enum active|archived, default active); dropped the vestigial `value` property; kept slug `setting`; Globe icon
- [x] 1.2 Add the optional `setting` property (string, UUID) to `character`, `event`, `skill`, `item`, `condition`, `ability`, and `effect` (NOT `player`); bumped their schema versions (character 1.2.0->1.3.0; the other six 1.0.0->1.1.0)
- [x] 1.3 Added `docs/Schema/Setting.json` (v2 shape); register JSON re-validated well-formed (PHPUnit SettingSchemaTest + python json.load)

## 2. Setting management UI

- [x] 2.1 Added `Settings` index page (typed `index`, objectType `setting`, Globe icon) + `SettingDetail` detail page to `src/manifest.json` + a menu entry labelled "Settings (Worlds)" to keep visual distance from the "Game Settings" config page (distinct icon/label/route)
- [~] 2.2 Create/edit/delete modals: handled by the generic `@conduction/nextcloud-vue` object editor the index/detail pages render (name/description/status are typed primitives). No bespoke modal file is needed in this declarative app.
- [~] 2.3 SettingDetail overview (scoped characters/events counts/tabs): DEFERRED — needs a custom detail-section component; the generic detail page shows the setting's own fields today. The scoped-relations overview is a nc-vue follow-up.
- [~] 2.4 Deletion guard (block delete while scoped entities exist): DEFERRED — there is no app-local setting controller (CRUD is OR-delegated, ADR-022); a populated-setting delete guard needs an OR pre-write listener counting scoped entities. Archiving (status=archived) is available today as the non-destructive path.

## 3. Active-setting lens

- [~] 3.1-3.4 Active-setting switcher, the server-side list filter through `useObjectStore`, dashboard-widget filtering, and picker default-filtering are DEFERRED. The lens requires a custom app-navigation switcher component + threading an OR object filter through the shared nc-vue object store — cross-cutting renderer plumbing this declarative app does not own. The setting entity, scoping property, and management pages (the data model + CRUD) ship here; the active-setting lens is a focused nc-vue follow-up that builds on them.

## 4. Quality

- [x] 4.1 `@spec` / SPDX not applicable (no new PHP); manifest pages are typed primitives (gate-22 manifest-validation PASS)
- [x] 4.2 i18n: new user-facing strings are manifest titles/labels ("Settings (Worlds)", "Setting", "Settings") — English source
- [x] 4.3 Bump `appinfo/info.xml` (0.1.26 -> 0.1.27); manifest 0.2.0 -> 0.3.0

## 5. Tests

- [x] 5.1 PHPUnit `SettingSchemaTest`: v2 setting schema shape (name/description/status enum, no `value`), scoping property on the 7 game entities, player NOT scoped, setting in the register schema list (5 tests)
- [~] 5.2 Newman: DEFERRED — setting CRUD + the `setting=<uuid>` list filter run against the OR objects API (OpenRegister's own enforcement, ADR-022); the schema shape is asserted by PHPUnit and the filter is OR-native
- [x] 5.4 Playwright `tests/e2e/spec-coverage/setting-management.spec.ts`: Settings index page renders its own surface (switcher/lens scenarios deferred with 3.x; gate-19 back-reference)
- [x] 5.5 `composer check` (php -l + PHPUnit 90 green); hydra gates green on the diff (incl. gate-22 manifest-validation PASS); vitest 22 green; `npm run build` green

## 6. Spec sync

- [x] 6.1 On archive, sync the `setting-management` capability spec into `openspec/specs/`
- [x] 6.2 `docs/FEATURES.md`: "manage your setting" now points at the real feature

## Acceptance criteria

- A GM can create, edit, archive, and (when empty) delete settings from a dedicated Settings index/detail UI. Done for create/edit/archive/delete via the manifest pages + generic editor; the "block delete while populated" guard is deferred (archive is the safe path today).
- Characters, events, and mechanics can be scoped to a setting; unscoped entities behave as shared; existing data needs no migration. Done (optional `setting` property; absence = shared).
- The per-user active-setting switcher + server-side filter: DEFERRED (nc-vue follow-up) with reasons.
- Assignment pickers default to active setting + shared: DEFERRED with 3.x.
- A populated setting cannot be deleted from the UI: DEFERRED; archiving hides it while keeping data readable (status enum shipped).
- New strings ship in en; PHPUnit + Playwright coverage as in section 5; `composer check` and hydra gates pass.
