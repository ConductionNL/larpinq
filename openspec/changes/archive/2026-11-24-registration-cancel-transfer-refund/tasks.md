# Tasks: registration-cancel-transfer-refund

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 9. -->

## 1. Data

- [x] 1.1 Fragment: `event.cancellationPolicy`, `registration.bookingGroup`, `bookedByUid`, `cancelReason`, `cancelledBy`, `transferTo`, `transferOfferedAt`, `transferredFrom`, `transferredAt`, `settlement`, and the transitions (REQ-RCT-001 to REQ-RCT-004). Verify: `npm run check:register`; `npm run check:schema-l10n`.

## 2. Rules

- [x] 2.1 Pre-write guards for cancel, offer and accept (REQ-RCT-001, REQ-RCT-002, REQ-RCT-004). Verify: PHPUnit per caller (participant, booker, other player, game master) and before and after the cancel-by date.
- [x] 2.2 Group bookings: adding a participant to a booking group through `decide()` (REQ-RCT-002). Verify: PHPUnit: cancelling one group member leaves the others accepted.
- [x] 2.3 `settle()` and the two typed events; the payments listener sets `refunded` or `credited` (REQ-RCT-003). Verify: PHPUnit asserts the dispatched event and its fields; `git grep "new RegistrationRefundRequested"` finds the dispatch site.
  - Built 24 Nov on shillinq's agreed fleet events (larpinq#1163): `PaymentSettlementCommand` dispatches `OCA\Shillinq\Event\PaymentRefundRequestedEvent` or `PaymentCreditRequestedEvent` (looked up by name, fails closed without shillinq) after larpinq's own `RegistrationRefundRequested` / `RegistrationCreditRequested`; a credit shillinq books shows `credited` at once. `PaymentFollowUp::requestChanged()` maps shillinq's object event `refunded` / `credited` onto the registration's `settlement`. PHPUnit: `PaymentSettlementCommandTest`, `RegistrationSettlementTest`, `PaymentEdgeCasesTest::testShillinqsRefundOrCreditShowsOnTheRegistration`.
- [x] 2.4 Expiry of transfer offers in the daily payments job (REQ-RCT-004). Verify: PHPUnit with a fixed clock.

## 3. Pages

- [x] 3.1 Cancel, offer transfer, accept transfer and add participant on My registrations and the registration detail, in `src/manifest.json` (REQ-RCT-001 to REQ-RCT-004). Verify: `npm run check:manifest`; Playwright `tests/e2e/registration-cancel-transfer.spec.ts` for Mila's cancel and Sanne's transfer.
  - The spec file was written 21 Nov from the scenarios "Mila drops out" and "Sanne hands her place to Pieter"; not run here, it needs a live instance.

## 4. Strings and docs

- [x] 4.1 Dutch and English strings (REQ-RCT-001). Verify: `npm run test:l10n`.
- [x] 4.2 `docs/features/cancel-and-transfer.md` (ADR-010). Verify: the docs build renders it.

## 5. Shillinq

- [x] 5.1 Tell shillinq: listen to `RegistrationRefundRequested` and `RegistrationCreditRequested`, refund or book the credit, apply open credit to the debtor's next request (REQ-RCT-005), and emit a state larpinq can map to `refunded` or `credited` (drafted for Ruben: for-ruben/shillinq-registration-refund-and-credit-events.md). Larpinq then shows the part paid from credit on the registration (REQ-RCT-005). Shillinq's half is built (larpinq#1163, shillinq `receivables-object-request-refund-and-credit`) and task 2.3 now dispatches its commands and maps `refunded` / `credited`. Left here: showing the part paid from credit on the next registration (REQ-RCT-005).
  - Built 24 Nov (larpinq#1181): `RequestCreditShare` reads shillinq's `credit` settlements on the request (DebtorCreditService applies open credit at create) and the registration stores `paidFromCredit` and `amountOpen` in cents, shown on My registrations and Registrations with the `cents` formatter; a request shillinq settled whole by credit (`settledVia: credit`) marks the registration paid at once and in the daily pass. PHPUnit: `RequestCreditShareTest` (Mila's EUR 85 on EUR 95 shows 85 / 10); vitest `centsAmount.spec.js`.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
