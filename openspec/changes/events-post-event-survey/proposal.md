---
kind: code
depends_on: [registration-intake-and-capacity]
---

# Proposal: events-post-event-survey

## Summary

After a weekend the story team wants to know what each player did, what they
plan next and what went wrong; many groups call it the post-event letter.
Larpinq has no way to ask. This change lets a game master link a feedback
form (a Nextcloud Form, like the sign-up form) to an event, invites every
participant who was checked in when the event ends, reminds those who have
not answered, and shows the game master how many answered with a link to the
answers.

## Motivation

One events row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build`: two competitors rate it yes.

**`evt-post-event-survey`**, "Collect a feedback letter or survey from each
participant after an event." Larpinq rates it no. Matrix evidence:
"lib/Settings/larpinq_register.json:39-905 (the ten schemas are character,
player, ability, skill, item, condition, effect, event, setting, xpAward; none
holds a post-event letter or survey); grep -rniE 'survey|feedback|post.?event
letter' lib src: no hits".

- LarpManager (yes): "larpmanager/fixtures/feature.yaml:195-205 debrief feature: organiser-defined questions each participant fills in after the event, larpmanager/urls/orga.py:1501 orga_debrief_answers, larpmanager/tests/playwright/debrief_matchmaker_auto_save_test.py" (source read at main 36f23d3).
- LARP Portal (yes): "'Manage Post Event Letters (Surveys)'; players submit PELs and addendums, staff search them by question or keyword. https://larportal.com/features.php , https://larportal.com/maximize-impact-with-pel-responses.php"
- MyLARP (partial): "Business tools include 'surveys and graphs'; tying a survey to an event is not described. https://mylarp.com"

## Affected Projects

- [ ] Project: `larpinq`: a feedback form link on the event, invitations and reminders to checked-in participants, and an answered count on the event page.

## Scope

### In Scope

- `event.feedbackForm` (a Nextcloud Forms form id), `feedbackOpensAt` (default: the event's end), `feedbackClosesAt`.
- When feedback opens, each participant with attendance `checked-in` gets a Nextcloud notification and an email with the form link; 5 days before it closes, those who have not answered get one reminder.
- Who answered is read from the form's submissions by user; the event page shows answered and not answered for game masters, and links to the form's results in Nextcloud Forms.
- The form, the questions and the answers stay in Nextcloud Forms (the same rule as the sign-up form).

### Out of Scope

- Copying answers into larpinq objects, or linking an answer to a plot.
- Anonymous feedback: the invitation list needs to know who answered.

## Approach

The event carries the form id. A daily `TimedJob` opens feedback and sends
invitations and the reminder through Nextcloud's notification and mail
services. The answered list reads the form's submissions through the Forms
leaf that the event already has. Details in design.md.

## New Dependencies

None. Nextcloud Forms is already the sign-up leaf.

## Impact

- `lib/Settings/register.d/events-post-event-survey.json` (new): the event fields and `feedbackInvitedAt`, `feedbackRemindedAt` on attendance.
- `lib/BackgroundJob/EventFeedbackJob.php` and `lib/Service/EventFeedbackService.php` (new), `appinfo/info.xml`.
- `src/manifest.json`: EventDetail gains the feedback fields and an answered count (in place).

## Cross-Project Dependencies

Nextcloud Forms (`nextcloud/forms`): the form and its submissions per user; the
OpenRegister forms leaf (`FormsProvider`) already lists forms linked to the
event and their submission counts.

## Risks

### Risk 1: Players get the invitation twice
**Severity:** Low. **Mitigation:** `feedbackInvitedAt` and `feedbackRemindedAt` on the attendance record make each send happen once.

### Risk 2: The form accepts answers from anyone with the link
**Severity:** Low. **Mitigation:** the game master's form settings decide; the design recommends "only logged-in users", which is what makes the answered list possible, and the event page warns when the form allows anonymous answers.

## Rollback Strategy

Unregister the job; the fields stay unused.

## Open Questions

None.
