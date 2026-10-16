---
kind: code
depends_on: [registration-intake-and-capacity]
---

# Proposal: registration-ticket-types-and-options

## Summary

A LARP weekend is not one price. Players pay more than crew, NPCs often pay
nothing, early sign-ups get a lower price, and catering is a choice with its
own cost. Larpinq has no price anywhere. This change lets a game master set
ticket types per event (with a role, a price, an optional sale window and
place limit), extra options such as a meal plan with a price, and codes that
unlock a reduced ticket. A player chooses a ticket type, options and an
optional code on their registration. Larpinq records what was chosen and the
listed prices; the total, VAT and payment are shillinq's (hydra ADR-107).

## Motivation

Three registration rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for all three: each has two or more competitors
rated yes.

**`reg-tickets`**, "Sell tickets with different prices, such as player, crew
and NPC." Larpinq rates it no. Matrix evidence: "grep -rniE "ticket|price" lib
src --include=*.php --include=*.vue --include=*.js: no hits". Two competitors
rate it yes:

- LarpManager: "larpmanager/models/registration.py:41-180 TicketTier (standard, staff, NPC, patron, reduced and more) and RegistrationTicket with price" (source read at main 36f23d3).
- pretix: "src/pretix/base/models/items.py:360 Item and :1106 ItemVariation carry their own prices, so player, crew and NPC tickets are separate products or variations" (source read at tag v2026.7.0).

**`reg-discount-codes`**, "Offer discount or early-bird prices." Larpinq rates
it no. Matrix evidence: "grep -rniE "discount" lib src --include=*.php
--include=*.vue --include=*.js: no hits". Two competitors rate it yes:

- LarpManager: "larpmanager/models/accounting.py:561-700 Discount and DiscountType, larpmanager/fixtures/feature.yaml:62 discount codes, reg_surcharges for late signups".
- pretix: "src/pretix/base/models/vouchers.py:128 Voucher codes with reduced prices, and src/pretix/base/models/discount.py:44,81-86 automatic discounts with available_from/until for early-bird windows".

**`reg-meal-choice`**, "Let players choose a meal plan or catering option when
they sign up." Larpinq rates it no. Matrix evidence: "there is no registration
schema in lib/Settings/larpinq_register.json; sign-up goes to the Nextcloud
Forms leaf ..., which could carry a meal question, but nothing in larpinq reads
or totals one". Three competitors rate it yes:

- LarpManager: "larpmanager/models/form.py:549-790 registration questions with priced, capacity-limited options usable for catering choices, plus the diet profile field (larpmanager/models/member.py:275-285)".
- LARP Portal: "Released February 2024: players select a meal plan (for example none, vegan or meat) during registration when the campaign configures it. https://larportal.com/new-housing-and-food-selections.php"
- pretix: "src/pretix/base/models/items.py:1407 ItemAddOn lets a ticket offer add-on products such as meal plans at checkout (src/pretix/presale/checkoutflow.py:483 AddOnsStep), and choice questions (items.py:1570) can ask a diet option".

The three are what a registration buys, chosen on one screen.

## Affected Projects

- [ ] Project: `larpinq`: ticket type, option and code schemas, the choices on the registration, and a per-event count of choices.

## Scope

### In Scope

- `ticketType` per event: name, role (player, crew, npc, other), listed price, currency, sale window (from, until), place limit, hidden (reachable only with a code).
- `registrationOption` per event: name, category (meal, other), listed price, place limit.
- `accessCode` per event: a code that unlocks one or more hidden ticket types, with a valid window and a maximum number of uses.
- On the registration: the chosen ticket type, options and code, and `lines`: the listed price of each choice, copied at the moment of choosing.
- Early bird as a ticket type with a sale window that ends; a later ticket type takes over.
- Place limits per ticket type and option, enforced with the event capacity of `registration-intake-and-capacity`.
- A count per event of registrations per ticket type and per option (for the kitchen).

### Out of Scope

- Totals, VAT, invoices and payment: `registration-payments-through-shillinq` hands the lines to shillinq, which computes and books (hydra ADR-107).
- Percentage discounts: a code unlocks a ticket type with its own price, which needs no arithmetic in larpinq.
- Fees as Pipelinq products (hydra ADR-107 decision 3 names Pipelinq for municipal fees). A LARP ticket price is kept as event data here; see design.md D1.

## Approach

Register fragments for the three schemas and the registration fields; the
existing `RegistrationService::decide()` also checks ticket and option place
limits; `lines` are written by the registration pre-write listener from the
chosen objects, so a client cannot set its own price. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/registration-ticket-types-and-options.json` (new).
- `lib/Service/RegistrationService.php` and `lib/Listener/RegistrationListener.php`: place limits per ticket type and option, code checks, `lines`.
- `src/manifest.d/registration-ticket-types-and-options.json` (new): ticket types, options and codes pages. `src/manifest.json`: EventDetail gains the three lists and a choices count (edited in place).

## Cross-Project Dependencies

None in this change. `registration-payments-through-shillinq` consumes `lines`.

## Risks

### Risk 1: A price changes after players chose it
**Severity:** Medium. **Mitigation:** `lines` copy the listed price when the player chooses; a later price change applies to new choices only, and the ticket type page says so.

### Risk 2: Code sharing
**Severity:** Low. **Mitigation:** each code has a maximum number of uses and a window; uses are counted from registrations, under the event lock.

## Rollback Strategy

Remove the fragments and the service checks. Choices stay on registrations.

## Open Questions

None.
