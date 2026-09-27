# Design: registration-payments-through-shillinq

## Context

Read at larpinq development `2af18d8` and shillinq development (27 September
2026), with `registration-intake-and-capacity` and
`registration-ticket-types-and-options` (registration, status, `lines`).

- Larpinq has no payment code (`grep -rniE "payment|invoice" lib src`: no
  hits) and no `lib/BackgroundJob/` directory.
- hydra ADR-107: shillinq is the only ledger; a domain app never books
  income, classifies but does not aggregate. hydra ADR-066: a sibling app
  contributes read and append leaves; hydra ADR-041: cross-app commands go by
  typed events, never by calling another app's services.
- Shillinq: `lib/Integration/PaymentRequestLeafProvider.php` (leaf
  `shillinq-payment-requests`): `list(register, schema, objectId)` returns the
  requests on a host object with state, amount, type and `paymentLink`;
  `create(register, schema, objectId, payload)` appends a pending
  `PaymentRequest` with `subjectKind = object`, `requestType`, `amount`,
  `currency`, `description`, `debtor`, `dueAt` (open change
  `case-payment-requests`, REQ-SOPR-001 and REQ-SOPR-003). At most one pending
  request per subject and `requestType`. States run from `pending` to
  `captured` through the provider webhook (`ar-invoice-payment-links`).
- `event.players[]` and the registrations list are the participant views.

## Goals / Non-Goals

**Goals**: a payment request per accepted, priced registration; the state on
the registration; reminders and expiry; a reference for bank transfers.

**Non-Goals**: providers, ledger, invoices and receipts as documents, refunds,
totals across registrations.

## Decisions

### D1. When a request is made

`RegistrationPaymentService::requestFor(registration)` runs in the deferred
post-write handler when a registration becomes `accepted`, the event has
`paymentRequired`, and the registration's `lines` add up to more than zero.
It calls the leaf's `create` through OpenRegister's integration registry with:
`requestType: other`, `amount` = the sum of the registration's own lines,
`currency`, `description` "<event name>, <player name>, <reference>",
`debtor` = the player (semantic reference to the player object, ADR-048),
`dueAt` = the pay-by date, and `invoiceRequested` from the registration. It
stores the returned request id, `paymentLink` and `paymentReference` on the
registration and sets `paymentState: open`. A registration with lines of zero
gets `paymentState: not-needed`.

The reference is `<event code>-<4 digit sequence>` (for example
`WC26-0042`), unique per event, printed for a bank transfer.

### D2. Reading the state back

`PaymentRequestListener` listens to OpenRegister's `ObjectUpdatedEvent` and
reacts only to shillinq's `PaymentRequest` schema with a `subject` in the
larpinq register and schema `registration` (declared at the registration site,
hydra ADR-078 rule 4; deferred, rule 1). `captured` sets `paid`; `failed` or
`expired` keeps `open` and notes it. The daily job re-reads the leaf `list` for
open registrations as a safety net.

### D3. Reminders and expiry

`RegistrationPaymentJob` (`TimedJob`, daily) finds registrations with
`paymentState: open` (bounded pages, hydra ADR-058):

- 3 days before the pay-by date, it sets `paymentReminderAt`; an
  `x-openregister-notifications` rule on `registration` (trigger: field
  `paymentReminderAt` set) sends a Nextcloud notification and an email to
  `playerUid` with the payment link and reference.
- One day after the pay-by date, still `open`: status `cancelled` with
  `cancelReason: unpaid`, `paymentState: expired`. The intake change's handler
  then frees the place and promotes the waiting list.

### D4. Bank transfers

The reference is part of the request's description in shillinq, so a transfer
that quotes it can be matched by a shillinq matching rule
(`bookkeeping-bank-reconciliation`). Matching an object payment request by
reference is assumed in shillinq and reported as a sibling half; when shillinq
marks the request captured, D2 sets the registration paid.

### D5. Pages

EventDetail and the Registrations index (in place): columns payment state and
reference, a filter on payment state, a stats entry counting paid and open
registrations (counts, no money). My registrations: payment state, link,
reference and pay-by date. When shillinq is not installed, events with
`paymentRequired` show "Payment is handled outside larpinq" and game masters
set `paymentState` by hand.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Payment fields | Declarative, register fragment | Properties. |
| Reminder message | Declarative, notification rule on a field | The notification engine sends it. |
| Creating the request | Imperative, a leaf `create` from a deferred handler | An external integration (another app's leaf). |
| State from shillinq | Imperative, object event listener | Reacts to another app's objects. |
| Reminder timing and expiry | Imperative, a daily `TimedJob` | Scheduled bulk work, an ADR-031 listed exception. |

## Seed data

"Winter Court 2026" with `paymentRequired` and pay-by 2026-11-20: Anna's
registration open with reference WC26-0001; Sanne's paid; Joris's
not-needed (NPC ticket at EUR 0).

## Risks / Trade-offs

- [Two sources of truth] The state on the registration is a copy; shillinq's
  request is authoritative. The daily reconciliation keeps the copy honest.
- [Shillinq absent] Handled by hand as today.

## Migration

None.

## Open Questions

None.
