---
kind: code
depends_on: []
---

# Proposal: players-attendance-history

## Summary

"How many events has Anna played, and which?" In larpinq the answer means
opening every character Anna ever played and every event on it. This change
puts the answer on the player page: a list of the events the player was
checked in at, with the character, the date and the status, newest first, and
a count of events attended.

## Motivation

One players row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). It is rated
partial and built with two competitors rated yes; the OpenSpec pass of
2026-09-27 decided `build` for the missing half.

**`ply-player-history`**, "See which events a player attended over the years."
Matrix evidence: "src/manifest.json:839-857 (player-characters object-list
shows the characters a player has played, columns name/type/approved, no event
or date columns); character.events (lib/Settings/larpinq_register.json:212-223)
would have to be opened per character to see event participation; no
player-scoped event/attendance rollup exists". Reached on: "PlayerDetail page
shows characters played; reaching their event history requires opening each
character individually".

- LarpManager (yes): "larpmanager/views/exe/member.py:489 exe_member_registrations lists a member's registrations across events, larpmanager/urls/user.py registrations page" (source read at main 36f23d3).
- pretix (yes): "src/pretix/presale/views/customer.py:394 OrderView lists a customer's orders across the organizer's events, and src/pretix/control/views/organizer.py:3072 CustomerDetailView shows the same to staff" (source read at tag v2026.7.0).
- LARP Portal (partial): "'View my Points' lists campaign, event date and reason for each award, which traces the events a player earned points at; no attendance history view is described. https://larportal.com/larp-portal-tips-122023.php"

The missing half is the player-level list across characters and years.

## Affected Projects

- [ ] Project: `larpinq`: a derived player reference on attendance records and an attendance list and count on the player page.

## Scope

### In Scope

- `attendance.player` and `attendance.eventStartDate`, derived from the character's player and the event's start date, so attendance can be listed per player and sorted by date.
- On PlayerDetail: "Events attended", attendance records with status checked in for the player, newest first, with event, character and date; and a count of events attended.
- The same list for the player themself when they open their own player page.

### Out of Scope

- Participation before check-in was recorded: events that only exist in `character.events` show on the character pages as today.
- A cross-player attendance trend (row `ins-attendance-trend`, deferred).

## Approach

Declarative: two `x-openregister-calculations` on the attendance schema and an
object-list and stats block on PlayerDetail. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/players-attendance-history.json` (new): the two derived attendance properties.
- `src/manifest.json`: PlayerDetail gains the list and the count (existing page, edited in place).
- `lib/Repair/BackfillAttendanceDerivedFields.php` (new): re-saves existing attendance records once so the derived values are filled.

## Cross-Project Dependencies

OpenRegister `x-openregister-references` and `x-openregister-calculations`
(already used on `character.ownerUid`).

## Risks

### Risk 1: Old attendance records have no derived values
**Severity:** Low. **Mitigation:** OpenRegister materialises calculations on write; a one-off re-save of existing attendance records (a repair step) fills them.

## Rollback Strategy

Remove the fragment and the page edits.

## Open Questions

None.
