# Tasks: portal-contribution

Tracking issue: Conduction/larpingapp#51 (Wave 3 — provider + tests only; the ownerRef backfill and any XP-award surface are later items on this issue, do not close it). Depends on: portal-identity.

- [x] T1: Verify the full scoping map against the register JSONs at HEAD (`larpingapp_register.json` + `register.d/*.json`) — schemas, the `ownerRef` scoping property (uuid, from portal-identity), and the game-master-only columns to exclude.
  - `character.ownerRef` (uuid, added by portal-identity) is the scope; `ownerUid` is a NC uid and is NEVER used to scope
  - GM-only / internal character columns: `approved`, `slNotesPrivate`, `notice`, `requirementOverrides`, `ownerUid`, `ownerRef`

- [x] T2: Add `lib/Portal/PortalContributionProvider.php` — plain, dependency-free (no portaliq import, no implements, no constructor deps), EUPL-1.2/SPDX docblock matching the repo, `@spec` tags.
  - Class is at the convention FQCN `OCA\LarpingApp\Portal\PortalContributionProvider`
  - No `use` of any portaliq symbol anywhere in the file

- [x] T3: Implement `getAudiences(): array` returning `['player']` and the v1 fallback `getAudience(): string` returning `'player'`.

- [x] T4: Implement `getContribution(array $subject): ?array` — branch on `$subject['audience']` only; return `null` for anything else (fail-closed, no endpoint actions).

- [x] T5: Player read collections: `myCharacters` (`scopeField: ownerRef`, `scopeClaim: ownerRef`, field-projected) + public `events`, `skillCatalog`, `itemCatalog`, `conditionCatalog` (each `scopeField: ''`), catalogs projected to reference columns.
  - character projection drops `approved`, `slNotesPrivate`, `notice`, `requirementOverrides`, `ownerUid`, `ownerRef`
  - `event` drops `players` (roster) + `effects`; `item`/`condition` drop `characters` (ownership)

- [x] T6: Player action: `createCharacter` (`type: create`, `scopeField: ownerRef` so the writer stamps the owner) with fields `name`, `ocName`, `background` only; `notifications: []`; no `inbox` collection.

- [x] T7: Add `tests/unit/Portal/PortalContributionProviderTest.php` — direct construction, nil-UUID subject (design.md Seed Data), pinning the dependency-free shape, audiences, collection shape, field whitelists, create whitelist, fail-closed null, and a register-drift pin (scoping key + whitelist columns exist on their schema at HEAD).

- [x] T8: Create `openspec/changes/portal-contribution/specs/portal-contribution/spec.md` (`status: in-progress`) and run `openspec validate portal-contribution --strict` until valid.

- [x] T9: Run the gate suite the CI way (docker php:8.3-cli): `composer lint`, `composer phpcs`, `composer phpmd`, `composer phpstan`, and the unit suite (`vendor/bin/phpunit -c phpunit-unit.xml`); fix violations in the files this change touches (max 3 cycles, report honestly). Psalm is CI-disabled for this repo.
  - Existing unit tests stay green; no baseline files edited to pass

- [x] T10: Commit on `feat/portal-contribution` (conventional message, no Co-Authored-By); do not push, do not open a PR.

## Acceptance criteria

- The provider is at the convention FQCN, plain, dependency-free, and inert without portaliq (no portaliq symbol in the file, no constructor).
- `getAudiences()` == `['player']`; `getAudience()` == `'player'`; unknown/missing audience → `null`.
- Characters are scoped EXCLUSIVELY by `ownerRef` (never `ownerUid`); the character projection excludes every GM-only / internal column.
- The only action is `createCharacter` with `name`/`ocName`/`background`; every action is `type: create`.
- The register-drift pin passes and the existing unit suite stays green.
