# Tasks: registration-payments-through-shillinq

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 9. -->

## 1. Data

- [x] 1.1 Fragment: `event.payBy`, `paymentRequired`; `registration.paymentState`, `paymentRequestId`, `paymentLink`, `paymentReference`, `paymentReminderAt`, `invoiceRequested`, `cancelReason`; the reminder notification rule (REQ-RPS-001, REQ-RPS-003). Verify: `npm run check:register`; `npm run check:schema-l10n`; hydra notification-dialect gate passes.

## 2. Request and state

- [x] 2.1 `RegistrationPaymentService::requestFor()` through the integration registry's `shillinq-payment-requests` leaf, called from the deferred registration handler (REQ-RPS-001). Verify: PHPUnit with a fake leaf: one create per accepted priced registration, none for zero lines, none twice.
- [x] 2.2 `PaymentRequestListener` on `ObjectUpdatedEvent` for shillinq `PaymentRequest` with a larpinq registration subject (REQ-RPS-002). Verify: PHPUnit with the real OpenRegister event class and `getNewObject()`; the listener-work-placement gate passes.
- [x] 2.3 Graceful mode without shillinq: manual payment state for game masters (REQ-RPS-006). Verify: PHPUnit with the leaf absent.

## 3. Reminders and expiry

- [x] 3.1 `RegistrationPaymentJob` (`TimedJob`, daily, bounded pages): reminder 3 days before, expiry one day after, and the leaf `list` reconciliation (REQ-RPS-003, REQ-RPS-004). Verify: PHPUnit with a fixed clock for each case; `appinfo/info.xml` lists the job; the repair-step-registration gate passes.

## 4. Pages

- [x] 4.1 Payment columns, filter and counts on EventDetail and the Registrations pages, and the player's payment block on My registrations, in `src/manifest.json` (REQ-RPS-005). Verify: `npm run check:manifest`.
- [ ] 4.2 Playwright `tests/e2e/workflows/registration-payments.workflow.spec.ts` (written 2 October; not run: no isolated instance with shillinq) against a shillinq test instance: Anna is accepted, sees her link and WC26-0001; a simulated capture marks her paid on the roster (REQ-RPS-001, REQ-RPS-002, REQ-RPS-005). Verify: passes locally with shillinq enabled.

## 5. Strings and docs

- [x] 5.1 Dutch and English strings, including the reminder message (REQ-RPS-003). Verify: `npm run test:l10n`.
- [x] 5.2 `docs/features/event-payments.md`: what larpinq does and what shillinq does (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.

Open until shillinq's side exists (design "Revised at build time"): an app may raise a request on the leaf, so every acceptance asks at once (for-ruben/shillinq-payment-request-leaf-app-caller.md); bank matching by reference and an invoice on request for rows `reg-bank-statement-match` and `reg-invoices`. Do not archive before then.
