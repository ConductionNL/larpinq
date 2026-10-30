---
kind: code
depends_on: []
---

# Proposal: registration-intake-and-capacity

## Summary

A player fills in the sign-up form on an event page, and then nothing
happens: the form answer sits in Nextcloud Forms and a game master copies the
player into the event by hand. There is no place limit, no waiting list, no
approval and no way to say which character the player brings. This change
turns every sign-up into a registration: the player, the event, the chosen
character and a status (pending, accepted, waitlisted, declined, cancelled).
An event gets a capacity and an optional approval step, a full event puts new
sign-ups on a waiting list in the order they came in, and an accepted
registration puts its character in the event's participants.

## Motivation

Five rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for all five: each has two or more competitors
rated yes. The live spec `openspec/specs/event-signup-to-forms-leaf/spec.md`
already requires "Capacity and waiting-list ordering MUST stay in Larpinq",
from the archived change `2026-06-14-event-signup-to-forms-leaf`; no code backs
it. This change delivers that requirement.

**`reg-signup-form`**, "Let players sign up for an event through a form."
Larpinq rates it partial, built. Matrix evidence:
"lib/Settings/register.d/event-signup-to-forms-leaf.json
(event.configuration.linkedTypes: ["forms"]); src/manifest.json:1589-1660
(event-forms widget, type integration, integrationId forms);
src/views/ObjectDetail.vue:20,60 (event -> forms leaf)". The row note: "the same
spec's capacity/waitlist/confirmed-participant requirement has no code, so a
submission never automatically becomes a confirmed participant in
character.events[], a GM must add participants manually". Three competitors
rate it yes:

- LarpManager: "larpmanager/urls/event.py register routes, larpmanager/models/form.py:549-816 RegistrationQuestion, options and answers, larpmanager/urls/orga.py orga_registration_form" (source read at main 36f23d3).
- LARP Portal: "'Event registration functionality' is an explicit Campaign Module feature. https://larportal.com/features.php"
- pretix: "src/pretix/presale/checkoutflow.py:248-1521 checkout steps (customer, add-ons, questions, payment, confirm) are the sign-up form, with custom questions from src/pretix/base/models/items.py:1570" (source read at tag v2026.7.0).

**`evt-capacity`**, "Set a maximum number of participants for an event."
Larpinq rates it no. Matrix evidence: "grep -rniE "capacity" lib
--include=*.php: no hits; openspec/specs/event-signup-to-forms-leaf/spec.md
'Capacity and waiting-list ordering MUST stay in Larpinq' requirement has zero
backing code". Two competitors rate it yes:

- LarpManager: "larpmanager/models/event.py:170-190 max_pg, max_filler, max_waiting per event, per-ticket availability in larpmanager/models/registration.py:89".
- pretix: "src/pretix/base/models/items.py:1967 Quota caps how many tickets of the products it covers can be sold for an event or date".

**`reg-waitlist`**, "Put players on a waiting list when an event is full."
Larpinq rates it no. Matrix evidence: "grep -rniE "waitlist|waiting.?list" lib
--include=*.php: no hits, despite openspec/specs/event-signup-to-forms-leaf/spec.md
Requirement 'Capacity and waiting-list ordering MUST stay in Larpinq' and its
scenario 'Waiting list forms when capacity is reached'". Two competitors rate
it yes:

- LarpManager: "larpmanager/fixtures/feature.yaml:413 waiting feature, larpmanager/models/registration.py:47 Waiting tier, larpmanager/models/event.py max_waiting".
- pretix: "src/pretix/base/models/waitinglist.py:50 WaitingListEntry, joined via src/pretix/presale/views/waiting.py and managed in src/pretix/control/views/waitinglist.py, with vouchers sent when seats free up".

**`reg-choose-character`**, "Let a player pick which character they bring when
signing up." Larpinq rates it no. Matrix evidence: "the forms leaf
(reg-signup-form) is a generic NC Forms form with no larpinq-aware character
field, and no code binds a form submission to a specific character record".
Two competitors rate it yes:

- LarpManager: "larpmanager/views/user/character.py:890-944 participant picks and is assigned a character with locking and max checks, larpmanager/tests/playwright/character_choose_test.py".
- LARP Portal: "When registering, a PC can 'Pick the character that you are playing'. https://larportal.com/new-to-larp-portal.php"

**`reg-approve-registrations`**, "Review and approve each registration before
the player is let in or asked to pay." Larpinq rates it no. Matrix evidence:
"there is no registration or order object in lib/Settings/larpinq_register.json
... character approval exists (character.approved) but a registration is not an
object". Three competitors rate it yes:

- LarpManager: "larpmanager/urls/orga.py:366-376 orga_registration_requests with approve and reject, larpmanager/tests/playwright/registration_approval_process_test.py". Its changelog: https://github.com/LoSkana/larpmanager/commit/770544dfd2.
- LARP Portal: "Staff with logistics roles can 'Set up an Event, Approve Registrations, Assign Housing'. https://larportal.com/what-is-larp-portal.php"
- pretix: "src/pretix/base/models/items.py:619 require_approval on a ticket and src/pretix/base/models/orders.py:310 on the order, approved one by one or in bulk at src/pretix/control/urls.py:426,471".

All five are one record, the registration, and the rules around it.

## Affected Projects

- [ ] Project: `larpinq`: a registration schema, event capacity and approval settings, a listener on form submissions, a registration service that keeps capacity and the waiting list, and registration pages.

## Scope

### In Scope

- `registration` (slug `larping_registration`): event, player, character, status, submission reference, submitted and decided times, a waiting list position.
- `event.capacity` (optional) and `event.approvalRequired`, and `event.signupForm` (the Forms form id the event takes sign-ups from).
- A Forms submission on an event's sign-up form creates a registration for the submitting user's player.
- Without approval: accepted while there is a place, else waitlisted. With approval: pending until a game master accepts or declines; an accepted registration without a place becomes waitlisted.
- When a place frees up, the oldest waitlisted registration is accepted.
- An accepted registration adds its character to `event.players[]`; leaving accepted removes it.
- The player picks the character on their registration from their own active characters of the event's world.
- Pages: registrations per event for game masters, and "My registrations" for players.

### Out of Scope

- Prices, tickets and payment: `registration-ticket-types-and-options` and `registration-payments-through-shillinq`.
- Cancelling, transferring and refunding: `registration-cancel-transfer-refund`.
- Storing the form answers in larpinq: they stay in Forms (the existing requirement "Form definition and submissions MUST be owned by the forms leaf").

## Approach

The registration is a register object with row rules. A listener on Nextcloud
Forms' `FormSubmittedEvent` creates it; a `RegistrationService` called from a
post-write listener on registration writes keeps capacity, the waiting list
and `event.players[]` consistent. Details in design.md.

## New Dependencies

None. Nextcloud Forms is already the sign-up leaf.

## Impact

- `lib/Settings/register.d/registration-intake-and-capacity.json` (new): `registration`, and `event.capacity`, `approvalRequired`, `signupForm`.
- `lib/Listener/FormSubmissionListener.php`, `lib/Listener/RegistrationListener.php`, `lib/Service/RegistrationService.php` (new), registered in `lib/AppInfo/Application.php`.
- `src/manifest.d/registration-intake-and-capacity.json` (new): Registrations index, registration detail, My registrations.
- `src/manifest.json`: EventDetail gains a registrations list with status and a capacity stat (existing page, edited in place).

## Cross-Project Dependencies

Nextcloud Forms dispatches `OCA\Forms\Events\FormSubmittedEvent` from
`FormsService` (nextcloud/forms `lib/Service/FormsService.php:782`), with the
form and the submission (`getWebhookSerializable()`). The listener is
registered behind `class_exists()` so larpinq works without Forms.

## Risks

### Risk 1: Two sign-ups take the last place at once
**Severity:** Medium. **Mitigation:** the service counts accepted registrations and decides under a lock per event (`ILockingProvider`), so exactly one is accepted and the other waitlisted.

### Risk 2: A submitter has no player record
**Severity:** Medium. **Mitigation:** the registration is created with the submitting user's id and no player; game masters see it flagged "no player linked" and link or create the player, after which the player can pick a character. Anonymous form submissions are refused as registrations and logged.

### Risk 3: Manual edits of `event.players[]`
**Severity:** Low. **Mitigation:** game masters can still add participants by hand (the leaf's degradation requirement); such characters count against capacity as if accepted, and the registrations list shows them as "added by a game master".

## Rollback Strategy

Unregister the two listeners; registrations stay as data and `event.players[]`
keeps its last state.

## Open Questions

None.
