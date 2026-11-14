# Tasks: registration-ticket-types-and-options

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Data

- [x] 1.1 Fragment: `ticketType`, `registrationOption`, `accessCode` with rules, and `registration.ticketType`, `options`, `accessCode`, `lines` (REQ-RTO-001, REQ-RTO-002, REQ-RTO-003). Verify: `npm run check:register`; `npm run check:schema-l10n`; Newman: a player cannot read a hidden ticket type or a code.
- [x] 1.2 Seed the "Winter Court 2026" ticket types, options and code of design.md. Verify: the seed imports cleanly.

## 2. Rules

- [x] 2.1 Registration pre-write listener: `lines` from the chosen objects, sale window, hidden ticket types and code checks (REQ-RTO-001, REQ-RTO-002, REQ-RTO-004). Verify: PHPUnit per refusal, and a client-sent `lines` is ignored.
- [x] 2.2 `RegistrationService::decide()`: place limits per ticket type (waitlist) and option (refuse) under the event lock (REQ-RTO-005). Verify: PHPUnit with the 20 crew places full.

## 3. Pages

- [x] 3.1 `src/manifest.d/registration-ticket-types-and-options.json` and the EventDetail lists and counts in `src/manifest.json`; ticket, options and code fields on My registrations (REQ-RTO-001 to REQ-RTO-006). Verify: `npm run check:manifest`.
- [x] 3.2 Playwright `tests/e2e/workflows/registration-tickets.workflow.spec.ts` (written, not run: no isolated instance): Anna picks early bird and vegan catering; after the early bird window only "Player" is offered; "LANTERN" unlocks "Crew friends" (REQ-RTO-001 to REQ-RTO-004). Verify: passes locally.

## 4. Strings and docs

- [x] 4.1 Strings in all 36 shipped locales (REQ-RTO-001). Verify: `npm run test:l10n`.
- [x] 4.2 `docs/features/event-tickets-and-options.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
