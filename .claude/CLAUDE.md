# Workflow

## Before Starting Any Task
1. **Ask clarifying questions one at a time** (up to 5, stop early if clear). Wait for each answer.
2. **Present a plan** (approach, files, risks).
3. **Wait for approval** before implementing.

## Spec-Driven Development (OpenSpec)

**Company-wide ADRs and specs** live in `hydra/openspec/` — 12 compact ADRs, shared i18n specs, schemas, and cross-app changes. **Per-app specs** live in `{app}/openspec/` inside each app's repo. Each spec has one canonical home; other apps reference or adopt it from there.

**Skills are distributed across two repos** (both in `additionalDirectories`):
- **hydra** `.claude/skills/` — 50 skills: opsx workflow (opsx-*), OpenSpec vanilla (openspec-*), testing (test-*), team agents (team-*), app implementation (app-apply, app-verify), utilities (create-pr, clean-env, sync-docs)
- **concurrentie-analyse** `.claude/skills/` — 21 skills: app scaffolding (app-create, app-design, app-explore, app-pipeline), research (specter-*, tender-*, ecosystem-*), software catalog (swc-*)

**Docs** live in `.github/docs/claude/`. **Global settings/hooks** live in `.github/global-settings/`.

**Flow:** `/opsx-new` → `/opsx-ff` or `/opsx-continue` → `/opsx-plan-to-issues` → `/opsx-apply` → `/opsx-verify` → `/opsx-archive`

# Project Context

## Conduction Apps

All apps are ConductionNL projects in three categories:

**Core Apps:** openregister, opencatalogi, openconnector, docudesk, nldesign, mydash, softwarecatalog, larpingapp, zaakafhandelapp, procest, pipelinq

**ExApp Sidecar Wrappers:** openklant, opentalk, openzaak, valtimo, n8n-nextcloud

**External:** `Softwarecatalogus/` (VNG client repo, NEVER commit), `nextcloud-vue/` (shared Vue lib)

**PHP apps** must pass `composer check:strict` (PHPCS, PHPMD, Psalm, PHPStan).
**Python ExApps** use `make check-strict` (ruff + mypy).
**Pre-existing issues**: Always fix pre-existing quality issues (PHPCS, PHPMD, PHPStan, test warnings) when encountered during a task — don't leave them for later.
**No scripting for code changes**: Do NOT use sed, awk, Python scripts, or other scripting to modify code files — high probability of breaking code. Always use the Edit tool or manually write complete files.

## Open Register (Foundation Repository)
- Foundation for all Conduction apps — owns shared specs and docker-compose
- UI: localhost:3030 (dev) / localhost:3000 (local env)
- Backend: localhost:80 (dev) / localhost:8080 (local env)

## NL Design System
All apps support `nldesign` for government theming. Use CSS variables (no hardcoded colors), standard NC components, WCAG AA compliance.

## Docker Environment

Use `.github/docker-compose.yml`. Default starts db + nextcloud + n8n.

Profiles: see `.github/docs/development-environment.md`. Reset: `bash clean-env.sh` or `/clean-env` skill.

## Browser Pool (7 Playwright MCP sessions)

- `browser-1`: Main agent default
- `browser-2` to `browser-5`, `browser-7`: Sub-agents / parallel (headless)
- `browser-6`: **Headed** — use when user wants to watch

Rules: default to browser-1; assign browser-2..5,7 to parallel sub-agents; fallback to next number on error; browser-6 only for user watching. All isolated (no persistent state).

## Git Commits
- **Do NOT add `Co-Authored-By` trailers** to commit messages. All commits should appear as authored solely by the user.
