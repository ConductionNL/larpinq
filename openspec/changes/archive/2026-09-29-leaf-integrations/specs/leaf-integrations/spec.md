# leaf-integrations Specification

**Status**: planned
**Scope**: larpinq
**OpenSpec changes**:
- _(none yet)_

## Purpose
Completes Larpinq's integration-leaf adoption (ADR-019 registry, ADR-022
consume-don't-build) by declaring the talk, polls, and deck leaves on the
`event` and `setting` schemas via a `register.d` fragment, alongside the five
leaves already shipped (calendar/maps/forms on `event`, contacts on `player`,
photos on `character`).

## ADDED Requirements

### Requirement: REQ-001 — `event` declares the talk, polls, and deck leaves
The merged register's `event` schema MUST carry `configuration.linkedTypes`
containing `"talk"`, `"polls"`, and `"deck"`, contributed by
`lib/Settings/register.d/leaf-integrations.json`, in addition to the
`"calendar"`, `"maps"`, and `"forms"` values contributed by the existing
fragments — six values in total after the ADR-037 deep merge.

#### Scenario: Event detail renders the three new leaf tabs
- GIVEN the larpingapp register merged with `register.d/leaf-integrations.json`
- AND Talk, Polls, and Deck are installed and enabled
- WHEN a game master opens an event's detail page
- THEN the talk, polls, and deck leaf tabs/widgets MUST be rendered via the integration registry
- AND the calendar, maps, and forms leaves MUST still be present unchanged

#### Scenario: The merge preserves the other fragments' leaves
- GIVEN the merged register (monolith + all `register.d/*.json` fragments)
- WHEN `event`'s `configuration.linkedTypes` is inspected
- THEN it MUST contain all of `calendar`, `maps`, `forms`, `talk`, `polls`, `deck`
- AND no fragment's contribution may be lost to the merge

### Requirement: REQ-002 — `setting` declares the talk and polls leaves
The merged register's `setting` schema MUST carry `configuration.linkedTypes`
containing `"talk"` and `"polls"`, contributed by the same fragment: a
setting (campaign) hosts the standing campaign chat room and campaign-level
votes (rules revisions). The fragment MUST NOT declare `deck` on `setting`.

#### Scenario: Campaign room and rules vote hang off the setting
- GIVEN a setting "Winterfell Chronicles" and the fragment imported
- WHEN a game master opens the setting's detail page
- THEN the talk leaf (campaign room) and polls leaf (campaign votes) MUST be rendered
- AND no deck leaf MUST be rendered on the setting page

### Requirement: REQ-003 — Leaves are declared via a register fragment, not the monolith
The talk/polls/deck declarations MUST live in a dedicated
`lib/Settings/register.d/leaf-integrations.json` fragment (ADR-037).
`lib/Settings/larpinq_register.json` and the existing fragments MUST NOT
be edited by this change.

#### Scenario: Monolith and existing fragments untouched
- GIVEN this change applied
- WHEN `larpinq_register.json` and the six pre-existing `register.d` files are diffed against HEAD
- THEN they MUST be byte-identical
- AND the new declarations MUST come only from `leaf-integrations.json`

### Requirement: REQ-004 — Leaves degrade gracefully when their app is absent
Each declared leaf MUST render only when its Nextcloud app (Talk, Polls,
Deck) is installed and enabled; when absent, the leaf MUST be hidden and the
detail page MUST render without error, matching the graceful-degradation
contract of the shipped calendar/maps/forms leaves.

#### Scenario: No Deck installed, no Deck tab, no error
- GIVEN a host instance without the Deck app
- WHEN an event detail page renders
- THEN the deck leaf MUST NOT be present
- AND the talk and polls leaves (apps present) MUST render normally

### Requirement: REQ-005 — Leaves are coordination surfaces, not game-state write paths
The talk, polls, and deck leaves MUST NOT write to any register object: chat
messages, poll outcomes, and card states live in their own apps, linked to
the event/setting object through the leaf. Adopting a poll's outcome (e.g. a
rules change or a chosen date) MUST remain a human edit through the existing
object forms; no leaf MUST mutate `event` or `setting` properties.

#### Scenario: A closed scheduling poll does not move the event date
- GIVEN an event with a scheduling poll closed on "June 14"
- WHEN the poll closes
- THEN the event's `startDate`/`endDate` MUST be unchanged
- AND changing them MUST require a game master edit on the event form
