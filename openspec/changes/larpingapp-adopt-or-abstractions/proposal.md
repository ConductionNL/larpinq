# LarpingApp — adopt OR abstractions (manifest, register-resolver, multi-tenancy)

## Why

The 2026-05-03 OR-abstraction audit (`.claude/audit-2026-05-03/`)
identified three adoption gaps in LarpingApp that map cleanly onto
already-merged OR / nc-vue / hydra specs:

1. **No architectural manifest** — LarpingApp wires its router by
   hand and has no `src/manifest.json`. Per the migration order in
   **ADR-024** (`hydra/openspec/architecture/`), LarpingApp is in the
   second-wave cohort (small, schema-driven) — adopt after MyDash
   (the pilot), before the larger apps.
2. **`getValueString(...register/schema...)` consolidation** —
   `lib/Service/RegisterObjectFetcher.php:116-127` resolves register
   and schema IDs from `IAppConfig::getValueString` per-call. The new
   `RegisterResolverService` from
   `openregister/openspec/changes/register-resolver-service/` exists
   precisely to consolidate this pattern. LarpingApp has fewer call
   sites than opencatalogi or pipelinq but the duplication is the
   same shape.
3. **No multi-tenancy wiring** — characters, events, and items are
   scoped by group / event in domain logic but the frontend has no
   `useTenantContext()` wiring. When the `multi-tenancy-context`
   change in nc-vue ships in a versioned release, LarpingApp adopts
   it for cache invalidation and tenant-scoped fetches.

## What Changes

### Manifest adoption (Tier 2 → Tier 3)

- Add `src/manifest.json` with:
  - top-level menu: Characters, Events, Items, Skills, Abilities,
    Conditions, Effects, Templates, Players, Settings
  - per-entity `index` pages (`type: "index"`, columns from
    `docs/Schema/{entity}.json`)
  - per-entity `detail` pages (`type: "detail"`, route
    `/{entity}/:id`)
  - PDF export action declared on the character detail page via
    `actionsComponent` slot override
  - Settings page as `type: "custom"` (data-source switcher between
    internal mappers and OR is not declarative yet)
- Set `dependencies: ["openregister"]` (LarpingApp's ADR-001 already
  requires OR — making it explicit in the manifest closes the loop).
- Tier 2 first: bundle the manifest, validate at build time, but
  keep vue-router hand-wired. Tier 3 (nav rendered from the
  manifest) tracked as a follow-up phase in `tasks.md`.

### `RegisterResolverService` consumption

- Replace `IAppConfig::getValueString` calls inside
  `RegisterObjectFetcher::resolveRegisterAndSchema()` with the new
  `RegisterResolverService` from OR. The service returns
  `(registerId, schemaId)` from a single typed call given an
  `objectType` string.
- Same migration in `lib/Service/SettingsService.php` for any
  `getValueString` calls that resolve register/schema pairs (vs
  generic non-OR config keys, which stay direct).

### Multi-tenancy wiring (when nc-vue ships)

- Adopt `useTenantContext()` from nc-vue in:
  - `src/views/character/CharacterIndex.vue` and detail
  - `src/views/event/EventIndex.vue` and detail
  - `src/views/item/ItemIndex.vue` and detail
- Stamp `X-OpenRegister-Organisation` on writes via the existing
  Pinia store actions.
- Refetch character / event / item lists on tenant switch.

### i18n wiring (downstream of OR ADR-025)

- Pass `?_lang=` on all OR fetches (currently relies on
  `Accept-Language` only).
- Pass `X-Translation-Target-Language` header on PATCH/PUT writes
  when a user is editing a translatable property in a non-default
  language.
- Read `sourceLanguage` metadata when displaying translated content
  in lists (e.g. character names) and surface a small "(translated
  from {lang})" affordance when the served language ≠ source.

## Problem

LarpingApp is a smaller schema-driven app. It already follows
ADR-001 (data in OR, no custom tables) and ADR-012 (nc-vue
components only). Yet:

- It still wires routes by hand, so adding a new entity type means
  editing `src/router/index.js` AND `src/navigation/...` AND
  `src/views/{type}/...`. The manifest collapses the first two into
  declarative config.
- It still resolves register/schema IDs in two places
  (`RegisterObjectFetcher`, `SettingsService`) with the same call
  shape. A future second site is a near certainty.
- It has no consistent runtime story for tenant-scoped views or
  language-negotiation. Editing a German-source character name from
  a Dutch UI silently overwrites the German source under the
  register's default language.

The cohort solution exists in already-merged specs. LarpingApp
adopts them; no new abstractions invented here.

## Proposed Solution

A single `larpingapp-adopt-or-abstractions` change with five
phases (see `tasks.md`):

1. Manifest at Tier 2.
2. `RegisterResolverService` consumption.
3. i18n wiring (`?_lang=`, `X-Translation-Target-Language`,
   `sourceLanguage` display).
4. Multi-tenancy wiring (gated on nc-vue release).
5. Manifest Tier 3 graduation (follow-up tracking).

Each phase is independently shippable. If nc-vue's
`useTenantContext()` slips, phases 1-3 still ship.

## Out of Scope

- Replacing the dual-mode data layer (internal mappers vs OR). The
  per-type configuration that `RegisterObjectFetcher` resolves is
  the seam between the two; keeping both modes is required by
  existing customer deployments.
- Migrating PDF export off mPDF. PDF is rendered server-side from
  Twig templates and is unaffected by manifest adoption.
- Stat calculation engine refactors. `CharacterService` is unchanged.
- Adding new entity types. Only existing entity types
  (ability, character, condition, effect, event, item, player,
  setting, skill, template) are modelled in the new manifest.

## See also

- `openregister/openspec/changes/register-resolver-service/` — the
  service this change consumes. Cite as the canonical contract.
- `openregister/openspec/changes/pluggable-integration-registry/`
  (ADR-019) — not consumed in this change but cited because future
  LarpingApp integrations (e.g. Discord notifications, character
  sheets pushed to a tabletop simulator) MAY register as integration
  providers.
- `openregister/openspec/changes/i18n-source-of-truth/` (ADR-025) —
  schema-level `sourceLanguage`. LarpingApp characters / events /
  items have translatable narrative properties; this change reads
  the metadata.
- `openregister/openspec/changes/i18n-api-language-negotiation/`
  (ADR-025) — `?_lang=` and `X-Translation-Target-Language`. This
  change wires both into LarpingApp's OR fetches and writes.
- `nextcloud-vue/openspec/changes/multi-tenancy-context/` —
  `useTenantContext()` composable. This change adopts it.
- `hydra/openspec/changes/adopt-app-manifest/` — fleet-wide manifest
  convention (ADR-024). LarpingApp is second-wave.
- ADR-001 — All data in OR.
- ADR-012 — nc-vue components only.
- ADR-022 — Apps consume OR abstractions.
- ADR-024 — App manifest fleet-wide adoption.
- ADR-025 — i18n source-of-truth + API language negotiation.
- `.claude/audit-2026-05-03/` — source audit (R4, R5, R6
  research files referenced).
