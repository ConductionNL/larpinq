# Tasks: LarpingApp Legacy Quality Cleanup

## Phase 1 — Inventory + planning

- [x] Run `composer phpcs` and capture current baseline error count
      → 0 errors, 21 warnings (all SpecTag warnings, surfaced not failing
      via `ignore_warnings_on_exit`). NOTE: the "4 phpcs.xml exclude-patterns"
      legacy-debt block referenced in the proposal no longer exists — a prior
      change already removed it. Only the standard vendor/node_modules/
      template excludes remain (not legacy debt).
- [x] Run `composer phpmd` for the first time as a unified gate
      → 2 violations, both `MissingImport` (RegisterObjectFetcher.php:266,
      SettingsLoadService.php:183).
- [x] Run `composer phpstan` for the first time as a unified gate
      → 0 errors at level 5 with an empty baseline.
- [x] Decide per gate: fix-outright (if <50 violations) or capture
      a fresh baseline (if larger) → all gates well under 50; fix outright,
      no new baselines captured.
- [x] Confirm CI runs `composer check:strict` on every PR before
      starting burn-down work → `composer.json` already wires phpcs + phpmd +
      psalm + phpstan into `check:strict`; CI runs the shared
      `Conduction/.github` `quality.yml` reusable workflow on every PR.

## Phase 2 — PHPCS burn-down (per excluded file)

- [x] No file-level legacy `<exclude-pattern>` entries exist in phpcs.xml
      (already removed by a prior change). PHPCS reports 0 errors against the
      full `lib/` tree, so there is nothing to burn down here.
- [x] Excluded file 1 — N/A (no legacy exclude present)
- [x] Excluded file 2 — N/A (no legacy exclude present)
- [x] Excluded file 3 — N/A (no legacy exclude present)
- [x] Excluded file 4 — N/A (no legacy exclude present)
- [x] Legacy-debt block already absent from phpcs.xml — no removal needed.

## Phase 3 — PHPMD burn-down

- [x] MissingImport — add `use` statements: added `use InvalidArgumentException;`
      to `RegisterObjectFetcher.php` and `use RuntimeException;` to
      `SettingsLoadService.php`, replacing the fully-qualified inline throws.
      `composer phpmd` now reports 0 violations.
- [x] ElseExpression — none surfaced.
- [x] CyclomaticComplexity / NPathComplexity — none surfaced.
- [x] StaticAccess — none surfaced.
- [x] variable-naming sniffs — none surfaced.
- [x] No `phpmd.baseline.xml` was ever created and none is needed (0
      violations); the `phpmd` composer script has no `--baseline-file`.

## Phase 4 — PHPStan burn-down

- [x] Inventory phpstan errors by file/type → 0 errors at level 5.
- [x] Missing return-type / param-type declarations — none surfaced.
- [x] Mixed types — none surfaced.
- [x] Possibly-null dereferences — none surfaced.
- [x] Gate runs clean against current code. `phpstan-baseline.neon` is kept
      as an empty file (only header + empty `ignoreErrors:`) because
      `phpstan.neon` `includes:` it — this matches the canonical fleet pattern
      (apps without debt ship an empty baseline rather than deleting the file,
      which would break the include).

## Phase 5 — CI integration

- [x] Verify `composer check:strict` runs in CI on every PR → confirmed via
      the shared reusable `quality.yml` workflow.
- [x] Baselines are empty:
  - [x] No `phpmd.baseline.xml` exists (never created).
  - [x] `phpstan-baseline.neon` kept empty (see Phase 4 — deleting breaks the
        canonical `includes:`; the fleet pattern ships it empty).
  - [x] No legacy-debt section in `phpcs.xml` to drop.
- [x] Enabled PHPStan in CI: flipped `enable-phpstan: false` → `true` in
      `.github/workflows/code-quality.yml` now that phpstan passes clean,
      bringing CI in line with the unified `check:strict` gate (phpmd already
      defaults to enabled in the reusable workflow). A separate weekly cron is
      not added — `check:strict` already runs on every push to `development`
      and on every PR, which is stricter than a weekly smoke-test.

## Phase 6 — Documentation

- [x] Update README quality-gates section: documented `composer phpstan`,
      `composer check:strict`, and the empty-baseline / no-exclude burn-down
      state in the Code quality section + Tech Stack table.
- [x] `app-config.json` does not exist in this repo — N/A.
- [~] Close the burn-down tracking issue — deferred to the coordinator;
      issue management is not part of the build (all underlying gates are
      green: phpcs 0e/21w surfaced-only, phpmd 0v, phpstan 0e@L5, no legacy
      excludes; the tracking issue can be closed by the coordinator without
      further code work).
