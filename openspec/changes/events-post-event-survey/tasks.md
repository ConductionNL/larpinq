# Tasks: events-post-event-survey

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Data

- [x] 1.1 Fragment: `event.feedbackForm`, `feedbackOpensAt`, `feedbackClosesAt`; `attendance.feedbackInvitedAt`, `feedbackRemindedAt` (REQ-EPS-001). Verify: `npm run check:register`; `npm run check:schema-l10n`. Built 14 Nov: `lib/Settings/register.d/events-post-event-survey.json`; the eight new schema strings are in all 36 locales (schema-l10n stays at its baseline).

## 2. Invitations

- [x] 2.1 `EventFeedbackService` and `EventFeedbackJob` (daily, bounded pages): invitations to checked-in participants and one reminder (REQ-EPS-002, REQ-EPS-003). Verify: PHPUnit with a fixed clock: invited once, reminded once, never someone marked no-show; `appinfo/info.xml` lists the job. Built 14 Nov: plus `FeedbackDelivery` (notification and mail with the form link), `Notifier`, and `FeedbackAnswers`. 15 Nov: a stamp writes the whole attendance record back, since a save replaces the object.
- [x] 2.2 `answered()` from Forms submissions per user, with the count-only fallback (REQ-EPS-004). Verify: PHPUnit against a fake Forms reader; a manual check on the dev instance's Forms version, noted in the PR. Built 15 Nov: `FormsFeedbackAnswers` reads Forms' `SubmissionMapper::findByForm()` and returns the named submitters once each, leaving out anonymous ones; it returns null when Forms is absent or fails, which is the count-only fallback. It is bound to `FeedbackAnswers` and tested by `FormsFeedbackAnswersTest` against a fake reader. The manual check on the dev instance's Forms version is left: it needs a live instance.

## 3. Pages

- [x] 3.1 EventDetail in `src/manifest.json`: the feedback fields, answered and not answered for game masters, and the link to the form's results (REQ-EPS-004). Verify: `npm run check:manifest`; Playwright `tests/e2e/event-feedback.spec.ts` sees 1 of 2 answered on "Summer Siege 2025". Built 15 Nov: `GET /api/events/{id}/feedback` (`EventFeedbackController`, game masters only) returns `EventFeedbackService::summary()` and the results link. The EventDetail page has a **Feedback** tab (`EventFeedback.vue`) that shows the window, "1 of 2 answered", who has not answered, and the link to the results. The feedback fields show in the event's data widget. `npm run check:manifest` reports only the four page types development reports. The Playwright run is left: it needs a live instance.

## 4. Strings and docs

- [x] 4.1 Dutch and English strings, including the notification and email (REQ-EPS-002). Verify: `npm run test:l10n`. Built 15 Nov: the tab, notification and mail strings are in English and the 36 other shipped locales. The browser catalogues `l10n/*.js` are regenerated; `npm run test:l10n` passes.
- [x] 4.2 `docs/features/post-event-feedback.md` (ADR-010). Verify: the docs build renders it. Built 15 Nov.
- [x] 4.3 Point `openspec/specs/event-signup-to-forms-leaf/spec.md` at this change for the second form. Verify: the spec lists the change. Built 15 Nov.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
