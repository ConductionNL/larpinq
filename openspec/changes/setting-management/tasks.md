# Tasks — setting-management

## 1. Schema

- [ ] 1.1 Rewrite the `setting` schema in `lib/Settings/larpingapp_register.json` to v2.0.0: `name` (required), `description`, `status` (enum active|archived, default active); drop the vestigial `value` property; keep slug `setting`
- [ ] 1.2 Add the optional `setting` property (string, UUID of a setting object) to the `character`, `event`, `skill`, `item`, `condition`, `ability`, and `effect` schemas (NOT `player`); bump their schema versions
- [ ] 1.3 Add `docs/Schema/Setting.json` mirroring the v2 shape; validate the register JSON stays well-formed and re-imports cleanly (register-config-json auto-import + re-import endpoint)

## 2. Setting management UI

- [ ] 2.1 Add `Settings` index page (typed `index` primitive, objectType `setting`, Globe icon, listed with the content types) and `SettingDetail` detail page to `src/manifest.json` + menu entry; keep visual distance from the "Game Settings" config page (icon, ordering, detail title "Setting")
- [ ] 2.2 Create/edit modal for settings (name required, description, status) in `src/modals/` (one file per modal — modal-isolation gate); delete with confirmation
- [ ] 2.3 SettingDetail overview: scoped characters and events listed, mechanics types as counts/tabs (server-side filtered queries on `setting = <uuid>`)
- [ ] 2.4 Deletion guard: block delete while scoped entities exist; confirmation dialog lists per-type counts and suggests reassign/archive

## 3. Active-setting lens

- [ ] 3.1 Setting switcher in the app navigation (all `active` settings + "All settings"); persist the choice per user via the preferences API (REQ-PREF-001/002, key `activeSetting`); fall back to "All settings" when the persisted setting is archived/deleted
- [ ] 3.2 Thread the lens through `useObjectStore` list fetches as an OpenRegister object filter (`setting = <uuid>` OR unset) — single chokepoint, never client-side slicing; detail pages, deep links, and relations tabs stay unfiltered
- [ ] 3.3 Dashboard widgets (KPI counts, recent lists, skill-usage chart) MUST consume the same filter
- [ ] 3.4 Add-Skill/Item/Condition/Event modals: picker default-filtered to active setting + shared; "show all settings" toggle; foreign-setting selections flagged with the setting name (no server-side veto)

## 4. Quality

- [ ] 4.1 Annotate new/changed methods with `@spec openspec/changes/setting-management/...` (gate-16) and SPDX headers (gate-1); NcSelect pickers carry `inputLabel` (gate-12)
- [ ] 4.2 i18n: English source keys for all new strings + nl translations (ADR-007/ADR-025)
- [ ] 4.3 Bump `appinfo/info.xml` `<version>` (immutable-cache bust)

## 5. Tests

- [ ] 5.1 PHPUnit: register-config import of the v2 setting schema + scoping properties (schema shape assertions)
- [ ] 5.2 Newman (`tests/integration/*.postman_collection.json`): setting CRUD via the OR objects API; list filter `setting=<uuid>` returns scoped + shared entities only; stranded-setting-reference entity still fetchable
- [ ] 5.3 Playwright `tests/e2e/spec-coverage/`: create setting, switcher filters the Characters index, persistence across reload, picker default-filter + cross-setting flag, dashboard counts under a lens, deletion guard + archive flow (UI scenarios; pure-API scenarios get `@e2e exclude` on their own line)
- [ ] 5.4 `composer check:strict` green; run hydra gates (incl. gate-16, gate-19, gate-22 manifest validation) — fix any pre-existing issues encountered in touched files

## 6. Spec sync

- [ ] 6.1 On archive, sync the `setting-management` capability spec into `openspec/specs/`; optionally add the `setting` property rows to the character-management / events-players / rpg-system schema tables (additive)
- [ ] 6.2 Update `docs/FEATURES.md` and README so "manage your setting" points at the real feature

## Acceptance criteria

- A GM can create, edit, archive, and (when empty) delete settings from a dedicated Settings index/detail UI.
- Characters, events, and mechanics can be scoped to a setting; unscoped entities behave as shared; existing data needs no migration.
- The per-user active-setting switcher filters every index page and dashboard widget server-side, persists via the preferences API, and never blocks deep links or detail pages.
- Assignment pickers default to the active setting + shared, with flagged (not blocked) cross-setting picks.
- A populated setting cannot be deleted from the UI; archiving hides it from the switcher while keeping data readable.
- All new strings ship in en + nl; PHPUnit, Newman, and Playwright coverage as in section 5; `composer check:strict` and hydra gates pass.
