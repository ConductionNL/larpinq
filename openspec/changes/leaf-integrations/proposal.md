# Proposal: leaf-integrations

## Summary
Complete Larpinq's adoption of OpenRegister's app-agnostic integration
leaves by declaring the three that are still missing — **talk** (event and
campaign chat rooms), **polls** (session scheduling and rules votes), and
**deck** (GM prep boards) — via one new `lib/Settings/register.d/` fragment
(ADR-037), the same declarative `configuration.linkedTypes` mechanism the
app's five shipped leaves already use. Pure config (`kind` equivalent:
config-only); no PHP, no bespoke widgets.

## Motivation
Larpinq already consumes five leaves, each through its own archived change:
calendar on `event` (`event-calendar-leaf`), maps on `event`
(`event-location-to-maps-leaf`), forms on `event`
(`event-signup-to-forms-leaf`), contacts on `player`
(`player-to-contacts-leaf`), and photos on `character`
(`character-photos-leaf`, `linkedTypes: ["photos"]` in the monolith). The
remaining coordination work of running a LARP campaign still happens off-app:
"where do we discuss Saturday's event?" (a Talk room per event/campaign),
"which Saturday suits everyone?" and "do we adopt the new rules revision?"
(Polls), and "what does the GM still need to prepare?" (a Deck board per
event). Per hydra **ADR-022** (Apps Consume OpenRegister Abstractions) and
**ADR-019** (Integration Registry Pattern) these MUST be consumed as OR
leaves on the object detail pages, not built as app-local chat/vote/task
widgets. OR ships all three providers at HEAD (`TalkProvider` id `talk`,
`PollsProvider` id `polls`, `DeckProvider` id `deck`).

## Affected Projects
- [ ] Project: `larpinq` — declare `linkedTypes` additions for `event` and
  `setting` via a new `lib/Settings/register.d/leaf-integrations.json`
  fragment; no PHP changes.

## Scope

### In Scope
- **talk** leaf on `event` and `setting`: an event gets its session chat
  room; a setting (campaign) gets its standing campaign room.
- **polls** leaf on `event` and `setting`: session-scheduling votes hang off
  the event; rules votes hang off the campaign.
- **deck** leaf on `event`: GM prep board (props, NPCs, plot beats) linked to
  the event record.
- A dedicated `register.d/leaf-integrations.json` fragment (ADR-037) — this
  change never edits `larpinq_register.json` directly, per this repo's own
  `register.d/README.md`.
- Graceful degradation when Talk / Polls / Deck are not installed.

### Out of Scope
- The five shipped leaves (calendar, maps, forms, contacts, photos) — they
  are the baseline this change completes, not part of it. Note: the task
  briefing that seeded this change listed maps and forms as gaps; both were
  verified shipped (`event-location-to-maps-leaf.json`,
  `event-signup-to-forms-leaf.json` fragments at HEAD) and are therefore
  excluded here.
- deck on `setting` — a standing campaign board is plausible but no concrete
  GM workflow asked for it yet; bias to fewer (the same discipline as the MCP
  adoption's deferred `event.create`).
- talk/polls/deck on `character`, `player`, or any other schema — no
  per-character chat room or vote makes sense.
- Any leaf behaviour change in OpenRegister (consumed, not changed), and any
  MCP surface change (see the sibling `hermiq-ai-tooling` change).

## Approach
Declare the leaves purely as data: one new `register.d/*.json` fragment whose
`linkedTypes` arrays deep-merge (ADR-037, confirmed in
`ConfigFileLoaderService`) alongside the values other fragments already
contribute to the same schemas — `event` already carries `calendar`, `maps`,
and `forms` from its three leaf fragments, so merge order and array-merge
semantics must be verified against `mergeRegisterFragments()` (see design.md
for the measured behaviour and the resulting fragment shape). The registry
(`IntegrationRegistry::getEnabled()`) then renders the leaf tabs/widgets on
the `event` and `setting` detail pages through the shared nc-vue host —
exactly as the five shipped leaves render today.

## New Dependencies
None.

## Impact
- `lib/Settings/register.d/leaf-integrations.json` (new file).
- `event` detail gains talk/polls/deck leaf tabs; `setting` detail gains
  talk/polls leaf tabs — all registry-rendered, hidden when the NC app is
  absent.
- No change to existing fragments, controllers, the Vue frontend beyond what
  the registry host already renders, or the stat-calculation engine.

## Cross-Project Dependencies
Depends on OpenRegister's `TalkProvider`, `PollsProvider`, and `DeckProvider`
(all present at openregister HEAD) and the integration registry / nc-vue leaf
host already consumed by the shipped leaves. Inert configuration until the
fragment-merge import runs.

## Risks

### Risk 1: Fragment merge clobbers the `linkedTypes` other fragments contribute
**Severity:** Medium — **Mitigation:** design.md records the measured
`mergeRegisterFragments()` array semantics before the fragment shape is
final; the acceptance test asserts the *merged* `event.linkedTypes` contains
all six values (`calendar`, `maps`, `forms`, `talk`, `polls`, `deck`), not
just this fragment's three.

### Risk 2: Leaf tabs clutter the event page for groups that don't use Talk/Polls/Deck
**Severity:** Low — **Mitigation:** the registry only renders leaves whose NC
app is installed and enabled; a group that doesn't install Deck never sees a
Deck tab.

## Rollback Strategy
Delete `lib/Settings/register.d/leaf-integrations.json` and re-run the
settings import; the fragment signature changes, the merged register drops
the three `linkedTypes` values, and the tabs disappear. Single-file revert.

## Open Questions
None.
