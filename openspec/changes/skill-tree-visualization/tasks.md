# Tasks: skill-tree-visualization

- [ ] T1: Add a skill-tree page to `src/manifest.json` (menu entry + page) and a
  registry entry in `src/registry.js` (ADR-036 `kind`/`component`) pointing at a
  new read-only view under `src/views/`.
- [ ] T2: Implement the view: fetch `skill` objects via the OR objects API
  (manifest data layer), build nodes + directed `requiredSkills` edges, and
  render the graph with `@conduction/nextcloud-vue` primitives (ADR-012). Track
  visited nodes so self-references / cycles render without unbounded recursion.
- [ ] T3: Character-context availability — when a character is selected, call the
  existing `CharactersController::requirementReport` and colour each node
  `owned` / `available` / `locked` from the report's per-requirement `status` +
  `budget`. Do NOT re-implement prerequisite or XP-budget logic client-side.
- [ ] T4: Node detail — on node select, surface the full requirement set
  (`requiredSkills`, `requiredStats` + `requiredScore`, `requiredConditions`,
  `requiredEffects`), resolving requirement targets to names where possible.
- [ ] T5: Setting scoping — filter nodes by the active setting
  (`setting-management`); show all skills when no setting is active.
- [ ] T6: Empty state + degradation — empty state when no skills exist; when the
  report endpoint or OR is unavailable, render the graph uncoloured rather than
  erroring (mirror the widget/leaf degrade pattern).
- [ ] T7: Unit / component tests for graph derivation, cycle tolerance, the
  owned/available/locked mapping from a report fixture, and the degraded path
  (ADR-009; run in the Nextcloud container per config.yaml).
- [ ] T8: i18n — nl + en strings for the page, states and legend (ADR-005); bump
  `appinfo/info.xml` version for cache-bust.
- [ ] T9: Docs — `docs/features/` entry with Playwright screenshots of the tree
  (owned/available/locked colouring, node detail) (ADR-010).
- [ ] T10: Run `openspec validate skill-tree-visualization --strict` until clean.

## Acceptance criteria

- A skill-tree page renders the prerequisite graph purely from OR `skill`
  objects, tolerating self-references and cycles without crashing.
- With a character selected, nodes are coloured `owned` / `available` / `locked`
  from `requirementReport`, with no client-side re-derivation of prerequisites or
  the XP budget.
- Node detail surfaces the full requirement set by name; the tree scopes to the
  active setting; empty and report-unavailable states degrade without error.
- No schema change and no new backend endpoint are introduced.
- `openspec validate skill-tree-visualization --strict` passes.
