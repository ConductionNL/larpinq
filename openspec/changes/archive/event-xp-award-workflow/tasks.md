# Tasks — event-xp-award-workflow

## 1. Schema + RBAC

- [x] 1.1 Add the `xpAward` schema to `lib/Settings/larpingapp_register.json`: `event` (uuid, required), `character` (uuid, required), `amount` (number, exclusiveMinimum 0, required), `reason` (string, optional), `awardedBy` (string), `awardedAt` (date-time); registered in the register's schema list and `src/store/store.js` SCHEMA_SLUGS
- [x] 1.2 Configure OpenRegister schema-level RBAC on `xpAward`: create/update/delete restricted to the `gamemasters` group via the schema `authorization` block; read open to authenticated app users — no app-local authorization code (ADR-022)
- [~] 1.3 `docs/Schema/XpAward.json` added. Server-side stamping of `awardedBy`/`awardedAt` is DEFERRED: the app has no character/award controller (writes go straight through the OR objects API per ADR-022), so stamping needs an OR pre-write listener — a small follow-up. The schema documents the fields.

## 2. Stat engine

- [x] 2.1 Preload xpAwards into a per-character indexed map in `CharacterService` (`loadXpAwards()`, CHAR-080 pattern)
- [x] 2.2 Apply awards as the fifth stage in `calculateCharacter()` after skills -> items -> conditions -> events: sum each award's amount onto the XP ability with audit entries `{type:"xpAward", award:{...}, old, new}`; existing four-stage arithmetic and audit ordering byte-identical (verified by `testFourStageRegressionUnchangedWhenNoAwards`)
- [x] 2.3 Resolve the XP ability via name-match (`resolveXpAbilityId()`: "xp" / "experience") — shared rule with skill-requirement-enforcement, no hardcoded UUIDs
- [x] 2.4 Graceful skips: award for an unresolvable character ignored; missing XP ability -> no-op; non-numeric amount skipped; never throws (CALC-006)
- [~] 2.5 Recalculation trigger on award create/update/delete: DEFERRED — stats are computed-on-read in this app (`calculateCharacter()` is not wired to persist computed stats anywhere), so there is no persisted stat to "recalculate". Awards are consumed on the next calculation automatically.

## 3. Event detail awarding UI

- [~] 3.1-3.4 GM-only "Award XP" tab + batch modal + inline awards list + XP provenance rendering DEFERRED. This app is a declarative manifest-v2 renderer app with no bespoke event-detail component in `src/`. Instead, a manifest **"XP Awards" index + detail page** (schema `xpAward`) is shipped so GMs create/list/edit/delete awards through the generic object editor today (GM-write enforced by schema RBAC). The event-centric batch tab needs a custom event-detail section component — a nc-vue follow-up. XP provenance is already in the stat audit trail (`type:"xpAward"` entries).

## 4. Quality

- [x] 4.1 `@spec` annotations on new methods (gate-16) + SPDX headers (gate-1); awards are plain OR object CRUD (gate-17)
- [x] 4.2 i18n: the new user-facing strings are the manifest page titles/menu labels ("XP Awards", "XP Award") — English source
- [x] 4.3 Bump `appinfo/info.xml` `<version>` (0.1.26 -> 0.1.27); manifest 0.2.0 -> 0.3.0

## 5. Tests

- [x] 5.1 PHPUnit `CharacterServiceXpAwardTest`: fifth-stage sum, audit shape/order, awards-after-effects, four-stage regression, other-character ignored, no-XP-ability no-op, non-numeric skip, no-id (8 tests)
- [~] 5.2 PHPUnit recalculation-trigger test: N/A — recalc trigger deferred (2.5)
- [~] 5.3 Newman: DEFERRED — needs a live NC+OR env with the `gamemasters` group; engine arithmetic + graceful skips covered by PHPUnit, award CRUD + RBAC is OpenRegister's enforcement (ADR-022)
- [x] 5.4 Playwright `tests/e2e/spec-coverage/event-xp-award-workflow.spec.ts`: XP Awards index page renders its own surface (gate-19 back-reference)
- [x] 5.5 `composer check` (php -l + phpcs 0 errors + PHPUnit 93 green); hydra gates green on the diff; vitest 22 green; `npm run build` green

## 6. Spec sync

- [x] 6.1 On archive, sync the `event-xp-awards` capability spec + rpg-system delta into `openspec/specs/`
- [x] 6.2 `docs/FEATURES.md`: "experience score" now backed by a real awarding workflow

## Acceptance criteria

- A GM can record per-participant XP awards (one auditable `xpAward` per grant). Done via the XP Awards manifest page; event-centric batch tab deferred (nc-vue follow-up).
- Non-GM writes to `xpAward` rejected server-side via OR schema RBAC; players can read provenance. Done (schema `authorization`; reads open).
- `calculateCharacter()` sums awards onto XP as a fifth stage with audit entries; pre-existing CALC unchanged; dangling refs never crash. Done (8 PHPUnit tests).
- Computed XP feeds the skill-requirement-enforcement budget by construction. Done.
- PHPUnit coverage as in section 5; UI batch tab + Newman + recalc-trigger deferred with reasons; `composer check` and hydra gates pass.
