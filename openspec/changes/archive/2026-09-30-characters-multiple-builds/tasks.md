# Tasks: characters-multiple-builds

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Data and check

- [x] 1.1 `lib/Settings/register.d/characters-multiple-builds.json`: `characterBuild` with its own read, create, update and delete rules, and owner and world materialised from the character (REQ-CMB-001, REQ-CMB-004). Verified: `tests/unit/Settings/CharacterBuildsFragmentTest.php` over the real merge; `RuleSchemaAuthorizationTest` with OpenRegister's real evaluator; `npm run check:register`; `npm run check:schema-l10n`.
- [x] 1.2 `GET /api/builds/{id}/report` in a new `CharacterBuildsController` (design change 1) with `#[NoAdminRequired]` and 404 for unreadable builds (REQ-CMB-002, REQ-CMB-004). Verified: `tests/unit/Controller/CharacterBuildsControllerTest.php` asserts no write happens and the report equals `validate()` on the composed candidate; hydra gates.
- [x] 1.3 Three seed builds (design seed plus one, ADR-111 rule 1) in `lib/Settings/larpinq_mock_register.json`. Verified: `CharacterBuildsFragmentTest::testTheSeedBuildsValidate` with Opis.

## 2. Pages

- [x] 2.1 `src/manifest.d/characters-multiple-builds.json` with BuildDetail and its Check tab, `src/views/BuildReport.vue` (`kind: 'section'`), and the Builds list on CharacterDetail in `src/manifest.json` (REQ-CMB-001, REQ-CMB-002). Verified: `tests/vitest/characterBuilds.spec.js`; `npm run check:manifest`.
- [x] 2.2 `src/dialogs/ApplyBuildDialog.vue` for game masters (design change 2) (REQ-CMB-003). Verified: vitest for the PATCH and the refusal; Playwright `tests/e2e/workflows/character-builds.workflow.spec.ts` written, not run: no isolated instance.

## 3. Strings and docs

- [x] 3.1 Strings in all 37 shipped locales (REQ-CMB-001). Verified: `npm run test:l10n`, `npm run check:l10n-js`.
- [x] 3.2 `docs/features/character-builds.md`, linked from `docs/features/README.md`. Screenshot not taken: no isolated instance.
