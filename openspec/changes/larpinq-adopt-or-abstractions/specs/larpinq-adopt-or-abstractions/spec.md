---
status: draft
---

# LarpingApp — adopt OR abstractions

## Purpose

Specify the requirements for LarpingApp's adoption of:

1. The fleet-wide app-manifest contract from
   `@conduction/nextcloud-vue` (per ADR-024 and
   `hydra/openspec/changes/adopt-app-manifest/`).
2. OpenRegister's `RegisterResolverService` for register / schema
   ID resolution (per
   `openregister/openspec/changes/register-resolver-service/`).
3. OpenRegister's i18n source-of-truth + API language-negotiation
   conventions (per ADR-025 and the two i18n changes under
   `openregister/openspec/changes/`).
4. nc-vue's `useTenantContext()` composable for multi-tenancy
   awareness (per
   `nextcloud-vue/openspec/changes/multi-tenancy-context/`).

## ADDED Requirements

### Requirement: LarpingApp MUST ship an architectural manifest at `src/manifest.json`

LarpingApp MUST add `src/manifest.json` conforming to the JSON
Schema published by `@conduction/nextcloud-vue` at
`src/schemas/app-manifest.schema.json`. The manifest MUST be loaded
via `useAppManifest('larpingapp', bundledManifest)` in `src/main.js`.

The manifest MUST set:
- `$schema` to the published nc-vue schema URL
- `version` to a semver string
- `dependencies: ["openregister"]` (LarpingApp depends on OR per
  ADR-001)
- a `menu` array including all 10 entity / settings entries
- a `pages` array including index and detail pages for each entity
  type

#### Scenario: Manifest loads on app boot

- GIVEN LarpingApp is installed and OR is enabled
- WHEN a user navigates to `/index.php/apps/larpingapp`
- THEN `useAppManifest('larpingapp', bundledManifest)` MUST be
  called before vue-router mounts
- AND on async-fetch of `/index.php/apps/larpingapp/api/manifest`
  the loader MUST silently fall back to bundled on non-200

#### Scenario: Manifest validation fails build

- GIVEN a developer commits `src/manifest.json` with a missing
  `pages[].id` field
- WHEN `npm run check:manifest` runs
- THEN it MUST exit non-zero
- AND CI MUST fail

#### Scenario: Manifest declares OR dependency

- GIVEN `src/manifest.json`
- WHEN reading `manifest.dependencies`
- THEN it MUST contain the string `"openregister"`
- AND `CnAppRoot` (when adopted at Tier 4) MUST render
  `CnDependencyMissing` if OR is disabled at runtime

### Requirement: LarpingApp MUST consume `RegisterResolverService` for register / schema resolution

`RegisterObjectFetcher::resolveRegisterAndSchema()` MUST delegate to
`OCA\OpenRegister\Service\RegisterResolverService::resolveForObjectType()`
instead of calling `IAppConfig::getValueString` directly.

`SettingsService` MUST migrate every `getValueString` call that
follows the `{type}_register` / `{type}_schema` naming convention
to the resolver. Other `getValueString` calls (feature flags,
tunables) MUST stay on `IAppConfig`.

#### Scenario: Resolver returns typed pair

- GIVEN `RegisterResolverService::resolveForObjectType('character')`
- AND `IAppConfig` has `character_register = "5"` and
  `character_schema = "12"`
- WHEN the method is called
- THEN it MUST return an object with
  `registerId === 5` and `schemaId === 12`
- AND the returned values MUST be type-narrowed (int, not string)

#### Scenario: RegisterObjectFetcher uses resolver

- GIVEN `RegisterObjectFetcher::resolveRegisterAndSchema('character')`
- WHEN the method is called
- THEN it MUST invoke `$this->resolver->resolveForObjectType('character')`
- AND MUST NOT call `$this->config->getValueString(...)` for
  the `_register` / `_schema` suffixes

#### Scenario: Resolver fallback when OR feature flag indicates absence

- GIVEN OR is installed but the
  `RegisterResolverService` class is not yet present (during
  upgrade window)
- WHEN `RegisterObjectFetcher::resolveRegisterAndSchema()` is
  called
- THEN the code MUST detect resolver absence via DI null-check
- AND MUST fall back to the legacy `getValueString` path
- AND MUST log a deprecation warning to the Nextcloud log

### Requirement: LarpingApp OR fetches MUST pass `?_lang={user locale}`

All OR object fetches (read paths) issued from LarpingApp's
frontend MUST include the `?_lang={BCP47}` query parameter set to
the user's Nextcloud locale (region tag stripped).

This is a downstream consumer of
`openregister/openspec/changes/i18n-api-language-negotiation/`.

#### Scenario: Lang stamping on character fetch

- GIVEN the user's Nextcloud locale is `de_DE`
- WHEN `useOrClient().fetchObject({register: 5, schema: 12, uuid: 'abc'})`
  is called
- THEN the request URL MUST be
  `/index.php/apps/openregister/api/objects/5/12/abc?_lang=de`
- AND the `Accept-Language` header MUST also be set (default browser
  behaviour) but MUST NOT be the sole signal

#### Scenario: Locale region tag stripped

- GIVEN `OC.getLocale()` returns `en_GB`
- WHEN `orClient.js` builds the URL
- THEN the `_lang` parameter MUST be `en` (not `en_GB`)
- AND MUST NOT cause OR to 4xx on an unknown region tag

### Requirement: LarpingApp OR writes MUST stamp `X-Translation-Target-Language` when editing a non-default language

When a user is editing a translatable property in a non-default
language (e.g. editing the German translation of a character's
name from a German UI, while the register's default language is
Dutch), the PATCH/PUT request MUST include the
`X-Translation-Target-Language: {target}` header.

#### Scenario: German edit on Dutch-default register

- GIVEN a character with translatable property `name` and
  `sourceLanguage: "nl"`
- AND the user's UI is in German
- AND the user edits the German variant of `name` to
  `Drachenschlinge`
- WHEN the PATCH is issued
- THEN the request body MUST be `{ "name": "Drachenschlinge" }`
- AND the request MUST include header
  `X-Translation-Target-Language: de`
- AND OR MUST wrap the value as `{ name: { de: "Drachenschlinge" } }`
  rather than overwriting the Dutch source

#### Scenario: Default-language edit omits header

- GIVEN a character with `sourceLanguage: "nl"` and the user is in
  Dutch
- WHEN the PATCH is issued
- THEN the request MUST NOT include
  `X-Translation-Target-Language` header
- AND OR MUST treat the body as the source-language update

### Requirement: LarpingApp lists MUST display "(translated from {lang})" badge when served language differs from source

When an OR-backed list view (CharacterIndex, EventIndex, ItemIndex)
renders an object whose served language differs from its
`sourceLanguage` metadata, the row MUST show a small
"(translated from {sourceLanguage})" badge next to the primary
display field.

The badge MUST use the canonical nc-vue badge style (no bespoke
CSS).

#### Scenario: Badge on translated row

- GIVEN a character with `sourceLanguage: "en"` and a Dutch
  translation
- AND the user's locale is `nl_NL`
- WHEN the character appears in `CharacterIndex.vue`
- THEN the row MUST show the Dutch name
- AND a badge MUST appear next to the name with text
  `(translated from English)` (i18n-keyed)

#### Scenario: No badge when served language matches source

- GIVEN a character with `sourceLanguage: "nl"` and the user's
  locale is `nl_NL`
- WHEN the character appears in `CharacterIndex.vue`
- THEN the row MUST NOT show the translated-from badge

#### Scenario: No badge for internal-mapper objects

- GIVEN a character stored via the internal mapper data path (not
  OR)
- WHEN the row renders
- THEN no `sourceLanguage` metadata is available
- AND no badge MUST be rendered
- AND no warning MUST be logged

### Requirement: LarpingApp MUST consume `useTenantContext()` from nc-vue when surfacing tenant-scoped OR data

Once `useTenantContext()` is exported from a versioned nc-vue
release, LarpingApp views that surface OR data (CharacterIndex,
EventIndex, ItemIndex, plus their detail views) MUST adopt the
composable.

#### Scenario: Tenant switch clears caches and refetches

- GIVEN the user is viewing CharacterIndex in tenant A
- AND the index has 12 cached characters
- WHEN the user switches to tenant B via the nc-vue tenant switcher
- THEN `useTenantContext().activeOrganisationUuid` MUST update to
  B's UUID
- AND the Pinia character store MUST clear its collection cache
- AND a fresh fetch MUST issue with B's session
- AND the rendered list MUST contain only characters scoped to B

#### Scenario: Tenant switch on detail view navigates back

- GIVEN the user is viewing a character detail in tenant A
- WHEN the user switches to tenant B
- THEN the detail view MUST navigate back to CharacterIndex (since
  the character may not be visible in B)
- AND the index MUST refetch with B's session

#### Scenario: Pre-release fallback

- GIVEN nc-vue's exported version does not yet include
  `useTenantContext`
- WHEN LarpingApp imports it (try/catch guarded)
- THEN absence MUST NOT crash the app
- AND views MUST behave as single-tenant (no refetch on switch)

### Requirement: LarpingApp PHP code MUST pass `composer check:strict`

Per project policy, all LarpingApp PHP files MUST pass
`composer check:strict` (PHPCS, PHPMD, Psalm, PHPStan). This change
MUST NOT introduce new warnings, and SHOULD fix any pre-existing
warnings in the files it touches (`RegisterObjectFetcher.php`,
`SettingsService.php`).

#### Scenario: Strict check passes

- GIVEN the change is applied
- WHEN `composer check:strict` runs in the LarpingApp container
- THEN the exit code MUST be 0
- AND no new warnings or errors MUST appear in the output

### Requirement: LarpingApp PHPUnit tests MUST run inside the Nextcloud container

Per `feedback_phpunit-must-run-in-container.md` and project
CLAUDE.md, unit tests MUST be invoked via:

```
docker exec -w /var/www/html/custom_apps/larpingapp nextcloud \
  php vendor/bin/phpunit -c phpunit-unit.xml
```

NOT via host-side `composer test:unit`.

#### Scenario: Container test invocation

- GIVEN the developer wants to run unit tests
- WHEN they invoke the container command above
- THEN `RegisterObjectFetcherTest` MUST run
- AND assert that the resolver-injection path is taken when the
  resolver service is bound
- AND assert that the legacy fallback path is taken when the
  resolver service is null
