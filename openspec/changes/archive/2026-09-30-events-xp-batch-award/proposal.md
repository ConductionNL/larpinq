---
kind: code
depends_on: []
---

# Proposal: events-xp-batch-award

## Summary

After an event a game master awards XP to everyone who was there. In larpinq
that is one create form per character, and nothing looks at who actually
checked in. The live spec `event-xp-awards` already requires a batch "Award
XP" surface on the event page; the archived change `event-xp-award-workflow`
deferred it (tasks 3.1 to 3.4) and the archived `event-checkin-roster` change
deferred tying it to attendance (task T7). This change builds that surface:
the roster with checked-in participants ticked by default, no-shows unticked,
one amount for all with per-row overrides, and one award per ticked character
on save, stamped with who awarded it and when.

## Motivation

Two rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for both.

**`prg-award-xp-bulk`**, "Award experience points to everyone who attended an
event in one go." Larpinq rates it no. Matrix evidence: "grep -rniE
'bulk|mass.?award|award.*all|everyone' lib src: no hits ...; each xpAward is
created one at a time (event, character, amount) via the generic object-create
form, no multi-select-attendees-then-award action exists anywhere in src/".
One competitor rates it yes, one partial; the build decision rests on the
archived change that specified it and never shipped it, while the row stayed
`none`:

- LarpManager (yes): "larpmanager/views/orga/experience.py:103-127 and 148-193 load an award prefilled with all run participants, only checked-in ones, or debrief respondents" (source read at main 36f23d3).
- LARP Portal (partial): "Cross-campaign point assignment is processed by staff in a batch when 'staff process point assignments', but a dedicated bulk-by-event UI isn't confirmed. https://larportal.com/larp-portal-tips.php"

**`evt-attendance-drives-xp`**, "Use recorded attendance to decide who gets
XP." Larpinq rates it no. Matrix evidence: "nothing reads
larping_attendance.status when creating or validating an xpAward ...". The row
note: "a GM can award XP to a no-show or skip a checked-in character freely."
Two competitors rate it yes:

- LarpManager: "larpmanager/views/orga/experience.py:108-116 and 156-159 load an XP award for checked-in participants only".
- LARP Portal: "Staff 'assign points' after an event, and registration records which game an NPC's points should go to, tying participation to point awards. https://larportal.com/how-it-works.php"

## Affected Projects

- [ ] Project: `larpinq`: an Award XP tab on the event page, a batch award endpoint, and server stamping of award provenance.

## Scope

### In Scope

- The "Award XP" surface of the `event-xp-awards` requirement, as a sidebar tab on EventDetail for game masters.
- Rows ticked by default when the character's attendance is `checked-in`, unticked when `no-show` or when no attendance was recorded (with a hint), unticked when the character already has an award for the event.
- A default amount and reason (for example "Attended Winter Court 2026"), per-row overrides, and one award per ticked row on save.
- `awardedBy` and `awardedAt` stamped on the server for every award write (the archived change's deferred task 1.3).

### Out of Scope

- XP for between-event work (row `prg-xp-for-non-attendance`, deferred) and caps (row `prg-xp-caps`, deferred).
- Refusing awards to no-shows: the default follows attendance, the game master may override.

## Approach

A registered section component on a new EventDetail tab, backed by a game
master endpoint that validates the rows and creates the awards through the
existing object fetcher, so the GM-only RBAC on `xpAward` still applies. A
pre-write listener stamps provenance. Details in design.md.

## New Dependencies

None.

## Impact

- `src/views/EventXpAward.vue` (new, `kind: 'section'`); the EventDetail sidebar tab in `src/manifest.json` (in place).
- `lib/Controller/EventsController.php` and `appinfo/routes.php`: `GET /api/events/{id}/xp-award-roster`, `POST /api/events/{id}/xp-awards`.
- `lib/Service/XpAwardBatchService.php` (new); `lib/Listener/XpAwardProvenanceListener.php` (new).

## Cross-Project Dependencies

None.

## Risks

### Risk 1: Double awards
**Severity:** Medium. **Mitigation:** rows with an existing award for the event start unticked and show the award; the endpoint refuses a second award for the same event and character unless the request marks it as an extra award with a reason.

### Risk 2: Attendance not recorded
**Severity:** Low. **Mitigation:** rows without attendance start unticked with the hint "no check-in recorded", so a game master who skipped check-in still sees everyone and ticks them.

## Rollback Strategy

Remove the tab and routes; awards made stay as ordinary awards.

## Open Questions

None.
