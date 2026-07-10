# Tasks — player-character-sheet-access

## 1. Backend

- [ ] 1.1 `lib/Controller/CharactersController.php::downloadPdf`: reorder the flow to fetch the character BEFORE the authorization decision (keep the unauthenticated 401 and the DocuDesk 424 + template-UUID 400 checks in their current positions relative to each other)
- [ ] 1.2 Add a private `canAccessCharacter(array $character, IUser $user): bool` helper: true when `IGroupManager::isAdmin($uid)`, OR `IGroupManager::isInGroup($uid, 'gamemasters')` (same group id as `EventsController::GM_GROUP`), OR `(string) ($character['ownerUid'] ?? '') !== ''` and it equals `$user->getUID()`
- [ ] 1.3 Return 403 `{"error": "Access denied"}` when `canAccessCharacter` is false — never 404, so non-owners cannot probe character-id existence (preserves the #205 contract)
- [ ] 1.4 Add a private `redactGmPrivateFields(array $character): array` helper that unsets `slNotesPrivate`; apply it to the render context when the caller is authorized by ownership only (not admin, not GM)
- [ ] 1.5 Delete the stale "requires the character schema to gain a `player` ownership field — tracked as a follow-up" comments (constructor docblock ~line 63-66, method docblock ~line 93-98, inline ~line 142-145) and replace with a short note that access is admin / GM-group / `ownerUid` owner
- [ ] 1.6 `@spec` annotations on the new/changed methods pointing at `openspec/changes/player-character-sheet-access/specs/pdf-export/spec.md` (gate-16); SPDX/licence docblock untouched (gate-1)

## 2. Tests

- [ ] 2.1 Extend `tests/unit/Controller/CharactersControllerTest.php` (or the existing CharactersController test class): owner with matching `ownerUid` gets 200 and the DocuDesk render context contains NO `slNotesPrivate` key
- [ ] 2.2 Non-owner, non-GM, non-admin authenticated user gets 403 even for an existing character id
- [ ] 2.3 `gamemasters` member (non-admin) gets 200 and the render context KEEPS `slNotesPrivate`
- [ ] 2.4 Admin path unchanged: 200 with full context (regression)
- [ ] 2.5 Character with empty/absent `ownerUid`: non-admin non-GM caller gets 403 (empty owner never matches)
- [ ] 2.6 Playwright `tests/e2e/spec-coverage/` back-reference for the new scenarios, or `@e2e exclude` with reason where a live DocuDesk env is required (gate-19)

## 3. Spec sync

- [ ] 3.1 On archive, fold the delta into `openspec/specs/pdf-export/spec.md`: replace the "Non-admin user downloads PDF" scenario under Route and Security Configuration with the owner-scoped scenarios and add the access-matrix + redaction requirements
- [ ] 3.2 Update `docs/FEATURES.md` (players can download their own character sheet)

## 4. Quality

- [ ] 4.1 `composer check` green (php -l, phpcs, PHPUnit); hydra gates green on the diff — the changed route must keep passing gate-5 (route-auth), gate-7 (no-admin-idor: the per-object guard is now the owner/GM/admin matrix), gate-9 (semantic-auth)
