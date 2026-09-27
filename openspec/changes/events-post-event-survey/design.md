# Design: events-post-event-survey

## Context

Read at development `2af18d8`, with `registration-intake-and-capacity`.

- Events link Nextcloud Forms through the forms leaf
  (`lib/Settings/register.d/event-signup-to-forms-leaf.json`), and the spec
  `event-signup-to-forms-leaf` keeps form definitions and submissions in Forms.
  The intake change adds `event.signupForm` to tell the sign-up form apart; a
  second form id tells the feedback form apart the same way.
- OpenRegister's `FormsProvider` lists forms linked to an object with
  `submissionCount`.
- Attendance (`larping_attendance`, status `checked-in`) is written by the
  Check-in tab (`EventRosterService::recordAttendance()`); it records who was
  there.
- No `TimedJob` exists in larpinq yet; `registration-payments-through-shillinq`
  adds the first.

## Goals / Non-Goals

**Goals**: ask every participant who was there, once, remind once, show who
answered.

**Non-Goals**: storing answers in larpinq, anonymous feedback.

## Decisions

### D1. Fields

`event.feedbackForm` (integer), `feedbackOpensAt` (date-time, default the
event's `endDate`), `feedbackClosesAt` (date-time, default 21 days after
opening). `attendance.feedbackInvitedAt`, `feedbackRemindedAt` (date-time).

### D2. Invitations

`EventFeedbackJob` (daily) takes events whose feedback opened and has not
closed (bounded pages). For each attendance record with status `checked-in`
and no `feedbackInvitedAt`, `EventFeedbackService` resolves the character's
owner (`ownerUid`), sends a notification (`OCP\Notification\IManager`, subject
"Tell us about <event>") and an email (`OCP\Mail\IMailer`) with the form link,
and stamps `feedbackInvitedAt`. Five days before closing, owners who have not
answered get one reminder and `feedbackRemindedAt` is stamped.

Alternative: an `x-openregister-notifications` rule on attendance. Rejected
because the trigger is a moment in time, not a change to an object, and a job
that would write a field just to trigger a rule adds a write per participant
for nothing.

### D3. Who answered

`EventFeedbackService::answered(eventId)` reads the form's submissions per
user through the Forms API (the leaf lists forms and counts; per-user
submission ids come from Forms' `SubmissionMapper` via the leaf's `list` with
submissions) and matches them to the participants' `ownerUid`. The event page
shows, to game masters, counts of answered and not answered, a list of those
who have not answered, and a link to the form's results in Forms.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Form link and dates | Declarative, register fragment | Properties. |
| Invitations and the reminder at a moment in time | Imperative, a daily job | Scheduled bulk work, an ADR-031 listed exception. |
| Who answered | Imperative, a read over Forms submissions | Another app's data (Forms). |

## Seed data

"Summer Siege 2025" (ended) with feedback form "Summer Siege post-event
letter"; Anna and Sanne checked in; Anna answered.

## Risks / Trade-offs

- [Per-user submissions] If the installed Forms version cannot list submissions
  per user through the leaf, the answered list shows only the count, and the
  reminder goes to all invitees; the task verifies this against the Forms
  version in the dev environment.

## Migration

None.

## Open Questions

None.
