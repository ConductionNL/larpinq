# Tasks: registration-cancel-transfer-refund

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 8. -->

## 1. Data

- [ ] 1.1 Fragment: `event.cancellationPolicy`, `registration.bookingGroup`, `bookedByUid`, `cancelReason`, `cancelledBy`, `transferTo`, `transferOfferedAt`, `transferredFrom`, `transferredAt`, `settlement`, and the transitions (REQ-RCT-001 to REQ-RCT-004). Verify: `npm run check:register`; `npm run check:schema-l10n`.

## 2. Rules

- [ ] 2.1 Pre-write guards for cancel, offer and accept (REQ-RCT-001, REQ-RCT-002, REQ-RCT-004). Verify: PHPUnit per caller (participant, booker, other player, game master) and before and after the cancel-by date.
- [ ] 2.2 Group bookings: adding a participant to a booking group through `decide()` (REQ-RCT-002). Verify: PHPUnit: cancelling one group member leaves the others accepted.
- [ ] 2.3 `settle()` and the two typed events; the payments listener sets `refunded` or `credited` (REQ-RCT-003). Verify: PHPUnit asserts the dispatched event and its fields; `git grep "new RegistrationRefundRequested"` finds the dispatch site.
- [ ] 2.4 Expiry of transfer offers in the daily payments job (REQ-RCT-004). Verify: PHPUnit with a fixed clock.

## 3. Pages

- [ ] 3.1 Cancel, offer transfer, accept transfer and add participant on My registrations and the registration detail, in `src/manifest.json` (REQ-RCT-001 to REQ-RCT-004). Verify: `npm run check:manifest`; Playwright `tests/e2e/registration-cancel-transfer.spec.ts` for Mila's cancel and Sanne's transfer.

## 4. Strings and docs

- [ ] 4.1 Dutch and English strings (REQ-RCT-001). Verify: `npm run test:l10n`.
- [ ] 4.2 `docs/features/cancel-and-transfer.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
