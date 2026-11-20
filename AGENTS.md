# AGENTS.md

Larpinq is a Nextcloud app for LARP (live-action role-play) organisations: settings (worlds), characters, players, game mechanics with a stat-calculation engine, events, XP awards and printable character sheets. It is built on Nextcloud and OpenRegister: a PHP backend in `lib/` and a Vue 2 frontend in `src/` (see `README.md` and `appinfo/info.xml`).

## Setup

- Install dependencies: `composer install` and `npm ci`. CI installs with Node 24 (npm 11); `package.json` declares `"npm": "^11.0.0"`.
- Evaluation stack (Nextcloud with larpinq and OpenRegister, PostgreSQL and Redis): `docker compose -f docker/docker-compose.yml up -d --build`, then open http://localhost:8080/apps/larpinq/ as `admin` / `admin`. Remove it with `docker compose -f docker/docker-compose.yml down -v`. Settings are listed at the top of `docker/docker-compose.yml`. It is for trying the app out, not for development.
- Development runs the app inside a Nextcloud with OpenRegister. The README's Development section starts that environment from the OpenRegister repository (`docker compose -f openregister/docker-compose.yml up -d`, a file in that repository, not in this one).

## Build

- `npm run build`: production frontend build (webpack). Its `prebuild` step runs `scripts/assert-lockfile-matches-install.js`.
- `npm run dev` / `npm run watch`: development build, once or in watch mode.
- `npm run l10n:build`: regenerate `l10n/*.js` from the translation sources; `npm run check:l10n-js` checks they are up to date.

## Test

- PHP unit tests: `composer test:unit` (PHPUnit, `phpunit.xml`, suites `tests/unit` and `tests/integration`).
- Frontend unit tests: `npm run test:unit` (Vitest, `vitest.config.js`, specs in `tests/vitest/`).
- End-to-end: `npm run test:e2e` (Playwright, `playwright.config.ts`, specs in `tests/e2e/`). It needs a running Nextcloud: set `PLAYWRIGHT_BASE_URL` to an isolated instance (for example `http://localhost:8094`); there is deliberately no `localhost:8080` default. Install the browser once with `npm run test:e2e:install`.
- Translations: `npm run test:l10n` checks every used key is in `l10n/en.json` and that locales are at parity.
- Specs and config: `npm run check:specs` validates strict JSON, `src/manifest.json` and the register files (CI job `spec-validation.yml`).

## Lint and quality

- `composer check:strict`: the unified PHP gate (lint, phpcs, phpmd, psalm, phpstan, all PHPUnit tests). `composer check` is the shorter set (lint, phpcs, psalm, unit tests).
- Single PHP tools: `composer phpcs` (fix with `composer phpcs:fix`), `composer cs:check` (fix with `composer cs:fix`), `composer phpmd`, `composer psalm`, `composer phpstan`.
- Frontend: `npm run lint` (ESLint over `src`, `tests`, `scripts`; the required `lint-check` CI job) and `npm run stylelint`.

## OpenSpec flow

Features are specified in `openspec/` (`openspec/config.yaml` holds the project context and rules).

1. A change starts in `openspec/changes/<change-name>/` with `proposal.md`, `tasks.md`, `screens.md`, an optional `design.md`, and delta specs in `specs/<capability>/spec.md` (`## ADDED Requirements`, `### Requirement: ...`, `#### Scenario:` blocks).
2. When it is done, its requirements are merged into the living specs in `openspec/specs/<capability>/spec.md`.
3. The change folder moves to `openspec/changes/archive/<YYYY-MM-DD>-<change-name>/`.

Code links back to its spec with `@spec` docblock tags pointing at the spec file and requirement.

## Architecture decisions

Architecture decisions are recorded as numbered ADRs in `openspec/architecture/` (for example `adr-001-integrate-dont-build.md`). Read them before changing how the app stores data, integrates with other apps or authorizes users.

## Conventions

- Pages are manifest configuration: the frontend's pages, menu, setup wizard and dashboard are declared in `src/manifest.json`, with per-change fragments in `src/manifest.d/*.json` merged on at load (`src/main.js`). Validate with `npm run check:manifest`.
- Data types are OpenRegister register configuration, not PHP entity classes: `lib/Settings/larpinq_register.json`, with per-change fragments in `lib/Settings/register.d/*.json` (see `lib/Settings/register.d/README.md`). Validate with `npm run check:register`.
- Consume OpenRegister abstractions and integration apps instead of building parallel mechanisms (`openspec/architecture/adr-001-integrate-dont-build.md`).
- Service interfaces live in `lib/Kernel/Contracts/`.
- PHP classes carry a file docblock (category, package, author, copyright, license, link) and SPDX headers, use `declare(strict_types=1);` and typed signatures; `.cursor/rules/coding-standards.mdc` lists the full rules.
- Product documentation is the Docusaurus site in `docs/`.

## Commits

Use Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `refactor:`), present tense and imperative mood, first line at most 72 characters (`CONTRIBUTING.md`). Branch from `development` and open pull requests against it.
