# Design: hermiq-ai-tooling

## Architecture Overview
The adoption change (`larpingapp-mcp-adoption`) is pure dialect: 21 derived
tools from `register.d/larpingapp-mcp-adoption.json`, zero PHP. This change
adds the curated layer on the DocuDesk pattern (chain: dialect → curated
`#[McpTool]` methods → `IMcpScannableServices` opt-in):

```
Hermiq (agent)                 grants: default-deny, scope × reach per agent
  -> OpenRegister /api/mcp
    -> SchemaDerivedToolProvider      -> 21 derived tools   [shipped]
    -> AttributeToolScanner
       -> LarpingappScannableServices [THIS CHANGE]
          -> EventRosterService::awardXpToAttendees()   #[McpTool] create, gated
          -> CharacterService  (calculated sheet)       #[McpTool] read
```

## Why the fan-out is one tool, not N derived calls
"Award 5 XP to everyone who attended Saturday" via derived tools is:
`event.search` → `attendance.search(event, status=checked-in)` → N ×
`xpAward.create`. Three governance problems: (1) the gate either fires N
times (approval fatigue trains the GM to rubber-stamp) or the agent
free-runs N writes under one broad grant; (2) partial failure leaves no
single record of what the *batch* was; (3) the roster cut (checked-in only)
is re-decided by the model on every run instead of being a tested invariant.
A curated tool makes the batch the unit of approval, audit, and testing —
the same argument decidesk's gated meeting tools and DocuDesk's
`generateCorrespondence` made for their multi-step actions (ADR-063: curate
genuine non-CRUD, derive the rest).

Bounds: event-scoped only (no campaign-wide batch — the blast radius of one
event's roster is the largest a single approval can meaningfully review);
recipients resolved server-side from `attendance.status = "checked-in"`,
never from a model-supplied character list.

## Why the sheet read is curated
The sheet is computed (`CharacterService::calculateCharacter()` folds
skills/items/conditions/effects into stat totals); no derived `get` returns
it, and having the model re-derive stats from raw objects invites confident
arithmetic errors. Read-only, no gate. `slNotesPrivate` is excluded from the
result: GM-private notes must not enter an agent context that a player's own
chat session might share — the one content-boundary this app has.

## Refused tools

| Candidate | Verdict | Why |
|-----------|---------|-----|
| `approveCharacter` / sheet edits | Deferred, pre-bound | The `approved` lifecycle transition is the GM's judgement call in the UI; REQ-002 already binds any future sheet-affecting write to the gate so it cannot arrive ungated |
| Campaign-wide / multi-event XP batch | Refused | Approval reviewability bound (see above) |
| `checkInParticipant` curated wrapper | Refused | Derived `attendance.update` already covers it one-row-at-a-time, which is exactly the pace of a door check-in |
| Runsheet/PDF generation | Refused | Delegates to DocuDesk; a tool here would wrap another app's surface (ADR-022) |
| Any `delete` | Refused | Same as the adoption change: nothing in a game audit trail should be agent-deletable |

## Governance layering (who enforces what)
- **OR tool grants** — whether the agent sees the tool at all (default-deny).
- **Hermiq approval gate** — whether this invocation happens (hint-driven
  classification; hints are load-bearing, hermiq #57 fail-open lesson).
- **OR `authorization` on `xpAward`** — whether the write lands (gamemasters
  group; enforced even if both layers above are misconfigured).
- **App code** — roster cut, server-stamped fields, per-character result
  report, `mcp` attribution.

LarpingApp adds no gate/grant code of its own; it declares honest hints and
keeps the OR authorization block authoritative — the layering the adoption
change's Risk 2 analysis already established.

## Partial failure
The fan-out is not transactional across N OR writes. The response is a
per-character result list (`created` / `failed(reason)`); a retry re-runs the
gate, and the gate rendering includes which checked-in characters already
hold an award for the same event and reason, so the GM approves the delta
knowingly. `idempotentHint: false` is therefore honest and required.
