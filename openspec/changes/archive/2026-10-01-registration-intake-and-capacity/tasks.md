# Tasks: registration-intake-and-capacity

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 9. -->

## 1. Data

- [x] 1.1 Fragment: `registration` with rules, lifecycle on `status`, materialised uids and the character relation filter; `event.capacity`, `approvalRequired`, `signupForm` (REQ-RIC-001, REQ-RIC-006). Verify: `npm run check:register`; `npm run check:schema-l10n`.
- [x] 1.2 Seed the "Winter Court 2026" registrations of design.md. Verify: the seed imports cleanly.

## 2. Intake

- [x] 2.1 `FormSubmissionListener` for `FormSubmittedEvent`, behind `class_exists()` (REQ-RIC-001). Verify: `git grep "new FormSubmittedEvent"` in nextcloud/forms finds the dispatch; PHPUnit with the real event class: a known user, an unknown user, an anonymous submission, a second submission.

## 3. Capacity

- [x] 3.1 `RegistrationService::decide()` with the per-event lock and the D4 outcomes, called from the pre-write `RegistrationListener` (REQ-RIC-002, REQ-RIC-003, REQ-RIC-004). Verify: PHPUnit per outcome, and two concurrent creates for the last place give one accepted and one waitlisted.
- [x] 3.2 Post-write handler: sync `event.players[]` and promote the oldest waitlisted, deferred via `ListenerDeferralService`, with the lock release inline under `correctness` (REQ-RIC-003, REQ-RIC-005). Verify: PHPUnit with the real `ObjectUpdatedEvent` (`getNewObject()`); the hydra listener-work-placement gate passes.

## 4. Pages

- [x] 4.1 `src/manifest.d/registration-intake-and-capacity.json` (Registrations, registration detail, My registrations) and the EventDetail edits in `src/manifest.json` (REQ-RIC-004, REQ-RIC-006). Verify: `npm run check:manifest`.
- [x] 4.2 Playwright `tests/e2e/workflows/registration-intake.workflow.spec.ts`: a free place is accepted and the character joins the event, a full event waitlists, a cancelled place goes to the waiting list, approval and decline, the character choice, and the form answer (skipped without the forms app) (REQ-RIC-001 to REQ-RIC-006). Written; not run here (no isolated instance), the PR names it in the live check.

## 5. Strings and docs

- [x] 5.1 Dutch and English strings (REQ-RIC-004). Verify: `npm run test:l10n`.
- [x] 5.2 `docs/features/event-registration.md` and a pointer from `openspec/specs/event-signup-to-forms-leaf/spec.md` to this change (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
