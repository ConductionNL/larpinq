---
status: in-progress
---

# Skill Tree Visualization — Delta

**Spec refs**: ADR-022 (apps consume OR abstractions; no re-derivation), ADR-012 (use `@conduction/nextcloud-vue` components — no bespoke equivalents), ADR-036 (kind-agnostic slot/registry resolver). Adjacent: `skill-requirement-enforcement` (reuses `CharactersController::requirementReport`), `larping-skill-widget` (distinct usage donut), `setting-management` (setting scoping).

## ADDED Requirements

### Requirement: A skill-tree page MUST render the prerequisite graph from skill data

The app MUST provide a skill-tree page that renders the skill prerequisite graph
where nodes are `skill` objects and directed edges are `requiredSkills`
prerequisites. The graph MUST derive purely from OpenRegister `skill` objects and
MUST NOT read from any app-local prerequisite store (ADR-022). Self-referencing
skills and prerequisite cycles MUST be tolerated — the page MUST render them
without an infinite loop or crash.

#### Scenario: the tree renders prerequisite edges

- **GIVEN** skills "Healing Lvl 1", "Healing Lvl 2" (requiredSkills = ["Healing Lvl 1"]) and "Swordsmanship" exist
- **WHEN** a user opens the skill-tree page
- **THEN** a node MUST appear for each of the three skills
- **AND** a directed edge MUST connect "Healing Lvl 1" → "Healing Lvl 2"
- **AND** "Swordsmanship" MUST render as an unconnected root node
- `@e2e exclude` pending implementation — graph-render e2e authored alongside the apply-phase page

#### Scenario: a prerequisite cycle does not crash the page

- **GIVEN** two skills that reference each other via `requiredSkills` (a cycle)
- **WHEN** the skill-tree page renders
- **THEN** both nodes MUST render with the mutual edges
- **AND** the page MUST NOT hang or throw
- `@e2e exclude` pending implementation — cycle-tolerance e2e authored alongside the apply-phase page

### Requirement: Nodes MUST be coloured by character availability using the server report

When a character is selected, each skill node MUST be coloured by its
availability state — `owned`, `available`, or `locked` — derived from the
existing `CharactersController::requirementReport` result for that character. The
page MUST NOT re-derive prerequisites or the XP budget on the client; it MUST
reuse the server-authoritative report so the tree and the write-time veto in
`skill-requirement-enforcement` can never disagree.

#### Scenario: nodes reflect owned / available / locked state

- **GIVEN** a character who owns "Healing Lvl 1", has the XP and stats for "Healing Lvl 2", and lacks the prerequisite for "Master Healing"
- **WHEN** the character is selected on the skill-tree page
- **THEN** "Healing Lvl 1" MUST render as `owned`
- **AND** "Healing Lvl 2" MUST render as `available`
- **AND** "Master Healing" MUST render as `locked`
- `@e2e exclude` pending implementation — availability-colouring e2e authored alongside the apply-phase page

#### Scenario: availability comes from the server report, not client re-derivation

- **GIVEN** a selected character on the skill-tree page
- **WHEN** the page computes each node's availability state
- **THEN** it MUST source the state from the `requirementReport` response
- **AND** it MUST NOT contain a parallel client-side implementation of prerequisite or XP-budget evaluation
- `@e2e exclude` code-review assertion — the "no re-derivation" invariant is verified by reading the component, not by a browser e2e

### Requirement: Each node MUST surface its full requirement set

Selecting a skill node MUST surface that skill's full requirement set —
`requiredSkills`, `requiredStats` together with `requiredScore`,
`requiredConditions`, and `requiredEffects` — so a player can see why a skill is
locked, not merely that it is. Requirement targets that resolve to a named object
MUST be shown by name rather than by raw UUID.

#### Scenario: a locked node explains its requirements

- **GIVEN** "Master Healing" requires skill "Healing Lvl 2" and ability "Wisdom" ≥ 5
- **WHEN** a player selects the "Master Healing" node
- **THEN** the detail MUST list the required skill "Healing Lvl 2" by name
- **AND** the detail MUST list the required ability "Wisdom" with the required score 5
- `@e2e exclude` pending implementation — node-detail e2e authored alongside the apply-phase page

### Requirement: The tree MUST scope to the active setting

When a setting (world / campaign) is active, the skill-tree page MUST scope its
nodes to skills whose `setting` matches the active setting, consistent with the
`setting-management` model. When no setting is active, the tree MUST show all
skills.

#### Scenario: switching setting filters the tree

- **GIVEN** skills belonging to setting "Grimdark" and skills belonging to setting "Highfantasy"
- **WHEN** the user activates the "Grimdark" setting and opens the skill-tree page
- **THEN** only skills whose `setting` is "Grimdark" MUST appear as nodes
- `@e2e exclude` pending implementation — setting-scoping e2e authored alongside the apply-phase page

### Requirement: The page MUST show an empty state and degrade without erroring

The skill-tree page MUST render an empty state when no skills exist, and MUST
degrade without throwing when OpenRegister or the requirement-report endpoint is
unavailable — the graph is then shown without availability colouring rather than
breaking the page, mirroring the existing widget / leaf degradation pattern.

#### Scenario: no skills yields an empty state

- **GIVEN** an OpenRegister deployment with no `skill` objects
- **WHEN** the skill-tree page opens
- **THEN** it MUST render an empty state
- **AND** it MUST NOT throw
- `@e2e exclude` pending implementation — empty-state e2e authored alongside the apply-phase page

#### Scenario: report endpoint unavailable degrades to an uncoloured tree

- **GIVEN** skills exist but the requirement-report endpoint is unavailable
- **WHEN** a character is selected on the skill-tree page
- **THEN** the graph MUST still render the nodes and edges
- **AND** availability colouring MUST be omitted rather than the page erroring
- `@e2e exclude` pending implementation — degradation e2e authored alongside the apply-phase page
