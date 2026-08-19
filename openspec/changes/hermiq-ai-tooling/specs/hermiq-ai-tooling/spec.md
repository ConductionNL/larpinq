# hermiq-ai-tooling Specification

**Status**: planned
**Scope**: larpingapp
**OpenSpec changes**:
- _(none yet)_

## Purpose
LarpingApp's curated MCP tool surface on top of the derived-CRUD adoption
(`larpingapp-mcp-adoption`): the event-scoped XP fan-out write, the
calculated-character-sheet read, the scannable-services opt-in, and the
governance contract (GM approval gates, default-deny grants, audit
attribution) that binds them — so any LarpingApp action an agent performs is
governed by Hermiq's scope × reach model with a game master in the loop for
writes.

## ADDED Requirements

### Requirement: REQ-001 — A curated event-scoped XP award tool exists
`EventRosterService` MUST provide `awardXpToAttendees(eventId, amount,
reason)` carrying `#[McpTool(name: 'awardXpToAttendees', scope: 'create',
readOnlyHint: false, destructiveHint: false, idempotentHint: false)]`. The
tool MUST create exactly one `xpAward` object (`event`, `character`,
`amount`, `reason`, server-stamped `awardedAt`/`awardedBy`) per `attendance`
row of the event whose `status` is `"checked-in"`, and MUST NOT award
`registered` or `no-show` rows. It MUST refuse (with an explanatory error,
not an empty success) when the event has zero checked-in attendance. The
response MUST report per-character results so partial failures are visible.

#### Scenario: "Award 5 XP to everyone who attended Saturday's event"
- GIVEN an event with three attendance rows `checked-in` and one `no-show`
- WHEN an agent invokes `larpingapp.awardXpToAttendees` with amount 5 and a reason, and the game master approves the gate
- THEN exactly three `xpAward` objects MUST be created, one per checked-in character, each with `amount` 5
- AND the `no-show` character MUST receive no award
- AND `awardedAt`/`awardedBy` MUST be server-stamped, never taken from the tool input

#### Scenario: Empty roster refuses instead of silently succeeding
- GIVEN an event with no checked-in attendance
- WHEN the tool is invoked
- THEN it MUST return an error naming the empty roster
- AND no `xpAward` object MUST be created

### Requirement: REQ-002 — XP awards and sheet-affecting writes require a game-master approval gate
Every curated LarpingApp write tool — `awardXpToAttendees` now, and any
future tool that creates XP awards or changes a character sheet — MUST
declare honest write hints so Hermiq classifies it as approval-required, and
MUST only execute after Hermiq's human-approval gate has shown the resolved
effect (for XP: the event, the named recipient characters, the amount, the
reason) and received explicit approval. OpenRegister's object-level
`authorization` block on `xpAward` (gamemasters group) MUST remain the
enforcement point independent of grants and gates; LarpingApp MUST NOT
implement its own grant or approval mechanism.

#### Scenario: The gate shows the resolved recipients before any write
- GIVEN an agent invoking `awardXpToAttendees` for an event with five checked-in characters
- WHEN the approval gate renders
- THEN it MUST present the five character names, the amount, and the reason
- AND rejecting the approval MUST leave zero `xpAward` objects created

#### Scenario: A non-gamemaster principal cannot write through the tool
- GIVEN a caller outside the `gamemasters` group holding a (mis)grant for the tool
- WHEN the tool executes its per-character creates
- THEN OpenRegister's `authorization.create` on `xpAward` MUST reject every write

### Requirement: REQ-003 — A curated calculated-character-sheet read tool exists
`CharacterService` MUST expose the calculated sheet as
`#[McpTool(name: 'getCharacterSheet', scope: 'read', readOnlyHint: true)]`:
for a given character id, the computed result of
`CharacterService::calculateCharacter()` — base and computed stats, skills,
items, conditions, and XP total. The tool MUST NOT include `slNotesPrivate`
in its result (GM-private notes stay out of agent context), and MUST return
not-found for a character the invoking principal cannot read.

#### Scenario: "What's Alice's current sheet?"
- GIVEN an approved character "Alice" with skills and items affecting her stats
- WHEN an agent invokes `larpingapp.getCharacterSheet` for Alice
- THEN the result MUST contain the calculated stat totals as the app's own sheet view computes them
- AND the result MUST NOT contain `slNotesPrivate`

#### Scenario: Sheet reads leave no game-state trace
- GIVEN any invocation of `getCharacterSheet`
- WHEN it completes
- THEN no register object MUST have been created or modified

### Requirement: REQ-004 — Curated tools are exposed via the scannable-services opt-in
`lib/Mcp/LarpingappScannableServices.php` MUST implement
`OCA\OpenRegister\Mcp\IMcpScannableServices`, MUST be registered under the
`IMcpScannableServices::larpingapp` DI alias, and MUST list exactly the
service classes carrying `#[McpTool]` methods (`EventRosterService`,
`CharacterService`). No hand-written `IMcpToolProvider` MUST be added, and no
derived verb beyond the adoption fragment's set MUST be enabled by this
change.

#### Scenario: The tool surface is exactly derived-plus-two
- GIVEN the register imported and the scannable services registered
- WHEN Hermiq lists the `larpingapp.*` tool catalog
- THEN it MUST contain the 21 derived tools plus `awardXpToAttendees` and `getCharacterSheet`
- AND no other curated tool and no new derived verb MUST appear

### Requirement: REQ-005 — Agent-created XP awards are attributable and grants are default-deny
Tool availability MUST be governed by OpenRegister's default-deny tool-grant
whitelist per agent. Every `xpAward` created through `awardXpToAttendees`
MUST be attributable as agent-initiated (an `mcp` attribution with the
invoking principal) and distinguishable from awards a game master creates in
the UI or via derived `xpAward.create`.

#### Scenario: Ungranted agent sees no LarpingApp curated tool
- GIVEN an agent whose grants include no LarpingApp curated tool
- WHEN it lists available tools
- THEN neither `larpingapp.awardXpToAttendees` nor `larpingapp.getCharacterSheet` MUST be offered

#### Scenario: An audit question is answerable
- GIVEN XP awards created by an agent fan-out and others created manually
- WHEN the awards for the event are inspected
- THEN the agent-created ones MUST carry the `mcp` attribution and the invoking principal
