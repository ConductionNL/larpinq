# Design: registration-cancel-transfer-refund

## Context

With `registration-intake-and-capacity` (registration, status, per-event lock,
waiting list promotion when a place frees up) and
`registration-payments-through-shillinq` (payment request per registration,
`paymentState`, the `PaymentRequestListener`), both in this pass.

- hydra ADR-107: refunds and balances are money, so shillinq holds them.
- hydra ADR-066: sibling leaves are read and append only; hydra ADR-041: a
  command to another app is a typed event it listens to.
- Shillinq development: object payment requests (`case-payment-requests`),
  provider webhook states; no refund or credit intake for object requests.

## Goals / Non-Goals

**Goals**: cancel one registration, including one of a group; transfer; a
settled refund or credit for paid cancellations.

**Non-Goals**: partial refunds, fees, moving between events.

## Decisions

### D1. Group bookings as linked registrations

`registration.bookingGroup` (uuid) and `bookedByUid`. On My registrations a
signed-in user can add a participant (an existing player they may pick, or a
new player with a name and email) to their booking group; each participant
gets their own registration with the same `bookingGroup`, decided by
`RegistrationService::decide()` like any other. Read rule: also where
`bookedByUid = $userId`. Alternative: one registration with a participant
list; rejected because capacity, payment and cancellation are per person.

### D2. Cancellation

Lifecycle transition `cancel` on `registration.status` from `pending`,
`accepted` or `waitlisted` to `cancelled`, with `cancelReason` (`by-player`,
`by-organiser`, `unpaid`, `transferred-none`) and `cancelledBy`. The pre-write
listener allows it for game masters always, and for the participant or the
booker until `event.cancellationPolicy.cancelBy`. The intake change's handler
then frees the place.

### D3. Settlement

When a cancelled registration has `paymentState: paid`, `settlement` is set by
the policy (`refund`, `credit`, `none`, or the player's choice when
`player-chooses`). `RegistrationService::settle()` dispatches
`RegistrationRefundRequested` or `RegistrationCreditRequested` (fields:
registration reference, payment request id, debtor, amount of the paid request)
and sets `settlement: refund-requested` or `credit-requested`. When shillinq
reports the refund as done (a payment request state it emits), the payments
listener sets `refunded` or `credited`.

### D4. Transfer

Transitions `offerTransfer` (sets `transferTo` = a player, `transferOfferedAt`)
and `acceptTransfer` (by the user whose player is `transferTo`: sets `player`,
clears `character`, records `transferredFrom`, `transferredAt`) and
`withdrawTransfer`. The place, ticket type, lines and payment stay with the
registration. The recipient then picks a character (intake requirement). An
offer older than 7 days, or past the cancel-by date, is withdrawn by the
payments daily job.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Policy, booking group, transfer and settlement fields | Declarative, register fragment | Properties. |
| Cancel and transfer as transitions | Declarative lifecycle | A state machine. |
| Who may cancel or accept, and when | Imperative, the registration pre-write listener | hydra ADR-031 exception 2: depends on the caller, the booker and the event's policy date. |
| Refund and credit | Imperative, typed events to shillinq | A command to another app (hydra ADR-041). |

## Seed data

"Winter Court 2026" policy: cancel by 2026-11-25, `player-chooses`. Joris
books himself and his daughter Mila (booking group); Mila cancels and her paid
EUR 85 goes to credit. Sanne offers her registration to Pieter, who accepts.

## Risks / Trade-offs

- [Shillinq half missing] Until shillinq receives the events, settlement stays
  at `refund-requested` or `credit-requested` and game masters settle outside
  larpinq; the list shows it.

## Migration

None.

## Open Questions

None.
