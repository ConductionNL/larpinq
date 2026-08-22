# Proposal: larpinq-mcp-adoption

## Summary
Adopt ADR-063 ("MCP as Platform Abstraction") in Larpinq by declaring the
`x-openregister-mcp` schema dialect on a curated set of 9 register schemas
(`character`, `player`, `setting`, `skill`, `item`, `condition`, `event`,
`xpAward`, `attendance`), so OpenRegister derives MCP tools for Hermiq without
any hand-written provider code. Larpinq has no existing `IMcpToolProvider`,
so this is a pure dialect declaration (`kind: config`). Unlike the read-only
Software Catalog sibling change, three narrow write verbs are enabled
(`character.create`, `xpAward.create`, `attendance.update`) because a LARP
campaign is genuinely low-stakes and each write maps to a documented,
RBAC-gated, additive/lifecycle-bounded action a GM or player would plausibly
ask an assistant to perform.

## Motivation
Larpinq manages LARP (live-action role-play) characters, campaigns
("settings"), events, and the game-master workflows around them (approving
characters, awarding XP, checking attendees in at an event). These are exactly
the conversational, single-purpose actions a chat assistant is good at ("show
me my character", "who's coming to the summer event", "award Alice 5 XP for
tonight's session", "check Bob in at the door") but Larpinq currently
exposes zero MCP surface. ADR-063 (hydra #102, merged) establishes the
declarative dialect pattern; the pipelinq leaf migration
(`mcp-provider-declarative-migration`, PR #390) is the working read-only
exemplar, and pipelinq's curated `#[McpTool]` write tools
(`LeadService::createLead`) are the precedent for arguing a write is safe. This
change applies the same discipline to a smaller, lower-stakes domain where a
few writes are defensible.

## Affected Projects
- [ ] Project: `larpinq` — declare `x-openregister-mcp` on 9 curated schemas
  (6 read-only, 3 with one additional write verb each) via a new
  `lib/Settings/register.d/` fragment; no PHP changes (no provider exists to
  migrate or delete).

## Scope

### In Scope
- Declare `configuration.x-openregister-mcp` on: `character`, `player`,
  `setting`, `skill`, `item`, `condition`, `event`, `xpAward`, `attendance`.
- `search` + `get` on all 9; additionally `create` on `character` and
  `xpAward`, and `update` on `attendance` — each argued individually in
  design.md.
- `search.filters` restricted to real, cross-checked schema properties.
- Agent-facing `description` prose per verb per schema.
- Honest MCP hints (`readOnlyHint`/`destructiveHint`/`idempotentHint`) and
  `scope` from the closed `read|create|update|delete` set on every verb,
  including the three writes.
- A dedicated `register.d/larpingapp-mcp-adoption.json` fragment (ADR-037) —
  this change never edits `larpinq_register.json` directly, per this
  repo's own `register.d/README.md` ("Do not edit `larpinq_register.json`
  ... in a build branch").

### Out of Scope
- `ability` and `effect` — excluded, see design.md exclusion table (internal
  stat-modifier primitives, not something a player/GM asks about directly).
- `delete` on any schema, and `create`/`update` beyond the three argued cases —
  bias to fewer writes; e.g. `event.create` is deliberately deferred because
  event creation fans out to three leaf integrations (calendar/maps/forms).
  See design.md.
- No hand-written `#[McpTool]` service method — the three writes are plain
  schema-derived CRUD (`x-openregister-mcp` verbs), not curated non-CRUD
  actions, so no PHP service class or `IMcpScannableServices` opt-in is added.
- No change to `ConfigFileLoaderService`, the fragment-merge mechanism, or any
  existing `register.d/*.json` fragment (`event-calendar-leaf`,
  `event-checkin-roster`, `event-location-to-maps-leaf`,
  `event-signup-to-forms-leaf`, `player-to-contacts-leaf`, `portal-identity`).

## Approach
Declare the dialect purely as data: one new `register.d/*.json` fragment
(ADR-037 recursive deep-merge, confirmed in
`lib/Service/ConfigFileLoaderService.php`) that adds a
`configuration.x-openregister-mcp` block to each curated schema — merging
cleanly alongside `character`'s existing `configuration.x-openregister-lifecycle`
block and the `linkedTypes` already contributed by other fragments (e.g.
`event`'s calendar/maps/forms leaves). OpenRegister's `SchemaDerivedToolProvider`
then derives the tools at runtime. Details (exact per-verb JSON, filter lists,
hint values, and the write-safety argument for each of the three writes) live
in design.md.

## New Dependencies
None.

## Impact
- `lib/Settings/register.d/larpingapp-mcp-adoption.json` (new file).
- OpenRegister's MCP surface for Hermiq gains 21 derived tools (9 schemas ×
  search/get, plus 3 extra write verbs) once imported.
- No change to existing REST controllers, Vue frontend, `CharacterService`
  stat-calculation engine, or existing register fragments.

## Cross-Project Dependencies
Depends on OpenRegister's `SchemaDerivedToolProvider` / `McpAnnotationValidator`
(already merged at origin/development) to derive and serve the tools; inert
configuration until that import runs. `attendance.update` and `xpAward.create`
both rely on OpenRegister's own object-level `authorization` block (already
present on both schemas, restricting `create`/`update`/`delete` to the
`gamemasters` group) as the actual enforcement point — MCP's `scope` is
descriptive metadata for Hermiq's tool classifier, not a second authorization
system, per `McpAnnotationValidator`'s own docblock.

## Risks

### Risk 1: A declared search filter doesn't match a real schema property
**Severity:** Medium — **Mitigation:** Every filter in design.md was
cross-checked against `lib/Settings/larpinq_register.json` at HEAD (property
dumps recorded in design.md); OpenRegister's `McpAnnotationValidator` also
hard-rejects the whole schema import on any unknown filter property.

### Risk 2: `character.create` / `xpAward.create` / `attendance.update` let an
agent write game data without a human confirming each field
**Severity:** Medium — **Mitigation:** Each write is bounded: `character.create`
only ever produces a draft in the `approved: "no"` lifecycle state (nothing is
"live" until a GM runs the existing `approved` transition); `xpAward.create` is
purely additive audit data restricted to the `gamemasters` group by OR
authorization; `attendance.update` can only move `status` forward
(`registered → checked-in/no-show`) and `checkedInAt`/`checkedInBy` are
server-stamped and never accepted from the client, per the schema's own
description. All three are already RBAC- or lifecycle-gated independent of MCP.

## Rollback Strategy
Delete `lib/Settings/register.d/larpingapp-mcp-adoption.json` (or flip every
`enabled` to `false`) and re-run the settings import; the fragment signature
changes so OpenRegister re-imports and the derived tools (including the three
writes) disappear. No other file is touched, so rollback is a single-file
revert.

## Open Questions
None — see design.md `DEFERRED_QUESTIONS` for follow-up items that don't block
this change.
