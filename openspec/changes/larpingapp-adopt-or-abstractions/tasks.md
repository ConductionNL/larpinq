# Tasks — larpingapp-adopt-or-abstractions

> Spec-only change. No PR / merge / archive tasks here.

## Phase 1 — Manifest pilot (Tier 2)

- [ ] 1.1 Add `src/manifest.json` with:
  - `$schema` set to the published nc-vue app-manifest schema URL
  - `version: "0.1.0"`
  - `dependencies: ["openregister"]`
  - top-level `menu` entries for: Characters, Events, Items, Skills,
    Abilities, Conditions, Effects, Templates, Players, Settings
  - `pages` for each entity:
    - `{entity}-index` — `type: "index"`, route `/{entity}`,
      `config.{register, schema, columns}` populated from
      `docs/Schema/{entity}.json`
    - `{entity}-detail` — `type: "detail"`, route `/{entity}/:id`,
      `config.{register, schema}` populated likewise
  - `pages.character-detail` declares an `actionsComponent: "PdfExportAction"`
    slot override for the existing PDF export button
  - `pages.settings` — `type: "custom"`, route `/settings`,
    `component: "SettingsPage"` (data-source switcher stays bespoke)
- [ ] 1.2 Add `npm run check:manifest` script to `package.json`
  invoking the nc-vue validator.
- [ ] 1.3 Wire `useAppManifest('larpingapp', bundled)` in
  `src/main.js` after the existing pinia setup, before router mount.
- [ ] 1.4 Wire `npm run check:manifest` into the existing CI lint
  job.
- [ ] 1.5 Verify `useAppStatus('openregister')` returns `installed:
  true, enabled: true` under the dev docker-compose; otherwise
  document how to enable OR for local LarpingApp dev.

## Phase 2 — `RegisterResolverService` consumption

- [ ] 2.1 Inject `OCA\OpenRegister\Service\RegisterResolverService`
  into `lib/Service/RegisterObjectFetcher.php` constructor.
- [ ] 2.2 Replace the body of
  `RegisterObjectFetcher::resolveRegisterAndSchema()`
  (`lib/Service/RegisterObjectFetcher.php:100-127`) — currently two
  `$this->config->getValueString(...)` calls — with one
  `$this->resolver->resolveForObjectType($objectType)` call
  returning a typed `(registerId, schemaId)` tuple.
- [ ] 2.3 In `lib/Service/SettingsService.php`, audit every
  `getValueString` call:
  - `lib/Service/SettingsService.php:83` and `:139` — list each
    key in tasks.md (subtask 2.3.1)
  - keys that follow the `{type}_register` / `{type}_schema`
    naming convention MUST migrate to `RegisterResolverService`
  - keys that are not register/schema pairs (e.g. tunable defaults,
    feature flags) MUST stay on `IAppConfig` directly
- [ ] 2.3.1 Inventory of `getValueString` calls (subtask):
  - file:line
  - key name
  - register/schema pair? (yes / no)
  - migrate? (yes / no)
- [ ] 2.4 Run `composer check:strict` — fix any pre-existing
  PHPCS / PHPMD / Psalm / PHPStan warnings touched by the edits.
- [ ] 2.5 Add unit test
  `tests/Unit/Service/RegisterObjectFetcherTest.php` mocking the
  resolver and asserting the legacy `getValueString` path is no
  longer reached.

## Phase 3 — i18n wiring (downstream of OR ADR-025)

- [ ] 3.1 Centralise OR fetch URL building in a single `or-client.js`
  composable at `src/composables/orClient.js` exposing
  `fetchObject({register, schema, uuid, lang})` and
  `patchObject({register, schema, uuid, body, targetLang})`.
- [ ] 3.2 The composable MUST automatically set `?_lang={user
  locale BCP-47}` from `OC.getLocale()` on every fetch.
- [ ] 3.3 The composable MUST automatically set
  `X-Translation-Target-Language: {target}` on writes when the
  caller passes `targetLang` (e.g. when editing a non-default
  language variant explicitly).
- [ ] 3.4 Migrate `src/store/character.js`, `src/store/event.js`,
  `src/store/item.js` (and any other Pinia stores that fetch OR
  objects) onto the composable.
- [ ] 3.5 In `CharacterIndex.vue`, `EventIndex.vue`, `ItemIndex.vue`
  display lists: when an object's served language differs from its
  `sourceLanguage` metadata (per ADR-025), show a small
  "(translated from {lang})" badge using the canonical nc-vue
  badge style.
- [ ] 3.6 Add Cypress / Playwright e2e: switch user locale to
  `de_DE`, open a character with `sourceLanguage: "en"`, assert
  the served name is German and the badge reads "(translated from
  English)".

## Phase 4 — Multi-tenancy wiring (gated on nc-vue release)

- [ ] 4.1 Add `package.json` peer constraint requiring nc-vue at
  the version that exports `useTenantContext`. Until released,
  guard the import with try/catch.
- [ ] 4.2 In `src/views/character/CharacterIndex.vue` (and Event,
  Item indexes): import `useTenantContext()`, watch
  `activeOrganisationUuid`, on change call store
  `clearAllSubResources()` and refetch.
- [ ] 4.3 In each detail view, watch `activeOrganisationUuid` and
  navigate back to the index if the active organisation changes
  while a detail is open (since the object may not be visible in
  the new tenant).
- [ ] 4.4 In `src/composables/orClient.js`, add an option to
  stamp `X-OpenRegister-Organisation` on writes. Default ON when
  `useTenantContext().activeOrganisationUuid` is non-null.
- [ ] 4.5 e2e: switch tenants, assert character list refetches and
  excludes characters from the previous tenant.

## Phase 5 — Manifest Tier 3 graduation (follow-up tracking)

- [ ] 5.1 Track in this tasks.md (no code in this change) the
  prerequisites for Tier 3:
  - `type: "index"` and `type: "detail"` page-type contracts stable
    in nc-vue
  - LarpingApp's bespoke `actionsComponent` slot for PDF export
    documented and reusable
  - Pinia stores compatible with the manifest-driven
    `CnPageRenderer` data fetching contract
- [ ] 5.2 Open a follow-up opsx change `larpingapp-manifest-tier-3`
  once Phase 5 prerequisites are met.

## Phase 6 — Documentation

- [ ] 6.1 Update or create `docs/architecture.md`:
  - manifest as declarative source of truth for routes / menu
  - `RegisterResolverService` consumption pattern
  - i18n flow with sourceLanguage display
  - multi-tenancy wiring
- [ ] 6.2 Update `docs/features/character-management.md` with
  screenshots of the new "(translated from)" badge.
- [ ] 6.3 Cross-link new docs from app's README.

## Phase 7 — Verification

- [ ] 7.1 `composer check:strict` passes.
- [ ] 7.2 `npm run lint` passes.
- [ ] 7.3 `npm run check:manifest` passes.
- [ ] 7.4 PHPUnit unit tests for `RegisterObjectFetcher`
  resolver-injection pass (per CLAUDE.md, run inside the Nextcloud
  container: `docker exec -w /var/www/html/custom_apps/larpingapp
  nextcloud php vendor/bin/phpunit -c phpunit-unit.xml`).
- [ ] 7.5 e2e tests for i18n badge and tenant-switch refetch pass.
- [ ] 7.6 Manual smoke: enable OR + LarpingApp on a clean dev
  Nextcloud, create a character, edit it in two languages, switch
  tenants, confirm UX matches the spec.
