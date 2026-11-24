---
kind: code
---

# Proposal: events-check-out-and-on-site-count

## Summary

During an event a game master needs to know who is on site right now: for safety, for meals, for a fire drill. larpinq records check-in and no-show, but nobody can be checked out, so the roster shows who arrived, not who is still there. LrEvenementInchecken draws a check-out per participant, "uitgecheckt za 22:10", and a "nu op locatie" count with the rule "Op locatie = ingecheckt en niet weer uitgecheckt". This change adds both.

## Motivation

Row evt-on-site-presence ("See who is on site right now, counting people who checked out again") was marked built, but its own evidence says there is no check-out. Read from code on 30 October 2026: `lib/Controller/EventsController.php` accepts the statuses `registered`, `checked-in` and `no-show` on `POST /api/events/{id}/attendance`, and `src/views/EventRoster.vue` offers Check in and No-show only. `specs/event-checkin-roster` has no check-out and no on-site count.

## Scope

### In scope

1. A game master checks a participant out and back in; the server stamps when and by whom.
2. The roster shows how many people are on site now and lists who checked out.

### Out of scope

- Check-out by QR scan: `events-qr-checkin` can add it once this status exists.

## Matrix rows

- evt-on-site-presence: See who is on site right now, counting people who checked out again.
