# Tasks — player-character-sheet-access

## 1. Backend

- [x] 1.1 `lib/Controller/CharactersController.php::downloadPdf`: reorder the flow to fetch the character BEFORE the authorization decision (keep the unauthenticated 401 and the DocuDesk 424 + template-UUID 400 checks in their current positions relative to each other)
- [x] 1.2 Add a private `canAccessCharacter(array $character, IUser $user): bool` helper: true when `IGroupManager::isAdmin($uid)`, OR `IGroupManager::isInGroup($uid, 'gamemasters')` (same group id as `EventsController::GM_GROUP`), OR `(string) ($character['ownerUid'] ?? '') !== ''` and it equals `$user->getUID()`
- [x] 1.3 Return 403 `{"error": "Access denied"}` when `canAccessCharacter` is false — never 404, so non-owners cannot probe character-id existence (preserves the #205 contract)
- [x] 1.4 Add a private `redactGmPrivateFields(array $character): array` helper that unsets `slNotesPrivate`; apply it to the render context when the caller is authorized by ownership only (not admin, not GM)
- [x] 1.5 Delete the stale "requires the character schema to gain a `player` ownership field — tracked as a follow-up" comments (constructor docblock ~line 63-66, method docblock ~line 93-98, inline ~line 142-145) and replace with a short note that access is admin / GM-group / `ownerUid` owner
- [x] 1.6 `@spec` annotations on the new/changed methods pointing at `openspec/changes/player-character-sheet-access/specs/pdf-export/spec.md` (gate-16); SPDX/licence docblock untouched (gate-1)

## 2. Tests

- [x] 2.1 Extend `tests/unit/Controller/CharactersControllerTest.php` (or the existing CharactersController test class): owner with matching `ownerUid` gets 200 and the DocuDesk render context contains NO `slNotesPrivate` key
- [x] 2.2 Non-owner, non-GM, non-admin authenticated user gets 403 even for an existing character id
- [x] 2.3 `gamemasters` member (non-admin) gets 200 and the render context KEEPS `slNotesPrivate`
- [x] 2.4 Admin path unchanged: 200 with full context (regression)
- [x] 2.5 Character with empty/absent `ownerUid`: non-admin non-GM caller gets 403 (empty owner never matches)
- [x] 2.6 Playwright `tests/e2e/spec-coverage/` back-reference for the new scenarios, or `@e2e exclude` with reason where a live DocuDesk env is required (gate-19). Done: `tests/e2e/spec-coverage/player-character-sheet-access.spec.ts` (fresh accounts with an empty storage state: owner, another player, a non-admin `gamemasters` member). The owner, non-owner 403, GM and unowned-character tests skip without DocuDesk, because the DocuDesk check answers 424 before the access check. The "existing and missing id answer the same" and anonymous-refused tests run on every instance. The two render-context scenarios are `@e2e exclude` (not observable in the PDF; covered by `CharactersControllerTest`). The file lints and lists, but has not been run against a live instance here.

## 3. Spec sync

- [x] 3.1 On archive, fold the delta into `openspec/specs/pdf-export/spec.md`: replace the "Non-admin user downloads PDF" scenario under Route and Security Configuration with the owner-scoped scenarios and add the access-matrix + redaction requirements
  - Done on archive (20 Nov): `openspec/specs/pdf-export/spec.md` goes from 11 to 13 requirements; Route and Security Configuration carries the matrix wording and the owner scenario.
- [x] 3.2 Update `docs/FEATURES.md` (players can download their own character sheet)

## 4. Quality

- [x] 4.1 `composer check` green (php -l, phpcs, PHPUnit); hydra gates green on the diff — the changed route must keep passing gate-5 (route-auth), gate-7 (no-admin-idor: the per-object guard is now the owner/GM/admin matrix), gate-9 (semantic-auth)
  - Measured on development (round 2): `composer lint` passes, `composer phpcs` passes (0 errors), and the unit suite is green (1236 tests, 20 skipped; `CharactersControllerTest` 16 OK). Psalm is clean on `CharactersController.php`. Whole-app `composer psalm` fails on files this change does not touch (`HelpMessageListener`, `PreWriteEvent`, `LarpinqScannableServices`: OCP and OpenRegister symbols), so `composer check` as a whole is not green. `downloadPdf` keeps `#[NoAdminRequired]` + `#[NoCSRFRequired]` and its per-object owner/GM/admin guard, checked by hand; the hydra gate script is not in this repository.
  - Measured 20 Nov on development after #1130, with OpenRegister main beside the app: `composer check` prints ALL CHECKS PASSED (lint; phpcs 0 errors; psalm no errors; test:unit 1404 tests OK). hydra gates (ConductionNL/.github hydra-gates, `--full`): gate-5 route-auth PASS, gate-9 semantic-auth PASS; gate-7 no-admin-idor fails on 8 methods in PreferencesController and RegistrationChangesController, and `CharactersController::downloadPdf` is not among them.
