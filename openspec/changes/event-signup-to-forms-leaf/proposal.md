---
status: draft
---

# Event sign-up / waiting list via the forms leaf

## Why

The README advertises **Event Subscriptions** — "Handle registrations and
waiting lists; track player participation" (README line 55) and "Players
register for events" (README line 20). This feature is **planned, not yet
built** — there is no sign-up form, no waiting-list logic in the codebase today.

Because it does not exist yet, this is the cheapest possible place to apply
**hydra ADR-022**: build it as a consumed OR abstraction from day one rather
than as a bespoke sign-up form. OpenRegister exposes a **forms leaf** via the
integration registry (ADR-019). LarpingApp MUST implement event sign-up /
waiting-list intake through the forms leaf. Mirrors the DocuDesk PDF
integrate-don't-build precedent.

## What Changes

- Surface the OR **forms leaf** on the Event detail page to collect player
  sign-ups (and any per-event intake questions) instead of a hand-rolled form.
- Sign-up submissions feed the Event's `players[]` participation; the
  waiting-list ordering is derived from submission order. The forms leaf owns
  the form definition + submissions; LarpingApp owns the event-domain rules
  (capacity, who's confirmed vs waitlisted).
- Graceful degradation: when the forms leaf is unavailable, the sign-up surface
  is hidden and `players[]` can still be edited manually (existing behaviour),
  mirroring the PDF graceful-degrade pattern.

## Impact

- Affected specs: `event-signup-to-forms-leaf` (new). Relates to the
  `events-players` participation model (`players[]`).
- Affected code (apply phase, NOT here): `src/views/ObjectDetail.vue` (event
  type); event-domain capacity/waitlist logic (new, thin, in-app).
- Depends on: OR integration registry (ADR-019) exposing the forms leaf.
- No stat-engine impact.
