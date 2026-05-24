# Coverage Report — larpingapp

Generated: 2026-05-24 00:00 UTC
Branch: fix/l10n-and-polyfill-overrides
Scanner: opsx-coverage-scan v1

## Summary

| Bucket | Count | Next action |
|---|---|---|
| annotated | 0 | — (already tagged) |
| plumbing | 15 | — (never tagged) |
| 1 — REQ matched | 122 | `/opsx-annotate larpingapp` |
| 2a — existing capability, no REQ | 0 (0 clusters) | — |
| 2b — no capability owner | 18 (1 cluster) | `/opsx-reverse-spec larpingapp --cluster db-entities-and-mappers-legacy-fallback` |
| 3a — REQ broken (code removed) | 2 | Already documented in spec as `status: Dead Code` — no action |
| 3b — REQ never implemented | 3 | Frontend-only or numbering gap — verify |
| 4 — ADR conformance | 18 findings across 3 rules | Roll into the annotation ghost change |

**REQ inventory totals**: 487 across 13 specs (470 table-format + 17 narrative-style in `larping-skill-widget`). Of these, roughly 340 describe frontend-only behavior (Vue, JS, CSS, navigation) and are out of scope for this PHP-only scan. The 122 Bucket 1 matches cover essentially every PHP REQ in the specs.

## Bucket 1 — Ready to annotate (via ghost change `retrofit-2026-05-24-annotate-larpingapp`)

### capability: deep-link-registration (12 matches → task-1)

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/AppInfo/Application.php | register | DEEP-001 | 0.95 | registers DeepLinkRegistrationListener for the OR event |
| lib/AppInfo/Application.php | register | DEEP-004 | 0.95 | class_exists guard binds to OR event class |
| lib/AppInfo/Application.php | register | DEEP-005 | 0.92 | graceful no-op when OR absent |
| lib/Listener/DeepLinkRegistrationListener.php | handle | DEEP-002 | 0.98 | iterates DEEP_LINK_MAP (8 slugs) |
| lib/Listener/DeepLinkRegistrationListener.php | handle | DEEP-003 | 0.98 | URL template `/apps/larpingapp/#/{type}/{uuid}` |
| lib/Listener/DeepLinkRegistrationListener.php | handle | DEEP-004 | 0.92 | `method_exists($event,'registerDeepLink')` guard |
| lib/Listener/DeepLinkRegistrationListener.php | handle | DEEP-006..013 | 0.98 | one DEEP_LINK_MAP entry per object type (character, player, ability, skill, item, condition, effect, event) |

### capability: register-config-json (12 matches → task-2)

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/AppInfo/Application.php | boot | REG-004 | 0.95 | calls SettingsService::loadSettings() |
| lib/AppInfo/Application.php | boot | REG-006 | 0.90 | try/catch wraps for OR-not-installed |
| lib/Controller/SettingsController.php | getConfigurationService | REG-005 | 0.78 | resolves OR ConfigurationService via container (NEEDS-REVIEW: duplicate of SettingsLoadService resolver) |
| lib/Controller/SettingsController.php | reimport | REG-012 | 0.98 | POST /api/settings/reimport handler |
| lib/Service/SettingsService.php | loadSettings | REG-004 | 0.92 | thin delegate to SettingsLoadService |
| lib/Service/SettingsLoadService.php | loadSettings | REG-005 | 0.98 | delegates to ConfigurationService::importFromApp |
| lib/Service/SettingsLoadService.php | updateObjectTypeConfiguration | REG-010 | 0.95 | writes register/schema IDs to IAppConfig |
| lib/Service/SettingsLoadService.php | getConfigurationService | REG-005 | 0.85 | helper (inherits from loadSettings) |
| lib/Service/ConfigFileLoaderService.php | loadConfigurationFile | REG-001 | 0.95 | reads `/lib/Settings/larpingapp_register.json` |
| lib/Service/ConfigFileLoaderService.php | loadConfigurationFile | REG-007 | 0.98 | loads + json_decodes |
| lib/Service/ConfigFileLoaderService.php | loadConfigurationFile | REG-008 | 0.98 | throws RuntimeException on missing/invalid file |
| lib/Service/ConfigFileLoaderService.php | ensureSourceType | REG-009 | 0.98 | sets `x-openregister.sourceType='local'` |
| lib/Service/SettingsMapBuilder.php | buildSchemaSlugMap | REG-011 | 0.98 | slug→ID map from import schemas |
| lib/Service/SettingsMapBuilder.php | findRegisterIdBySlug | REG-011 | 0.92 | finds larpingapp register id |
| lib/Service/SettingsMapBuilder.php | addSchemaToMap | REG-011 | 0.85 | helper |
| lib/Service/SettingsMapBuilder.php | extractRegisterIdIfMatch | REG-011 | 0.85 | helper |
| lib/Service/SettingsMapBuilder.php | normalizeToArray | REG-011 | 0.80 | helper |

### capability: admin-settings (19 matches → task-3)

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Sections/LarpingAppAdmin.php | getIcon | SET-002 | 0.95 | returns `app-dark.svg` |
| lib/Sections/LarpingAppAdmin.php | getID | SET-001 | 0.85 | section id `larpingapp` |
| lib/Sections/LarpingAppAdmin.php | getName | SET-001 | 0.80 | localized name |
| lib/Sections/LarpingAppAdmin.php | getPriority | SET-005 | 0.98 | returns 55 |
| lib/Settings/LarpingAppAdmin.php | getForm | SET-003 | 0.82 | ISettings impl (NEEDS-REVIEW: spec marks SET-003 as Bug but code is the fix) |
| lib/Settings/LarpingAppAdmin.php | getForm | SET-004 | 0.82 | renders settings/admin template |
| lib/Settings/LarpingAppAdmin.php | getSection | SET-001 | 0.80 | ties to `larpingapp` section |
| lib/Settings/LarpingAppAdmin.php | getPriority | SET-005 | 0.70 | NEEDS-REVIEW: returns 10 (within-section), SET-005 wants 55 (cross-section) — different axis |
| lib/Controller/SettingsController.php | index | SET-040 | 0.95 | GET /api/settings |
| lib/Controller/SettingsController.php | index | SET-042 | 0.95 | response includes objectTypes, openRegisters, isAdmin, configuration |
| lib/Controller/SettingsController.php | index | SET-020 | 0.92 | `getInstalledApps()` check |
| lib/Controller/SettingsController.php | index | SET-043 | 0.90 | configuration via settingsService->getSettings() |
| lib/Controller/SettingsController.php | create | SET-041 | 0.95 | POST /api/settings |
| lib/Controller/SettingsController.php | reimport | SET-053 | 0.95 | reimport handler |
| lib/Controller/SettingsController.php | getObjectService | SET-020 | 0.78 | NEEDS-REVIEW: OR-installed check helper |
| lib/Service/SettingsService.php | getSettings | SET-043 | 0.95 | reads CONFIG_KEYS via appConfig |
| lib/Service/SettingsService.php | updateSettings | SET-041 | 0.90 | persists CONFIG_KEYS |
| lib/Service/SettingsService.php | getConfigValue | SET-043 | 0.72 | NEEDS-REVIEW: partial REQ coverage |
| lib/Service/SettingsService.php | setConfigValue | SET-030 | 0.72 | NEEDS-REVIEW: partial REQ coverage |
| lib/Service/SettingsLoadService.php | loadSettings | SET-060 | 0.92 | loads bundled JSON |
| lib/Service/SettingsLoadService.php | loadSettings | SET-061 | 0.90 | importFromApp creates registers/schemas |
| lib/Service/SettingsLoadService.php | loadSettings | SET-063 | 0.92 | force flag through |
| lib/Service/SettingsLoadService.php | updateObjectTypeConfiguration | SET-062 | 0.92 | persists IDs in IAppConfig |

### capability: object-service (25 matches → task-4)

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Service/RegisterObjectFetcher.php | getOpenRegisterService | OBJ-010..014 | 0.97 | OR install check, container resolution, caching, throw |
| lib/Service/RegisterObjectFetcher.php | getMapper | OBJ-001 | 0.98 | reads `{type}_register` via getValueString |
| lib/Service/RegisterObjectFetcher.php | getMapper | OBJ-002 | 0.98 | reads `{type}_schema` |
| lib/Service/RegisterObjectFetcher.php | getMapper | OBJ-003 | 0.98 | `strtolower($objectType)` |
| lib/Service/RegisterObjectFetcher.php | getMapper | OBJ-004 | 0.97 | `$openRegister->getMapper($register, $schema)` |
| lib/Service/RegisterObjectFetcher.php | getMapper | OBJ-005 | 0.97 | throws when register empty |
| lib/Service/RegisterObjectFetcher.php | getMapper | OBJ-006 | 0.97 | throws when schema empty |
| lib/Service/RegisterObjectFetcher.php | getMapper | OBJ-007 | 0.95 | appName hardcoded to 'larpingapp' |
| lib/Service/RegisterObjectFetcher.php | getObjects | OBJ-020 | 0.97 | signature matches |
| lib/Service/RegisterObjectFetcher.php | getObjects | OBJ-021 | 0.95 | passes through to findAll |
| lib/Service/RegisterObjectFetcher.php | getObjects | OBJ-022 | 0.95 | array_map toArray |
| lib/Service/RegisterObjectFetcher.php | getObjects | OBJ-024 | 0.92 | default params |
| lib/Service/RegisterObjectFetcher.php | getObject | OBJ-030 | 0.97 | signature |
| lib/Service/RegisterObjectFetcher.php | getObject | OBJ-031 | 0.97 | URI cleaning via explode + end |
| lib/Service/RegisterObjectFetcher.php | getObject | OBJ-032 | 0.97 | FILTER_VALIDATE_URL guard |
| lib/Service/RegisterObjectFetcher.php | getObject | OBJ-033 | 0.95 | mapper->find |
| lib/Service/RegisterObjectFetcher.php | getObject | OBJ-034 | 0.95 | toArray result |
| lib/Service/RegisterObjectFetcher.php | toArray | OBJ-023 | 0.98 | combined dispatch |
| lib/Service/RegisterObjectFetcher.php | toArray | OBJ-040..043 | 0.97 | jsonSerialize / array / cast / mixed param |

### capability: character-management + game-mechanics + events-players + rpg-system stat engine (18 matches → task-5)

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Service/CharacterService.php | calculateAllCharacters | CHAR-070 | 0.97 | retrieves all chars, iterates calculateCharacter |
| lib/Service/CharacterService.php | calculateAllCharacters | CHAR-071 | 0.95 | returns updated array |
| lib/Service/CharacterService.php | calculateAllCharacters | CHAR-073 | 0.97 | uses RegisterObjectFetcher::getObjects('character') |
| lib/Service/CharacterService.php | calculateCharacter | CHAR-010 | 0.92 | single-char stat calc |
| lib/Service/CharacterService.php | calculateCharacter | CHAR-050 | 0.85 | backend stat-calc entry point |
| lib/Service/CharacterService.php | calculateCharacter | CHAR-052 | 0.92 | applies skill/item/condition effects |
| lib/Service/CharacterService.php | calculateCharacter | EVT-020 | 0.95 | applyEntityEffects(property:'events', lookup:allEvents) |
| lib/Service/CharacterService.php | applyEntityEffects | CHAR-052 | 0.90 | inherits from calculateCharacter |
| lib/Service/CharacterService.php | initializeAbilityScores | MECH-007 | 0.97 | base values used to init scores |
| lib/Service/CharacterService.php | initializeAbilityScores | ABIL-009 | 0.97 | starting stat map from base values |
| lib/Service/CharacterService.php | applyEffects | MECH-063 | 0.95 | null effectId skipped |
| lib/Service/CharacterService.php | collectEffectAbilities | MECH-024 | 0.92 | collects abilities[] |
| lib/Service/CharacterService.php | collectEffectAbilities | MECH-025 | 0.92 | appends legacy stat_id |
| lib/Service/CharacterService.php | collectEffectAbilities | MECH-026 | 0.95 | inherits from calculateEffect |
| lib/Service/CharacterService.php | calculateEffect | MECH-026 | 0.97 | calls collectEffectAbilities |
| lib/Service/CharacterService.php | applyModifierToAbility | MECH-027 | 0.98 | positive add / negative subtract |
| lib/Service/CharacterService.php | applyModifierToAbility | MECH-028 | 0.98 | audit entry {type, effect, old, new} |
| lib/Service/CharacterService.php | applyModifierToAbility | MECH-062 | 0.85 | null-id resilience (partial) |
| lib/Service/CharacterService.php | loadAllEntities | CHAR-073 | 0.75 | NEEDS-REVIEW: init helper, weak REQ match |
| lib/Service/CharacterService.php | indexById | CHAR-073 | 0.65 | NEEDS-REVIEW: private helper, no direct REQ match |

### capability: pdf-export (18 matches → task-6)

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Controller/CharactersController.php | downloadPdf | PDF-001 | 0.98 | name, signature, return type |
| lib/Controller/CharactersController.php | downloadPdf | PDF-002 | 0.98 | resolves OCA\DocuDesk\Service\PdfService |
| lib/Controller/CharactersController.php | downloadPdf | PDF-003 | 0.98 | resolves OCA\DocuDesk\Service\TemplateService |
| lib/Controller/CharactersController.php | downloadPdf | PDF-004 | 0.97 | templateService->getTemplate |
| lib/Controller/CharactersController.php | downloadPdf | PDF-005 | 0.97 | pdfService->renderPdf(content, data, options) |
| lib/Controller/CharactersController.php | downloadPdf | PDF-006 | 0.95 | data ctx character+template |
| lib/Controller/CharactersController.php | downloadPdf | PDF-007 | 0.95 | format 'A4', orientation 'P' defaults |
| lib/Controller/CharactersController.php | downloadPdf | PDF-008 | 0.97 | DataDownloadResponse 'application/pdf' |
| lib/Controller/CharactersController.php | downloadPdf | PDF-009 | 0.95 | filename pattern |
| lib/Controller/CharactersController.php | downloadPdf | PDF-020 | 0.97 | isEnabledForUser('docudesk') guard |
| lib/Controller/CharactersController.php | downloadPdf | PDF-021 | 0.98 | 424 JSONResponse when DocuDesk missing |
| lib/Controller/CharactersController.php | downloadPdf | PDF-022 | 0.98 | exact 424 error wording |
| lib/Controller/CharactersController.php | downloadPdf | PDF-030 | 0.97 | character not found → 404 |
| lib/Controller/CharactersController.php | downloadPdf | PDF-031 | 0.97 | template not found → 404 |
| lib/Controller/CharactersController.php | downloadPdf | PDF-032 | 0.97 | PDF gen fail → 500 |
| lib/Controller/CharactersController.php | downloadPdf | PDF-033 | 0.97 | objectFetcher->getObject('character', $id) |
| lib/Controller/CharactersController.php | downloadPdf | PDF-034 | 0.95 | template error → 404 |
| lib/Controller/CharactersController.php | downloadPdf | PDF-035 | 0.98 | return type union |

### capability: dashboard (4 matches → task-7)

| File | Method | REQ | Confidence | Signal |
|---|---|---|---|---|
| lib/Controller/DashboardController.php | page | DASH-001 | 0.98 | TemplateResponse for root route |
| lib/Controller/DashboardController.php | page | DASH-002 | 0.98 | renders 'index' template from 'larpingapp' |
| lib/Controller/DashboardController.php | page | DASH-003 | 0.95 | @NoAdminRequired |
| lib/Controller/DashboardController.php | page | DASH-004 | 0.95 | @NoCSRFRequired |

## Bucket 2a — Existing capability, no REQ

None. Every PHP method that maps to a known capability has a covering REQ in the relevant spec.

## Bucket 2b — No capability owner

### cluster: db-entities-and-mappers-legacy-fallback (18 methods/classes)

This cluster is the entire `lib/Db/` directory — 9 entity classes (Character, Ability, Skill, Item, Condition, Effect, Event, Player, Setting) plus 9 QBMapper classes. They are remnants of the original "internal storage" mode that was superseded by OpenRegister integration. The current migrations (`Version0Date20240826193657`, `Version0Date20241015141612`) are explicit no-op stubs noting "All data is now stored in OpenRegister."

- lib/Db/Character.php — Skeletal Entity (id/name/description, getJsonFields, hydrate, jsonSerialize)
- lib/Db/Ability.php — Skeletal Entity
- lib/Db/Skill.php — Skeletal Entity
- lib/Db/Item.php — Skeletal Entity
- lib/Db/Condition.php — Skeletal Entity
- lib/Db/Effect.php — Skeletal Entity
- lib/Db/Event.php — Skeletal Entity
- lib/Db/Player.php — Skeletal Entity
- lib/Db/Setting.php — Skeletal Entity
- lib/Db/CharacterMapper.php — references `larpingapp_characters` table (NEVER CREATED — `SET-081` documents this as a Bug)
- lib/Db/AbilityMapper.php
- lib/Db/SkillMapper.php
- lib/Db/ItemMapper.php
- lib/Db/ConditionMapper.php
- lib/Db/EffectMapper.php
- lib/Db/EventMapper.php
- lib/Db/PlayerMapper.php
- lib/Db/SettingMapper.php

Decision needed (NOT auto-fix): either delete this cluster (recommended — already documented as dead-code internal-mode fallback) or write a `legacy-internal-storage` spec to officially document them. The existing `larpingapp-legacy-quality-cleanup` change is the natural home for this decision.

## Bucket 3 — Surfaced for human triage

### 3a — possibly broken (already documented as dead code in spec)

- **search-service#SRCH-001..SRCH-010** — removed-lines cache matches `elasticService`, `directoryService`, `catalogi`, `search()`. The SearchService::search() method previously existed and was removed. **Spec already marks every REQ as `status: Dead Code`.** No action needed beyond keeping the spec.
- **search-service#SRCH-020..SRCH-086** — removed-lines cache matches `mergeFacets`, `mergeAggregations`, `createMongoDBSearchFilter`, `createMySQLSearchConditions`, `parseQueryString`, `sortResultArray`, `unsetSpecialQueryParams`, `recursiveRequestQueryKey`. The entire SearchService class was removed. **Spec retains historical REQs with `status: Dead Code` / `status: Bug`.** Consider deleting the spec or moving it under `openspec/specs/_archive/`.

### 3b — never implemented

- **larping-skill-widget — entire spec (17 narrative REQs)** — Frontend-only Vue dashboard widget. No PHP code exists or is expected. The widget mounts in the JS dashboard via ApexCharts; PHP is not involved. NOT a coverage gap.
- **admin-settings#SET-006..SET-009** — Numbering gap in the spec table; no REQs at IDs 6..9. NOT a coverage gap.
- **admin-settings#SET-081** — Documents the `larpingapp_characters` table-never-created bug. The bug is real (CharacterMapper references the table, migrations are now no-ops). No PHP method fix landed; the resolution will come from either deleting Db/CharacterMapper (Bucket 2b decision) or writing a fresh migration.

## Bucket 4 — ADR conformance findings

### missing-spec-in-file-docblock (13 files — ADR-003)

Every active PHP file under lib/ except `lib/Db/` and `lib/Migration/` lacks the `@spec openspec/changes/.../tasks.md#task-N` annotation. This is the entire annotation work the ghost change will perform.

- lib/AppInfo/Application.php
- lib/Controller/SettingsController.php
- lib/Controller/DashboardController.php
- lib/Controller/CharactersController.php
- lib/Service/RegisterObjectFetcher.php
- lib/Service/CharacterService.php
- lib/Service/SettingsService.php
- lib/Service/ConfigFileLoaderService.php
- lib/Service/SettingsLoadService.php
- lib/Service/SettingsMapBuilder.php
- lib/Listener/DeepLinkRegistrationListener.php
- lib/Sections/LarpingAppAdmin.php
- lib/Settings/LarpingAppAdmin.php

### missing-copyright-in-file-docblock (1 file — ADR-014)

- lib/Controller/DashboardController.php — outer file docblock has `@license` but no `@copyright` (class-level docblock has it). One-line fix.

### hardcoded-user-strings (4 files — ADR-007)

Mostly internal RuntimeException / API error strings. The one that matters for user-facing UX is in `CharactersController::downloadPdf`:

- lib/Controller/CharactersController.php — JSON error messages ('Character not found', 'Template not found', 'PDF generation failed: ...', 'PDF generation requires the DocuDesk app...') surface in the frontend as toast notifications. Consider wrapping with `$this->l10n->t(...)`.
- lib/Controller/SettingsController.php — internal RuntimeException + API success/error strings; lower priority.
- lib/Service/RegisterObjectFetcher.php — internal exceptions, OK to leave un-l10n'd.
- lib/Service/ConfigFileLoaderService.php — internal exceptions, OK to leave un-l10n'd.

### forbidden-patterns (0 findings)

Clean — no `var_dump`/`die`/`error_log`/`print_r`/`dd` calls in lib/.

### direct-sql (0 findings)

Clean — no `$this->db->query(`/`prepare(` calls. All data access goes through QBMapper (Db/) or OpenRegister (RegisterObjectFetcher).

## Notes for the human reviewer

1. **Branch:** the scan ran against `fix/l10n-and-polyfill-overrides`. The `openspec/specs/` tree is identical to development per file mtime — no spec drift between branches.
2. **Spec format split:** 12/13 specs use a clean `| ID | Requirement | Priority | Status |` markdown table. The 13th (`larping-skill-widget`) uses narrative `### Requirement: <prose>` headings without IDs — pure frontend, not in scope here.
3. **Frontend-only REQs dominate:** Of the 487 total REQs, the rough split is ~120 backend / ~340 frontend / ~25 dead-code-historical. Only the backend slice can land in Bucket 1 from a PHP scan. The 340 frontend REQs are NOT bucket-3b unimplemented — they live in `src/` and are out of scope for this skill.
4. **Spec status field staleness:** `admin-settings#SET-003` is marked `Bug` in the spec, but the bug is already fixed (`lib/Settings/LarpingAppAdmin.php` implements `ISettings`, not the broken `IIconSection`). Update the spec status to `Implemented`.
5. **SET-005 priority axis:** `lib/Sections/LarpingAppAdmin::getPriority()` correctly returns 55 (cross-section ordering). `lib/Settings/LarpingAppAdmin::getPriority()` returns 10 (within-section ordering — a different axis). REQ wording is ambiguous; the implementation is correct on both axes. NEEDS-REVIEW flag set so a human can clarify the REQ.
6. **lib/Db/ cluster:** all 18 entity+mapper classes are leftover internal-storage fallback. Migrations are no-op stubs. The natural place to decide deletion vs documentation is the in-flight `larpingapp-legacy-quality-cleanup` change.
7. **Search-service spec:** retrospectively documents the removed SearchService. Consider archiving it (`openspec/specs/_archive/search-service/`) so future scans don't keep flipping it into Bucket 3.
8. **In-flight changes:** `larpingapp-adopt-or-abstractions` (Tier-2 manifest pilot, spec-only) and `larpingapp-legacy-quality-cleanup` (also spec-only). The annotation work below should not collide with either.

## Next

1. Read this report manually — confirm Bucket 1 matches before annotating.
2. `/opsx-annotate larpingapp` — creates a ghost change and applies the 122 Bucket 1 annotations in one PR.
3. Decide on `lib/Db/` cluster fate (delete vs document) inside the existing `larpingapp-legacy-quality-cleanup` change — no reverse-spec run needed for that.
4. Optionally archive `search-service` spec (Bucket 3a triage).
