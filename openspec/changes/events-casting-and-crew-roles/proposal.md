---
kind: code
depends_on: [registration-intake-and-capacity]
---

# Proposal: events-casting-and-crew-roles

## Summary

Many LARPs are cast: the organisers write the characters, players say which
ones they would like to play, and someone spends an evening with a
spreadsheet matching the two. The same happens for crew: volunteers fill NPC
and crew roles such as bandits, healers or the kitchen. Larpinq has neither.
This change lets game masters open written characters for casting, lets
registered players rank them, proposes the assignment that gives the most
players their best choices, and lets game masters confirm it. It also adds
crew and NPC roles per event with places, filled from the registrations with a
crew or NPC ticket.

## Motivation

Two rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for both.

**`chr-casting-preferences`**, "Let players rank the characters they want to
play and have the tool find the best overall assignment." Characters is a core
area. Larpinq rates it no. Matrix evidence: "no preference or casting object
among the ten schemas (lib/Settings/larpinq_register.json:39-960); characters
are created by or for a player, never assigned from ranked wishes". One
competitor rates it yes:

- LarpManager: "larpmanager/fixtures/feature.yaml:170-180 casting feature, larpmanager/urls/event.py:209 casting_preferences, larpmanager/urls/orga.py:1476-1491 orga_casting, larpmanager/models/casting.py:187" (source read at main 36f23d3). Its changelog: https://github.com/LoSkana/larpmanager/commit/40b77c05ea.

**`evt-crew-roles`**, "Assign crew and NPC roles to volunteers for an event."
Larpinq rates it no. Matrix evidence: "grep -rniE "crew|npc" lib src ...: no
hits; character.type is a free-text field on the character schema with no
crew/NPC role assignment surface tied to an event". Three competitors rate it
yes:

- LarpManager: "larpmanager/models/registration.py:44-54 Staff, NPC, Collaborator ticket tiers, larpmanager/models/access.py:153 EventRole for staff, larpmanager/models/miscellanea.py:549-590 warehouse areas per event".
- MyLARP: "'Volunteer tracking (NPC/monstering sign-ins)' directly assigns crew/NPC roles to volunteers for an event. https://mylarp.com"
- LARP Portal: "Staff can 'make NPC characters' and hold plot/logistics roles distinct from PCs. https://larportal.com/how-it-works.php"

Both assign people to parts for one event, on one event screen.

## Affected Projects

- [ ] Project: `larpinq`: casting preferences and an assignment service, crew roles and assignments, and a Casting tab on the event page.

## Scope

### In Scope

- A character can be marked open for casting on an event (written by game masters, no owner yet).
- A registered player ranks up to 5 open characters for the event, before a casting deadline.
- A game master asks for a proposal: the assignment of open characters to players that maximises how well players are matched (first choice counts most), with at most one character per player and one player per character; players with no match are listed.
- The game master adjusts and confirms; on confirm, each cast character gets the player as owner and is set on the player's registration.
- Crew and NPC roles per event (name, kind crew or NPC, places, description); game masters assign registrations with a crew or NPC ticket to them; the event page shows roles with filled and open places.

### Out of Scope

- Weighting by play history or organiser priorities beyond rank order.
- Shift schedules for crew within the event (row `evt-schedule-within-event`, deferred).

## Approach

Register schemas for preferences, crew roles and crew assignments; a
`CastingService` computes the proposal with the Hungarian method on a rank
cost matrix and returns it without writing; confirming writes through the
normal object writes. A Casting tab on EventDetail, a registered section
component, shows ranks, the proposal and the crew roles. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/events-casting-and-crew-roles.json` (new).
- `lib/Service/CastingService.php`, `lib/Controller/CastingController.php` (new), two routes.
- `src/views/EventCasting.vue` (new, `kind: 'section'`), EventDetail sidebar tab in `src/manifest.json` (in place); My registrations gains the ranking.

## Cross-Project Dependencies

None.

## Risks

### Risk 1: Players see which characters others wanted
**Severity:** Medium. **Mitigation:** a preference is readable by game masters and its own player only.

### Risk 2: Large casts
**Severity:** Low. **Mitigation:** the Hungarian method runs in cubic time; 200 players by 200 characters is well under a second. The endpoint refuses above 500 by 500.

## Rollback Strategy

Remove the tab and routes. Preferences and roles stay as data; confirmed
castings are ordinary ownership.

## Open Questions

None.
