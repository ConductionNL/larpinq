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

## Revised at build time (2 Oct 2026)

The code at HEAD differed from this design in five places; the build follows the code.

- **Where the checks live (D2, D4).** `status`, `cancelReason` and every new registration field are writable by game masters only on the object API (property `authorization.update`), so a player cannot cancel or hand over through it at all. The checks therefore live in `RegistrationChangeService` and `TransferOffers`, behind larpinq's endpoints (`/api/registrations/{id}/changes`, `/cancel`, `/participants`, `/transfer`, `/transfer/accept`, `/transfer/withdraw`), which write with the app's authority through the registration listeners. The pre-write listener is unchanged; the freed place still goes to the waiting list there. `CancellationPolicy` answers who may act and until when: the policy's `cancelBy`, else the event's start, else no limit.
- **Cancel reasons (D2).** The payments change already shipped `cancelReason` with `unpaid`, `player` and `organiser`; those are reused. `transferred-none` is dropped: a transfer never cancels a registration.
- **Settlement (D3).** It runs from `PaymentRequestListener` for every write that moves a paid registration to `cancelled` (`RegistrationSettlement`), so a game master's cancel through the lifecycle action settles too. `settlementChoice` keeps the player's choice; `player-chooses` without a choice refunds. Shillinq's `PaymentRequest.state` has no refunded or credited value (`pending`, `authorized`, `captured`, `captured_unapplied`, `failed`, `expired`, `voided`), so nothing shillinq emits today can set `refunded` or `credited`: a game master sets them by hand, and the listener half waits for shillinq (task 2.3, second half, stays open). The events carry `debtor`, `amount` and `currency` in shillinq's PaymentRequest shape plus the paid request's `subject`; the amount is the paid request's as shillinq holds it, else the registration's price lines.
- **Transfer state (D4).** A transfer is not a lifecycle transition: the status stays `accepted`. `transferStatus` (`offered`, `accepted`, `withdrawn`, `lapsed`) tracks it, `transferToUid` gives the offered player read access and the `transfer-offered` notification, and accepting also clears `bookedByUid`. The daily lapse is `TransferOffers::lapse()`, called by `RegistrationPaymentJob`.
- **Group bookings (D1).** The player schema has no email, so a new participant is a player with a name only, or a player the booker booked before. The booker's own registration gets the `bookingGroup` when the first participant is added.
- **Seeds.** The demo event carries the policy (cancel by 2026-11-25, player chooses) and Mila's cancelled, paid registration with credit requested. Sanne's transfer is not seeded: a pending offer would notify a demo account.
