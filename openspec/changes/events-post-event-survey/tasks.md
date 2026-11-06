# Tasks: events-post-event-survey

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Data

- [ ] 1.1 Fragment: `event.feedbackForm`, `feedbackOpensAt`, `feedbackClosesAt`; `attendance.feedbackInvitedAt`, `feedbackRemindedAt` (REQ-EPS-001). Verify: `npm run check:register`; `npm run check:schema-l10n`.

## 2. Invitations

- [ ] 2.1 `EventFeedbackService` and `EventFeedbackJob` (daily, bounded pages): invitations to checked-in participants and one reminder (REQ-EPS-002, REQ-EPS-003). Verify: PHPUnit with a fixed clock: invited once, reminded once, never someone marked no-show; `appinfo/info.xml` lists the job.
- [ ] 2.2 `answered()` from Forms submissions per user, with the count-only fallback (REQ-EPS-004). Verify: PHPUnit against a fake Forms reader; a manual check on the dev instance's Forms version, noted in the PR.

## 3. Pages

- [ ] 3.1 EventDetail in `src/manifest.json`: the feedback fields, answered and not answered for game masters, and the link to the form's results (REQ-EPS-004). Verify: `npm run check:manifest`; Playwright `tests/e2e/event-feedback.spec.ts` sees 1 of 2 answered on "Summer Siege 2025".

## 4. Strings and docs

- [ ] 4.1 Dutch and English strings, including the notification and email (REQ-EPS-002). Verify: `npm run test:l10n`.
- [ ] 4.2 `docs/features/post-event-feedback.md` (ADR-010). Verify: the docs build renders it.
- [ ] 4.3 Point `openspec/specs/event-signup-to-forms-leaf/spec.md` at this change for the second form. Verify: the spec lists the change.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
