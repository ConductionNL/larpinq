# larpinq-mcp-adoption Specification

**Status**: planned
**Scope**: larpinq
**OpenSpec changes**:
- _(none yet)_

## Purpose
Adopts ADR-063 ("MCP as Platform Abstraction", hydra #102) in Larpinq by
declaring the `x-openregister-mcp` schema dialect on a curated set of game
schemas, so OpenRegister's `SchemaDerivedToolProvider` derives Hermiq-consumable
MCP tools without any hand-written provider PHP. Unlike the read-only
`softwarecatalog-mcp-adoption` sibling spec, Larpinq's low-stakes game domain
supports three narrow, individually-bounded write verbs.

## ADDED Requirements

### Requirement: REQ-001 — Curated read-only MCP dialect on 6 reference/lookup schemas
The `player`, `setting`, `skill`, `item`, `condition`, and `event` schemas MUST
declare a `configuration.x-openregister-mcp` block with `enabled: true` and
exactly the `search` and `get` tool verbs, each with `scope: "read"` and
`readOnlyHint: true`.

#### Scenario: A read-only schema exposes derived search and get tools
- GIVEN the larpingapp register merged with `register.d/larpingapp-mcp-adoption.json`
- WHEN OpenRegister's `SchemaDerivedToolProvider` lists MCP tools for the `larpinq` app
- THEN the tool list MUST include `larpinq.event.search` and `larpinq.event.get`
- AND the tool list MUST NOT include `larpinq.event.create`, `.update`, or `.delete`
- AND this MUST hold for `player`, `setting`, `skill`, `item`, and `condition` as well

### Requirement: REQ-002 — `character` exposes search, get, and a bounded create
The `character` schema MUST declare `search` and `get` (as REQ-001) plus a
`create` verb with `scope: "create"`. The `create` verb MUST NOT be
accompanied by `update` or `delete`.

#### Scenario: A drafted character is created in the unapproved lifecycle state
- GIVEN the larpingapp register merged with `register.d/larpingapp-mcp-adoption.json`
- WHEN an agent calls `larpinq.character.create` with a valid `name`
- THEN the created character's `approved` field MUST be `"no"` (the schema's declared initial lifecycle state)
- AND the character MUST NOT be usable in play until a game master runs the existing `approved` transition

### Requirement: REQ-003 — `xpAward` exposes search, get, and a bounded create
The `xpAward` schema MUST declare `search` and `get` (as REQ-001) plus a
`create` verb with `scope: "create"`, `readOnlyHint: false`,
`destructiveHint: false`, `idempotentHint: false`. The `create` verb MUST NOT
be accompanied by `update` or `delete`.

#### Scenario: An XP award is recorded as an additive audit record
- GIVEN the larpingapp register merged with `register.d/larpingapp-mcp-adoption.json`
- WHEN an agent calls `larpinq.xpAward.create` with `event`, `character`, and `amount`
- THEN a new `xpAward` object MUST be created
- AND OpenRegister's object-level `authorization.create` MUST still restrict the underlying write to the `gamemasters` group regardless of the MCP `scope` annotation

### Requirement: REQ-004 — `attendance` exposes search, get, and a bounded update
The `attendance` schema MUST declare `search` and `get` (as REQ-001) plus an
`update` verb with `scope: "update"`, `readOnlyHint: false`,
`destructiveHint: false`, `idempotentHint: true`. The `update` verb MUST NOT
be accompanied by `create` or `delete`.

#### Scenario: A GM checks a participant in at the door
- GIVEN an existing `attendance` record with `status: "registered"`
- WHEN an agent calls `larpinq.attendance.update` setting `status` to `"checked-in"`
- THEN the record's `status` MUST become `"checked-in"`
- AND `checkedInAt` and `checkedInBy` MUST be server-stamped, never taken from the MCP call's input
- AND OpenRegister's object-level `authorization.update` MUST still restrict the underlying write to the `gamemasters` group

### Requirement: REQ-005 — Every declared search filter names a real schema property
Each of the 9 curated schemas' `search` verb `filters` list MUST contain only
strings that name a property declared in that same schema's `properties` map
(including properties contributed by another `register.d/*.json` fragment,
e.g. `attendance`'s properties from `event-checkin-roster.json`).

#### Scenario: Import succeeds because every filter is a real property
- GIVEN `register.d/larpingapp-mcp-adoption.json` declares filters such as `character.search.filters = ["name", "type", "approved", "setting", "ownerUid"]`
- WHEN OpenRegister's `McpAnnotationValidator` validates the merged `character` schema at import time
- THEN validation MUST report zero `mcp-unknown-filter-property` errors for the `character` schema
- AND this MUST hold for all 9 curated schemas' filter lists

### Requirement: REQ-006 — No excluded schema, and no undeclared verb, is exposed
The `ability` and `effect` schemas MUST NOT declare `x-openregister-mcp`.
No curated schema other than `character`, `xpAward`, and `attendance` MUST
declare any verb beyond `search`/`get`. No schema MUST declare a `delete`
verb.

#### Scenario: Excluded schemas expose no MCP tools
- GIVEN the larpingapp register merged with `register.d/larpingapp-mcp-adoption.json`
- WHEN OpenRegister's `SchemaDerivedToolProvider` lists MCP tools for the `larpinq` app
- THEN the tool list MUST NOT include any tool for `ability` or `effect`
- AND the tool list MUST NOT include `larpinq.event.create` (deferred — see design.md Decision 2)
- AND the tool list MUST NOT include any `.delete` tool for any schema

### Requirement: REQ-007 — MCP dialect is declared via a register fragment, not the monolith
The `x-openregister-mcp` blocks introduced by this change MUST live in a new
`lib/Settings/register.d/larpingapp-mcp-adoption.json` fragment file;
`lib/Settings/larpinq_register.json` MUST NOT be modified by this change,
per this repo's own `register.d/README.md` convention.

#### Scenario: The monolith is untouched
- GIVEN a diff of this change against the base commit
- WHEN inspecting which files changed under `lib/Settings/`
- THEN the diff MUST include `lib/Settings/register.d/larpingapp-mcp-adoption.json`
- AND the diff MUST NOT include `lib/Settings/larpinq_register.json`

### Requirement: REQ-008 — MCP tools are derived without app-level PHP
Larpinq MUST NOT ship a hand-written `IMcpToolProvider` implementation or
any `#[McpTool]`-attributed service method as part of this change; the entire
MCP surface introduced by this change, including its three write verbs, MUST
be expressed as `x-openregister-mcp` dialect data.

#### Scenario: No provider class exists after this change
- GIVEN this change is applied
- WHEN searching `lib/` for a class implementing `OCA\OpenRegister\Mcp\IMcpToolProvider` or `IMcpScannableServices`
- THEN no such class MUST exist in the `larpinq` app

## Non-Functional Requirements

- **Performance:** Declaring the dialect adds no runtime cost to Larpinq's
  own request path — tool derivation happens inside OpenRegister at
  MCP-serve time.
- **Accessibility:** Not applicable — this change has no user-facing UI
  surface.
- **Internationalization:** Agent-facing tool `description` text is authored
  in English per fleet convention; the underlying game data (character names,
  descriptions, etc.) is unaffected by this change.

## Acceptance Criteria

- [ ] `register.d/larpingapp-mcp-adoption.json` declares `configuration.x-openregister-mcp` on exactly the 9 curated schemas (REQ-001–REQ-004).
- [ ] `character` has `search`/`get`/`create` only (REQ-002); `xpAward` has `search`/`get`/`create` only (REQ-003); `attendance` has `search`/`get`/`update` only (REQ-004); the remaining 6 curated schemas have `search`/`get` only (REQ-001).
- [ ] No `delete` verb anywhere, and `event.create` is not declared (REQ-006).
- [ ] Every `search.filters` entry is a real property of its schema (REQ-005), verified against `lib/Settings/larpinq_register.json` and the relevant `register.d/*.json` fragments at apply time.
- [ ] `lib/Settings/larpinq_register.json` is unmodified (REQ-007).
- [ ] No `#[McpTool]`/`IMcpToolProvider` PHP is introduced (REQ-008).
- [ ] The new fragment file is valid JSON (`python3 -m json.tool`).

## Notes
Related ADRs: ADR-063 (MCP as Platform Abstraction), ADR-037 (register
fragments). Exemplar: pipelinq `mcp-provider-declarative-migration` (archived,
PR #390) for the dialect shape, and pipelinq's curated `#[McpTool]`
`LeadService::createLead` (from `crm-mcp-tool-surface`, PR #379) as the
precedent for arguing a write verb is safe — though this change's three writes
are plain schema-derived CRUD verbs, not curated non-CRUD service tools, since
no genuine non-CRUD action was identified as worth a hand-written `#[McpTool]`
here. See the parent change's `design.md` curation table, exclusion list, and
per-write safety argument for the full reasoning, and `DEFERRED_QUESTIONS` for
follow-up work (`event.create`, `attendance.create` for walk-ins) not part of
this change.
