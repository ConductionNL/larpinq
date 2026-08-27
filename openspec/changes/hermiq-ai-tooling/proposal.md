# Proposal: hermiq-ai-tooling

## Summary
Add Larpinq's first curated (non-CRUD) MCP tools on top of the derived
surface the `larpinq-mcp-adoption` fragment already declares: a
`lib/Mcp/LarpinqScannableServices.php` opt-in
(`OCA\OpenRegister\Mcp\IMcpScannableServices`) exposing
`larpinq.awardXpToAttendees` (`#[McpTool]` write: one XP award per
checked-in attendee of an event, approval-gated in Hermiq) and
`larpinq.getCharacterSheet` (`#[McpTool]` read: the calculated character
sheet from `CharacterService::calculateCharacter()`), plus the spec-level
governance contract: GM approval gates on XP awards and sheet-affecting
writes, default-deny grants, and attributable audit.

## Motivation
The product line's direction is that every app action is in principle
AI-automatable, governed per agent by Hermiq's scope
(`read/create/update/delete`) × reach (`self/user/instance/external`) grant
model with default-deny writes, human approval gates, and an audit trail —
and that chat is a command surface for the app even before anything is
automated. The adoption change gave Larpinq 21 derived CRUD tools
(9 schemas × search/get, plus `character.create`, `xpAward.create`,
`attendance.update`), which covers single-object questions and single-row
writes. Two real GM workflows don't reduce to single derived calls:

- **"Award 5 XP to everyone who attended Saturday's event"** — a fan-out:
  resolve the event, read its checked-in `attendance` rows
  (`EventRosterService::buildRoster()`), create one `xpAward` per character.
  An agent could emulate it with N+2 derived calls, but then Hermiq must gate
  N separate writes (approval fatigue) or the agent free-runs N writes (no
  single approval covers the batch). A curated tool makes the *batch* the
  governed, approved unit.
- **"What's Alice's current sheet?"** — the calculated sheet (stat totals
  from skills/items/conditions/effects) exists only in
  `CharacterService::calculateCharacter()`; no derived `character.get` returns
  it. Genuine non-CRUD read.

decidesk's `lib/Mcp/` (gated write tools) and DocuDesk's
`DocudeskScannableServices` are the fleet references; ADR-063 rules the
mechanism (derive CRUD; curate genuine non-CRUD via `#[McpTool]` +
`IMcpScannableServices`).

## Affected Projects
- [ ] Project: `larpinq` — new `lib/Mcp/LarpinqScannableServices.php`,
  `#[McpTool]` attributes on `EventRosterService` (new method
  `awardXpToAttendees()`) and `CharacterService::calculateCharacter()` (or a
  thin sheet-shaped wrapper), DI alias registration.

## Scope

### In Scope
- `larpinq.awardXpToAttendees(eventId, amount, reason)`: creates one
  `xpAward` (`amount`, `reason`, `event`, `character`, server-stamped
  `awardedAt`/`awardedBy`) for each attendance row of the event with
  `status: "checked-in"`. `scope: 'create'`, `readOnlyHint: false`,
  `destructiveHint: false`, `idempotentHint: false`. Approval-required: the
  Hermiq gate shows the resolved recipient list (event, N characters, amount,
  reason) to a game master before any award is written.
- `larpinq.getCharacterSheet(characterId)`: the calculated sheet —
  base + computed stats, skills, items, conditions, XP total. `scope:
  'read'`, `readOnlyHint: true`.
- `lib/Mcp/LarpinqScannableServices.php` implementing
  `IMcpScannableServices`, registered under the DI alias (DocuDesk pattern).
- Spec-level governance contract: GM approval gate on XP awards and on any
  future sheet-affecting write; OR object-level `authorization` (gamemasters
  group on `xpAward`) remains the enforcement point; `mcp` attribution on
  agent-created awards; default-deny grants.
- Chat scenarios: "award 5 XP to everyone who attended Saturday's event";
  "what's Alice's current sheet?"; "who attended Saturday?" (derived
  `attendance.search`, already shipped — bound into the operation map, not
  duplicated).

### Out of Scope
- Any new derived verb on any schema (the adoption fragment stays as-is:
  no `delete` anywhere, no `event.create`).
- A character-sheet *write* tool (approve character, edit skills): the
  `approved` lifecycle transition is a GM act in the UI; deferred until a
  concrete workflow demands it, and pre-bound here to the same approval-gate
  requirement so a future change cannot add it ungated.
- Bulk tools beyond the single event-scoped XP fan-out (no campaign-wide or
  cross-event batch).
- PDF/runsheet generation tools (the runsheet flow delegates to DocuDesk).

## Approach
Follow the DocuDesk curated-tool chain: `#[McpTool]` attributes on real
service methods, one `IMcpScannableServices` implementation listing the two
classes, DI alias in the registration bootstrap. `awardXpToAttendees()` is a
new `EventRosterService` method composing the existing roster read and the
existing OR object-write path used by `xpAward.create` — same authorization
block, same server-stamped fields. No controller or route is added (tools are
served by OpenRegister's registry). Details, including the refused-tools
table and the batch-size bound, live in design.md.

## New Dependencies
None (OpenRegister's `AttributeToolScanner` / `McpTool` attribute, already a
runtime dependency of the adoption change).

## Impact
- New: `lib/Mcp/LarpinqScannableServices.php`; one new service method;
  two attributes; DI registration.
- Hermiq's tool catalog gains 2 curated tools next to the 21 derived ones.
- No change to the register fragments, the frontend, or existing controllers.

## Cross-Project Dependencies
OpenRegister `AttributeToolScanner` + `IMcpScannableServices` +
`McpAnnotationValidator` (all at origin/development; the DocuDesk adoption
runs them in production). Hermiq's human-approval gate
(`hermiq/openspec/specs/human-approval-gate/`) classifies and gates the write
tool from its declared hints; OR's tool-grant whitelist provides default-deny.

## Risks

### Risk 1: An agent awards XP to the wrong crowd (wrong event, wrong roster cut)
**Severity:** Medium — **Mitigation:** the approval gate shows the resolved
recipient list (names, not ids) before anything is written; the tool refuses
events with zero checked-in attendance instead of silently writing nothing;
the roster cut is exactly `status: "checked-in"` (registered/no-show rows are
never awarded).

### Risk 2: The fan-out partially fails, leaving half the table awarded
**Severity:** Medium — **Mitigation:** the tool reports per-character results
(created / failed) in its response and is safe to re-invoke: `idempotentHint:
false` is declared honestly, and the gate re-approval on retry shows which
characters already hold an award for that event+reason so the GM can see the
delta.

### Risk 3: Non-GM principals reach the write through an over-broad grant
**Severity:** Low — **Mitigation:** OR's object-level `authorization.create`
on `xpAward` (gamemasters group) is the enforcement point regardless of
grants — the same layering the adoption change verified for
`xpAward.create`.

## Rollback Strategy
Remove the two attributes, the service method, and the scannable-services
class + alias; the tools disappear from the registry on the next scan. No
config or schema change to revert.

## Open Questions
None.
