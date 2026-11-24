---
description: Start a new OpenSpec change for larpinq
---

Start an OpenSpec change named from the arguments (kebab-case, named by what it does): $ARGUMENTS

1. Read `openspec/config.yaml` for the project context and rules, check `openspec/specs/` for the capability you are changing, and check `openspec/changes/` so the name is not taken.
2. Create `openspec/changes/<change-name>/` with the files the existing changes have (compare `openspec/changes/worlds-maps/`):
   - `proposal.md`: summary and motivation.
   - `design.md`: only when there are design decisions to record.
   - `tasks.md`: numbered checkbox tasks, each with how to verify it.
   - `screens.md`: the screens involved.
   - `specs/<capability>/spec.md`: delta spec with `## ADDED Requirements` (or `## MODIFIED Requirements`), `### Requirement: ...` with SHALL statements, and `#### Scenario:` blocks.
3. New data types go in a fragment `lib/Settings/register.d/<change-name>.json`; new pages go in `src/manifest.d/<change-name>.json`. Validate with `npm run check:specs`.
4. Read the ADRs in `openspec/architecture/` before deciding how the change stores data or integrates with other apps.

Do not implement code until the proposal is agreed.
