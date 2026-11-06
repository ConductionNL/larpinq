# Design: registration-ticket-types-and-options

## Context

Read at development `2af18d8`, with `registration-intake-and-capacity`
(registration, `RegistrationService::decide()` under a per-event lock, the
registration pre-write listener).

- No price, ticket or discount exists in larpinq (`grep -rniE
  "ticket|price|discount" lib src` finds nothing).
- hydra ADR-107: "Shillinq is the only general ledger"; "Domain apps classify;
  they do not aggregate ... MUST NOT sum money, hold a ledger-shaped array";
  decision 3 gives municipal fees to Pipelinq as products.
- EventDetail (`src/manifest.json`) lists roster, XP awards and leaves; new
  lists are in-place edits there.

## Goals / Non-Goals

**Goals**: priced choices per event, early bird and codes, meal plans with
counts, limits on scarce places.

**Non-Goals**: totals, VAT, payment, percentage arithmetic.

## Decisions

### D1. Listed prices are event data; totals are not

A ticket type and an option carry a listed price (`amount` in minor units,
`currency`, default EUR). That is classification: what this registration
buys and at which list price. Larpinq never adds lines up, applies VAT or
keeps a balance; `registration-payments-through-shillinq` sends the lines to
shillinq, which computes the amount due. Alternative: model tickets as
Pipelinq products, as ADR-107 decision 3 does for municipal fees. Rejected for
now: a LARP group running larpinq should not need a CRM app installed to sell
a weekend; the lines carry what shillinq needs either way, and moving prices
to Pipelinq later changes only where the list price is read.

### D2. Schemas

- `ticketType` (slug `larping_ticket_type`): `event`, `name`, `role`
  (`player`, `crew`, `npc`, `other`), `amount`, `currency`, `saleFrom`,
  `saleUntil`, `placeLimit`, `hidden` (boolean), `order`.
- `registrationOption` (slug `larping_registration_option`): `event`,
  `name`, `category` (`meal`, `other`), `amount`, `currency`, `placeLimit`.
- `accessCode` (slug `larping_access_code`): `event`, `code`, `unlocks`
  (ticket type uuids), `validFrom`, `validUntil`, `maxUses`.

Rules: read by `larpers` for ticket types and options that are not hidden;
hidden ticket types and codes by `gamemasters`; write by `gamemasters`.

### D3. Choices on the registration

`registration` gains `ticketType`, `options[]`, `accessCode` (the typed code,
stored as the matched code's uuid) and `lines[]` (`{kind: ticket|option,
ref, name, amount, currency}`). The registration pre-write listener fills
`lines` from the chosen objects and ignores `lines` sent by a client. It
refuses: a ticket type outside its sale window, a hidden ticket type without a
valid code that unlocks it, a code past its window or its uses, a choice from
another event.

### D4. Place limits

`RegistrationService::decide()` also counts accepted registrations per ticket
type and per option. A registration whose ticket type is full is waitlisted
like a full event; a full option is refused at choice time with a message,
since an option is not worth a waiting place.

### D5. Counts for the organisers

EventDetail (in place): a stats block counting accepted registrations per
ticket type and per option (a declarative count, hydra ADR-031 and ADR-058),
so the kitchen knows how many vegan meals to cook.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Ticket types, options, codes | Declarative, register fragment | Schemas and row rules. |
| Copying list prices into `lines`, code and window checks | Imperative, the existing registration pre-write listener | hydra ADR-031 exception 2: the valid registration depends on three other objects and the moment of choice. |
| Place limits | Imperative, the existing `decide()` under the event lock | Concurrency. |
| Counts | Declarative, stats widgets | Counts over one schema. |

## Seed data

"Winter Court 2026": ticket types "Player early bird" (EUR 85, until
2026-11-01), "Player" (EUR 110), "Crew" (EUR 45, 20 places), "NPC" (EUR 0),
"Crew friends" (EUR 30, hidden); options "Full catering, meat" (EUR 35),
"Full catering, vegan" (EUR 35); code "LANTERN" unlocking "Crew friends", 5
uses. Anna chooses "Player early bird" and the vegan catering.

## Risks / Trade-offs

- [Currency] One currency per event; mixed currencies are refused.

## Migration

None.

## Open Questions

None.

## Revised at build time (1 Oct 2026)

Built on registration-intake-and-capacity (#815, landed as `96cc1836`).

- Players cannot read hidden ticket types or codes through the object API
  (the read rules hide them), so the registration page asks larpinq:
  `GET /api/registrations/{id}/offer?code=` returns the ticket types and
  options on offer for that registration (a hidden one only with a valid
  code), and `GET /api/events/{id}/choices` returns the counts for game
  masters. Both are guarded in `RegistrationChoicesController`.
- `registration.code` is the plain string a player types; `accessCode` is the
  matched code object, set by larpinq. Codes match without regard to case.
- The event's Choices tab is a section (`EventChoiceCounts.vue`) over the
  counts endpoint, not a stats widget: the counts are per ticket type and
  option, a dynamic list a fixed stats block cannot express. It shows counts
  only, never a money total.
- The rules live in `TicketChoiceService` (lines from the chosen objects,
  sale window, hidden types, codes, one currency) called from the existing
  registration pre-write listener; place limits per ticket type sit in
  `RegistrationService::decide()` under the event lock, and a full option is
  refused there.
- The spec delta is folded into `openspec/specs/event-registration/spec.md`
  as REQ-RTO-001 to REQ-RTO-006.
