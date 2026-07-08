---
kind: code
---

# Proposal: skill-tree-visualization

## Why

LarpingApp already carries a complete skill-prerequisite model and enforces it
server-side, but a player has no way to *see* it. The `skill` schema at HEAD
declares `requiredSkills[]` (prerequisite skills), `requiredStats[]` +
`requiredScore` (ability gates), `requiredConditions[]` and `requiredEffects[]`.
`SkillRequirementService` + `CharacterRequirementListener` enforce all of it on
every character write, and `CharactersController::requirementReport` already
returns a structured per-skill status report (passed / unmet / overridden /
unresolvable) plus the XP budget for a given character. What is missing is the
*visualization*: there is no page that renders the prerequisite graph, so a
player cannot answer "what can I learn next, and what is blocking the skill I
want?" without reading raw UUID arrays.

The existing skill surfaces do not cover this. `larping-skill-widget` is a
dashboard donut of skill-usage *popularity* across characters (a distribution
chart), not a prerequisite graph. `skill-requirement-enforcement` is a
server-side veto with an on-demand report, not a rendered tree. The graph and
the availability data already exist server-side — only the view is absent.

Market signal (Specter intelligence DB, app_id=10): `skill-ruleset` (158,
Rules & Skills) and `character-builder` (113, Characters) are the relevant
demand clusters; a visual skill/progression tree is a standard expectation of
character-builder and VTT-adjacent tools (Fantasy Grounds, Demiplane,
Larpmanager). Tender relevance is 0 — hobby-market feature completeness, not
procurement.

## What Changes

- **A skill-tree page.** A new manifest page renders the skill prerequisite
  graph: nodes are `skill` objects, directed edges are `requiredSkills`
  prerequisites. The graph derives purely from OpenRegister `skill` objects — no
  bespoke prerequisite store (ADR-022). Self-references and cycles are tolerated
  (rendered without an infinite loop / crash).
- **Character-context availability colouring.** When a character is selected, each
  node is coloured by state — `owned` / `available` / `locked` — using the
  existing `CharactersController::requirementReport` result. The page MUST NOT
  re-derive prerequisites or the XP budget client-side; it reuses the
  server-authoritative report so the tree and the write-time veto can never
  disagree.
- **Full requirement detail per node.** Selecting a node surfaces that skill's
  full requirement set — `requiredSkills`, `requiredStats` + `requiredScore`,
  `requiredConditions`, `requiredEffects` — so a player sees *why* a skill is
  locked, not merely that it is.
- **Setting scoping.** Skills carry a `setting` (world/campaign). When a setting
  is active, the tree scopes to skills of that setting, consistent with the
  `setting-management` model.
- **nc-vue + graceful degradation.** The page is built from
  `@conduction/nextcloud-vue` primitives (ADR-012), shows an empty state when no
  skills exist, and degrades without erroring when OpenRegister or the report
  endpoint is unavailable — mirroring the existing widget/leaf degradation.

## Impact

- Affected specs: `skill-tree-visualization` (new capability). Adjacent
  (referenced, not modified): `skill-requirement-enforcement` (the availability
  colouring reuses its `requirementReport`), `larping-skill-widget` (distinct —
  usage donut vs prerequisite graph), `setting-management` (setting scoping),
  `rpg-system` (skill/effect model).
- Affected code (apply phase, NOT here):
  - `src/manifest.json` — a new skill-tree page + menu entry.
  - `src/registry.js` — the skill-tree view component (ADR-036 registry entry).
  - A view component under `src/views/` rendering the graph from OR `skill`
    objects + the requirement report (read-only; no new endpoint required —
    `requirementReport` already exists).
  - `l10n/` nl + en strings; `appinfo/info.xml` version bump (cache-bust).
- Depends on: `CharactersController::requirementReport` (shipped) and the OR
  objects API for `skill` (shipped). No schema change, no new backend write path.
- Out of scope: editing prerequisites from the tree (skills are still edited on
  the skill detail page); a spend/purchase action from the tree (buying a skill
  stays the character-edit write path guarded by
  `skill-requirement-enforcement`); cross-setting merged trees.
