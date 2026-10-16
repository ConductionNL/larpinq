---
kind: code
depends_on: [registration-intake-and-capacity, registration-payments-through-shillinq]
---

# Proposal: registration-cancel-transfer-refund

## Summary

Plans change. A player falls ill, a parent signs up two children and one
drops out, a player hands their place to a friend. Larpinq has no
registration to cancel, transfer or refund. This change adds cancelling a
single registration (also one from a group booking) under the event's
cancellation policy, transferring a registration to another player who
accepts it, and settling the money of a cancelled, paid registration as a
refund or as credit for a later event. Refunds and credit balances are kept
by shillinq (hydra ADR-107); larpinq asks for them and shows the outcome.

## Motivation

Four registration rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for all four: three have two competitors rated
yes, and `reg-partial-cancel` has a feature request plus one competitor yes.

**`reg-refunds`**, "Refund a cancelled registration." Larpinq rates it no.
Matrix evidence: "grep -rniE "refund" lib src ...: no hits".

- LarpManager (yes): "larpmanager/models/accounting.py:789-832 RefundRequest, larpmanager/urls/orga.py orga_cancellation_refund, larpmanager/fixtures/feature.yaml:776 refund" (source read at main 36f23d3).
- pretix (yes): "src/pretix/control/views/orders.py:1086 OrderRefundView creates refunds to the original payment method or a gift card, stored as src/pretix/base/models/orders.py:2128 OrderRefund; buyers can self-cancel via src/pretix/presale/views/order.py:1014 OrderCancel" (source read at tag v2026.7.0).

**`reg-player-credit`**, "Keep a credit balance for a player that pays for a
future event, such as after a cancellation." Larpinq rates it no. Matrix
evidence: "there is no registration or order object ... and payment is not
modelled".

- LarpManager (yes): "larpmanager/fixtures/feature.yaml:709 credits feature, credits usable for registration fees and redeemable (larpmanager/models/accounting.py:402-446 credit assignments), larpmanager/tests/playwright/credits_readonly_event_test.py".
- pretix (yes): "src/pretix/base/models/giftcards.py:64,83 GiftCard linked to a customer account and offered at checkout; refunds can go to a gift card (src/pretix/control/views/orders.py:1086)". Its changelog: https://pretix.eu/about/en/blog/20251030-release-2025-9/.

**`reg-transfer`**, "Transfer a registration to another person when a player
can no longer come." Larpinq rates it no. Matrix evidence: "there is no
registration object in lib/Settings/larpinq_register.json:39-960, so there is
nothing to transfer; a GM can edit event.players by hand".

- LarpManager (yes): "larpmanager/urls/orga.py:396 orga_registration_transfer with preview and confirm, larpmanager/tests/playwright/registration_transfer_test.py". Its changelog: https://github.com/LoSkana/larpmanager/commit/e409393382.
- pretix (yes): "src/pretix/base/settings.py:1929 allow_modifications and :2042 change_allow_attendee let the buyer or the attendee change the ticket holder's name and email (src/pretix/presale/views/order.py:942 OrderPositionModify); staff can do the same in the backend order view".

**`reg-partial-cancel`**, "Cancel one participant from a group registration
without cancelling the others." Larpinq rates it no. Matrix evidence: "there is
no registration or group-booking object (lib/Settings/larpinq_register.json:765-905:
event.players is a plain list), so there is no group registration to cancel part
of". Demand: a pretix feature request, https://github.com/pretix/pretix/issues/1791.

- pretix (yes): "src/pretix/base/services/orders.py:1587,1859 OrderChangeManager.cancel removes one position from an order, used by staff via src/pretix/control/urls.py:424 order change and by buyers via src/pretix/presale/urls.py:163 OrderPositionChange when allowed (src/pretix/base/settings.py:2055)".
- LarpManager (partial): "each participant holds their own Registration and can cancel alone (larpmanager/views/user/registration.py:860-904 unregister); extra tickets bought for others are just a count on the buyer's registration".

The four are what happens to a registration after it was made.

## Affected Projects

- [ ] Project: `larpinq`: a cancellation policy per event, group bookings, cancel and transfer transitions on the registration, and refund and credit requests to shillinq.
- [ ] Project: `shillinq` (not specified here, reported to the coordinator): receiving a refund request and a credit request for an object payment request, and applying a debtor's credit to a later request.

## Scope

### In Scope

- `event.cancellationPolicy`: cancel-by date, and what a paid cancellation gets (`refund`, `credit`, `player-chooses`, `none`).
- Group bookings: a signed-in user adds registrations for other players to their own sign-up; each participant keeps their own registration, linked by a booking group, so each can be cancelled alone.
- Cancel: the booker or the participant cancels one registration before the cancel-by date; a game master can always cancel. The place frees up for the waiting list.
- Settlement of a paid cancellation: a refund request or a credit request to shillinq, as the policy says, recorded on the registration.
- Transfer: the participant offers their registration to another player; the other player accepts and picks a character; the place, ticket and payment stay with the registration.

### Out of Scope

- Partial refunds and fees withheld on cancellation: the policy is all or nothing in this change.
- Moving a registration to another event.

## Approach

Lifecycle transitions on the registration (cancel, offer transfer, accept
transfer) with a guard in the existing registration pre-write listener.
Refund and credit requests are commands to shillinq sent as typed events
(hydra ADR-041), because the shillinq leaf is read and append only (hydra
ADR-066). Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/registration-cancel-transfer-refund.json` (new): policy fields, booking group, transfer and settlement fields, lifecycle transitions.
- `lib/Listener/RegistrationListener.php` and `lib/Service/RegistrationService.php`: guards and the settlement step.
- `lib/Event/RegistrationRefundRequested.php`, `lib/Event/RegistrationCreditRequested.php` (new).
- `src/manifest.json`: actions on the registration detail and My registrations (in place).

## Cross-Project Dependencies

Shillinq holds refunds and credit balances (hydra ADR-107). Its open change
`case-payment-requests` and spec `ar-invoice-payment-links` cover requesting
and capturing a payment on an object; nothing on shillinq development yet
receives a refund or credit request for an object payment request. That half
is reported to the coordinator: listen to the two larpinq events (or an agreed
fleet event), refund through the provider or book a credit note on the debtor,
and apply open credit to the debtor's next payment request.

## Risks

### Risk 1: A refund is requested and never made
**Severity:** Medium. **Mitigation:** the registration records `settlement: refund-requested` with the time; the game master's list shows requests older than 7 days; shillinq's later capture of the refund sets `refunded` through the payments listener.

### Risk 2: A transfer to someone who never accepts
**Severity:** Low. **Mitigation:** an offer expires after 7 days or at the cancel-by date, whichever is first; the registration stays with the original participant.

## Rollback Strategy

Remove the transitions and the events; registrations keep their states.

## Open Questions

None.
