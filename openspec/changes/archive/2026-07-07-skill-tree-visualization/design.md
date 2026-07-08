# Design: skill-tree-visualization

## Context

The prerequisite model and its enforcement already exist server-side:

- `skill.requiredSkills[]` / `requiredStats[]` + `requiredScore` /
  `requiredConditions[]` / `requiredEffects[]` (data model).
- `SkillRequirementService::validate()` + `CharacterRequirementListener`
  (write-time veto).
- `CharactersController::requirementReport(id)` (read-only structured status per
  skill + XP budget, already routed).

Only the visualization is missing. This change is therefore a **frontend-only,
read-only** addition: a page that draws the graph and colours it from the report.
No schema change, no new backend endpoint.

## Graph derivation

- **Nodes** = OR `skill` objects (fetched via the OR objects API through the
  manifest data layer).
- **Edges** = `skill.requiredSkills` (directed prerequisite → dependent).
- Cycles / self-references are tolerated by tracking visited nodes during layout;
  the renderer never recurses unbounded.

## Availability colouring (reuse, never re-derive)

When a character is selected the page calls the existing `requirementReport`
endpoint and maps each skill to a state:

- `owned` — the skill id is in the character's `skills[]`.
- `available` — not owned, and the report shows all of the skill's requirements
  `passed` with the XP budget `ok`.
- `locked` — not owned, and the report shows at least one `unmet` / `unresolvable`
  requirement (or the budget cannot cover it).

The mapping consumes the report's per-requirement `status` and `budget` blocks —
it does not re-implement prerequisite or XP-budget logic. This guarantees the
tree and the write-time veto (`skill-requirement-enforcement`) agree by
construction.

## Setting scoping

Skills carry `setting`. The page filters nodes by the active setting (from the
`setting-management` selection) and shows all skills when none is active. This
keeps large multi-world registers legible.

## Rendering

Built from `@conduction/nextcloud-vue` primitives (ADR-012) and wired through the
ADR-036 `src/registry.js` as a manifest page component. Graph layout uses a
dependency the shared nc-vue library already exposes where possible; a bespoke
charting/graph dependency is avoided per the shared-deps rule (charts come from
nc-vue). Empty state and an uncoloured-degraded state mirror the existing
`larping-skill-widget` and leaf degradation patterns.

## Alternatives considered

- **Extend `larping-skill-widget`** — rejected: that widget is a usage-popularity
  donut; a prerequisite graph is a different data shape and a full page, not a
  dashboard tile.
- **New backend endpoint for the graph** — rejected: unnecessary. Skills come
  from the OR objects API and availability from the existing `requirementReport`;
  adding an endpoint would duplicate OR-delegated reads (ADR-022).
- **Editable tree (drag prerequisites)** — out of scope: prerequisites are edited
  on the skill detail page; the tree stays a read-only planning view.
