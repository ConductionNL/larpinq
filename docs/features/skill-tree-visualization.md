# Skill Tree Visualization

## Overview

The skill-tree page renders Larpinq's skill prerequisite graph so a player can
answer "what can I learn next, and what is blocking the skill I want?" without
reading raw UUID arrays. Nodes are `skill` objects; directed edges are the
`requiredSkills` prerequisites. The whole graph derives purely from OpenRegister
`skill` objects — there is no app-local prerequisite store (ADR-022).

This is a **frontend-only, read-only** view: no schema change and no new backend
endpoint. Availability colouring reuses the existing
`CharactersController::requirementReport`, so the tree and the write-time veto
(`skill-requirement-enforcement`) can never disagree.

## How to Use

1. Open **Mechanics → Skill tree**.
2. (Optional) Pick a **Character** to colour nodes by availability, and a
   **Setting** to scope the tree to one world.
3. Click a node to see its full requirement set (required skills, abilities +
   score, conditions, effects), resolved to names.

## Behaviour

- **Nodes + edges.** One node per skill; each node lists its prerequisite skills
  by name (the incoming directed edge). Skills with no prerequisites render as
  root nodes. Nodes are arranged into dependency tiers (roots first).
- **Cycle-tolerant.** Self-references and prerequisite cycles never hang or crash
  — tier assignment is bounded by the node count, and any cyclic nodes fall into
  a final tier.
- **Availability colouring (server-authoritative).** With a character selected,
  each node is coloured `owned` / `available` / `locked` from the
  `requirementReport` result plus owned-skill membership. The page does **not**
  re-derive prerequisites or the XP budget client-side.
- **Setting scoping.** When a setting is active the tree shows only skills of
  that setting; otherwise it shows all skills.
- **Graceful degradation.** An empty state shows when no skills exist. When
  OpenRegister or the requirement report is unavailable the graph still renders
  its nodes and edges, uncoloured, rather than erroring.

## Known limitation (endpoint scope)

The shipped `requirementReport(id)` endpoint is **diff-based** — it reports
requirement entries only for skills being *added* relative to the character's
persisted skill set, so for the persisted character it returns an empty
`requirements[]` and only the XP `budget`. The tree therefore colours `owned`
from membership and `available`/`locked` from whatever the report classifies,
leaving unclassified skills uncoloured. Full available/locked colouring for every
unowned skill would need a per-skill (candidate) report — an endpoint change that
is deliberately out of scope for this frontend-only change (ADR-022: no new
OR-duplicating read path). The "no client re-derivation" invariant is preserved.

## Related

- `skill-requirement-enforcement` — the availability colouring reuses its
  `requirementReport`; the tree is the read-only visualization of the same model.
- `larping-skill-widget` — distinct: a usage-popularity donut, not a prerequisite
  graph.
- `setting-management` — provides the setting scoping.
