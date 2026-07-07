# Tasks: skill-tree-visualization

- [x] T1: Add a skill-tree page to `src/manifest.json` (menu entry + page) and a
  registry entry in `src/registry.js` (ADR-036 `kind`/`component`) pointing at a
  new read-only view under `src/views/`.
- [x] T2: Implement the view: fetch `skill` objects via the OR objects API
  (manifest data layer), build nodes + directed `requiredSkills` edges, and
  render the graph with `@conduction/nextcloud-vue` primitives (ADR-012). Track
  visited nodes so self-references / cycles render without unbounded recursion.
- [~] T3: Character-context availability — when a character is selected, call the
  existing `CharactersController::requirementReport` and colour each node
  `owned` / `available` / `locked` from the report's per-requirement `status` +
  `budget`. Do NOT re-implement prerequisite or XP-budget logic client-side.
- [x] T4: Node detail — on node select, surface the full requirement set
  (`requiredSkills`, `requiredStats` + `requiredScore`, `requiredConditions`,
  `requiredEffects`), resolving requirement targets to names where possible.
- [x] T5: Setting scoping — filter nodes by the active setting
  (`setting-management`); show all skills when no setting is active.
- [x] T6: Empty state + degradation — empty state when no skills exist; when the
  report endpoint or OR is unavailable, render the graph uncoloured rather than
  erroring (mirror the widget/leaf degrade pattern).
- [~] T7: Unit / component tests for graph derivation, cycle tolerance, the
  owned/available/locked mapping from a report fixture, and the degraded path
  (ADR-009; run in the Nextcloud container per config.yaml).
  NOTE: the pure derivation is extracted to `src/views/skillTreeGraph.js` and
  tested by `tests/vitest/skillTreeGraph.spec.js` (node/edge derivation, cycle +
  self-ref tolerance, the owned/available/locked mapping from a report fixture,
  the degraded report-null path). The vitest env is node-only (no .vue
  transform), so the SFC delegates to this module. The vitest RUNNER is not
  executable in this environment (no node in the deployed container; no
  node_modules in the worktree) — the exact spec logic was verified via a node
  ESM harness (all assertions pass); it runs in CI.
- [x] T8: i18n — nl + en strings for the page, states and legend (ADR-005); bump
  `appinfo/info.xml` version for cache-bust.
- [~] T9: Docs — `docs/features/` entry with Playwright screenshots of the tree
  (owned/available/locked colouring, node detail) (ADR-010).
  DEVIATION: `docs/features/skill-tree-visualization.md` added; Playwright
  screenshots deferred — the frontend bundle cannot be built/served here (no
  node_modules), see the live-run-deferred note.
- [x] T10: Run `openspec validate skill-tree-visualization --strict` until clean.

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
