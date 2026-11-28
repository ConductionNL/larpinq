# event-registration Specification

## Purpose

A sign-up on an event becomes a registration with a status, the event keeps
its capacity with a waiting list, game masters can approve, and the player
says which character they bring. From larpinq matrix rows `reg-signup-form`,
`evt-capacity`, `reg-waitlist`, `reg-choose-character` and
`reg-approve-registrations`. It delivers the requirement "Capacity and
waiting-list ordering MUST stay in Larpinq" of the `event-signup-to-forms-leaf`
spec. A registration also records the ticket type, options and code a player
chose, at the listed prices (rows `reg-tickets`, `reg-discount-codes` and
`reg-meal-choice`).

## Requirements

### Requirement: A form sign-up becomes a registration (REQ-RIC-001)

When a signed-in user submits the sign-up form of an event, larpinq SHALL
create one registration for that event with the user, their player when one is
linked to their account, and the submission's id. The form answers MUST stay
in Nextcloud Forms. An anonymous submission MUST NOT create a registration.

#### Scenario: Anna signs up for the winter event

- GIVEN event "Winter Court 2026" takes sign-ups from form "Winter Court sign-up"
- AND Anna's account is linked to player "Anna de Vries"
- WHEN Anna submits the form on the event page
- THEN a registration of "Anna de Vries" for "Winter Court 2026" exists
- AND her answers are only in Nextcloud Forms

### Requirement: An event has a capacity (REQ-RIC-002)

A game master SHALL be able to set a capacity on an event. Places taken MUST
be counted as accepted registrations plus participants a game master added by
hand, and larpinq MUST NOT accept more registrations than the capacity, also
when two sign-ups arrive at the same moment.

#### Scenario: The last place

- GIVEN "Winter Court 2026" has capacity 3 and 2 places taken, without approval
- WHEN Sanne and Pieter submit the form at the same moment
- THEN one of them is accepted and the other is waitlisted

### Requirement: A full event keeps a waiting list in order (REQ-RIC-003)

A registration that finds no free place SHALL be waitlisted with its position
by submission time. When a place frees up, the oldest waitlisted registration
MUST be accepted.

#### Scenario: A place frees up

- GIVEN Pieter is first on the waiting list of "Winter Court 2026"
- WHEN an accepted registration of the event is cancelled
- THEN Pieter's registration becomes accepted

### Requirement: Game masters approve registrations (REQ-RIC-004)

On an event that requires approval, a new registration SHALL be pending until a
game master accepts or declines it on the registration page. Accepting MUST
give a place when one is free and put the registration on the waiting list
otherwise.

#### Scenario: A game master declines a sign-up for a retired character

- GIVEN "Winter Court 2026" requires approval and Karel's registration is pending
- WHEN a game master declines it on the registration page
- THEN Karel's registration shows status declined and takes no place

### Requirement: Accepted registrations are the participants (REQ-RIC-005)

When a registration with a character becomes accepted, larpinq SHALL add the
character to the event's participants; when it leaves accepted, larpinq SHALL
remove the character, so the roster, run sheet and check-in follow the
registrations.

#### Scenario: The roster follows

- GIVEN Anna's registration with "Mirela the Wanderer" is accepted
- WHEN a game master opens the Check-in tab of "Winter Court 2026"
- THEN "Mirela the Wanderer" is on the roster

### Requirement: The player picks the character they bring (REQ-RIC-006)

A player SHALL be able to choose, on their own registration, one of their own
active characters in the event's world. A player MUST NOT be able to choose a
character that is not theirs, retired or dead.

#### Scenario: Anna chooses Mirela

- GIVEN Anna owns active character "Mirela the Wanderer" in Aldmoor and retired "Old Captain Harrow" belongs to Karel
- WHEN Anna opens her registration on "My registrations"
- THEN the character choice offers "Mirela the Wanderer" and not "Old Captain Harrow"

### Requirement: Events offer ticket types with a role and a listed price (REQ-RTO-001)

A game master SHALL be able to define ticket types per event with a name, a
role (player, crew, npc, other), a listed price, an optional sale window and an
optional place limit. A player MUST choose one ticket type on their
registration, and larpinq SHALL record the listed price at the moment of
choosing.

#### Scenario: Anna picks a player ticket

- GIVEN "Winter Court 2026" offers "Player" at EUR 110 and "Crew" at EUR 45
- WHEN Anna chooses "Player" on her registration
- THEN her registration records ticket type "Player" with a line of EUR 110

### Requirement: Early bird ends on time (REQ-RTO-002)

A ticket type with a sale window SHALL be offered only inside that window, and
a choice of it outside the window MUST be refused.

#### Scenario: Too late for the early bird

- GIVEN "Player early bird" is on sale until 2026-11-01
- WHEN Pieter opens his registration on 2026-11-02
- THEN "Player early bird" is not offered and "Player" is

### Requirement: Codes unlock hidden ticket types (REQ-RTO-003)

A game master SHALL be able to create codes that unlock hidden ticket types,
with a valid window and a maximum number of uses. A hidden ticket type MUST be
offered only after a valid code is entered, and a code MUST stop working after
its last use.

#### Scenario: A crew friend uses the code

- GIVEN code "LANTERN" unlocks "Crew friends" at EUR 30 and has 5 uses left
- WHEN Sanne enters "LANTERN" on her registration
- THEN "Crew friends" is offered and she can choose it

### Requirement: Prices come from the event, not from the player (REQ-RTO-004)

The price lines of a registration MUST be written by larpinq from the chosen
ticket type and options; a price sent by a client SHALL be ignored.

#### Scenario: A client tries its own price

- GIVEN Anna chooses "Player"
- WHEN a client sends her registration with a line of EUR 1 through the API
- THEN the stored line is EUR 110

### Requirement: Scarce places and options are limited (REQ-RTO-005)

A ticket type with a place limit SHALL waitlist registrations beyond it, and an
option with a place limit MUST be refused once it is full.

#### Scenario: Crew is full

- GIVEN all 20 "Crew" places are taken
- WHEN Joris chooses "Crew"
- THEN his registration is waitlisted

### Requirement: Organisers see what was chosen (REQ-RTO-006)

The event page SHALL show, for accepted registrations, how many chose each
ticket type and each option, and MUST NOT show a money total.

#### Scenario: The kitchen plans

- GIVEN 12 accepted registrations chose vegan catering and 30 meat
- WHEN a game master opens "Winter Court 2026"
- THEN the page shows 12 vegan and 30 meat

### Requirement: A player chooses the event's options on the registration (REQ-RTO-007)

An event SHALL offer registration options, such as a meal plan, each with its choices. A player MUST be able to pick a choice per option on their registration, and the registration page MUST show what was picked.

#### Scenario: Anna eats vegetarian

- GIVEN "Winter Court 2026" offers the option "Meal" with the choices vegetarian, meat and vegan
- WHEN Anna picks vegetarian on her registration
- THEN her registration shows "Meal: vegetarian"
- AND the event's count of vegetarian meals goes up by one

### Requirement: An accepted registration gets a payment request (REQ-RPS-001)

When a registration becomes accepted on an event that requires payment and its
price lines are above zero, larpinq SHALL append one payment request for it
through shillinq's payment requests leaf, with the registration's amount, the
player as debtor, the pay-by date as due date and a transfer reference, and
MUST show the player the payment link and the reference. Larpinq MUST NOT book
the payment itself. Until shillinq lets an app raise a request, larpinq raises
it when a game master accepts; a registration accepted any other way, or
refused by shillinq, SHALL wait as "payment to request" with its pay-by date,
and a game master MUST be able to request its payment from the registration.

#### Scenario: Anna gets her payment link

- GIVEN Anna's registration for "Winter Court 2026" is accepted with lines of EUR 85 and EUR 35
- WHEN a game master accepts the registration
- THEN shillinq holds one pending payment request of EUR 120 for her registration
- AND My registrations shows Anna the payment link and reference WC26-0001

### Requirement: The payment state follows shillinq (REQ-RPS-002)

When shillinq reports a registration's payment request as captured, larpinq
SHALL set the registration's payment state to paid, whether the payment came
through the link or through a bank transfer that shillinq matched by its
reference.

#### Scenario: A bank transfer is matched

- GIVEN Sanne paid by bank transfer quoting WC26-0002
- WHEN shillinq matches the statement line and captures the request
- THEN Sanne's registration shows paid on the event's registrations list

### Requirement: A game master uploads a bank statement to match transfers (REQ-RPS-007)

The Registrations list SHALL offer game masters an action to upload a bank statement (CAMT.053, MT940 or CSV). The upload MUST go to shillinq, which matches each incoming transfer to a payment request by its reference. A matched registration MUST show paid with the payment method bank transfer, and the lines that matched nothing MUST be listed for the game master.

#### Scenario: The Monday statement

- GIVEN Sanne's registration for "Winter Court 2026" is accepted with reference WC26-0002 and not paid
- WHEN a game master uploads a statement with a transfer of EUR 120 quoting WC26-0002 and one transfer without a reference
- THEN Sanne's registration shows paid by bank transfer
- AND the transfer without a reference is listed as not matched

### Requirement: Players are reminded before the pay-by date (REQ-RPS-003)

Three days before the pay-by date, larpinq SHALL send a player whose
registration is still open a Nextcloud notification and an email with the
payment link and reference.

#### Scenario: Anna forgot to pay

- GIVEN the pay-by date of "Winter Court 2026" is 2026-11-20 and Anna has not paid
- WHEN the daily job runs on 2026-11-17
- THEN Anna receives a reminder with her link and WC26-0001

### Requirement: An unpaid registration expires and frees its place (REQ-RPS-004)

One day after the pay-by date, an accepted registration whose payment request
is still pending SHALL be cancelled as unpaid, and its place MUST go to the
waiting list.

#### Scenario: The place goes to Pieter

- GIVEN Anna's request is still pending on 2026-11-21 and Pieter is first on the waiting list
- WHEN the daily job runs
- THEN Anna's registration is cancelled as unpaid
- AND Pieter's registration is accepted

### Requirement: Organisers see who paid (REQ-RPS-005)

The event page and the registrations list SHALL show each registration's
payment state and reference, filter on it, and count paid and open
registrations, without showing money totals.

#### Scenario: Who still owes

- GIVEN 18 accepted registrations of which 15 are paid
- WHEN a game master filters the registrations of "Winter Court 2026" on open
- THEN the 3 unpaid registrations are listed with their references

### Requirement: Invoices and receipts come from shillinq (REQ-RPS-006)

A player SHALL be able to ask for an invoice on their registration, which
larpinq passes on the payment request; larpinq MUST NOT produce invoices or
receipts itself. Without shillinq, a game master MUST be able to set the
payment state by hand.

#### Scenario: A club pays for its member

- GIVEN Joris needs an invoice for his club
- WHEN Joris ticks "Request an invoice" on his registration before it is accepted
- THEN the payment request for his registration carries the invoice request

### Requirement: A registration can be cancelled under the event's policy (REQ-RCT-001)

The participant or the person who booked a registration SHALL be able to
cancel it until the event's cancel-by date, and a game master MUST be able to
cancel it at any time. A cancelled registration's place SHALL go to the
waiting list.

#### Scenario: Anna cancels in time

- GIVEN the cancel-by date of "Winter Court 2026" is 2026-11-25
- WHEN Anna cancels her registration on My registrations on 2026-11-10
- THEN her registration is cancelled
- AND the first waitlisted registration is accepted

#### Scenario: Too late to cancel yourself

@e2e exclude the refusal needs a player account that is not a game master, which the e2e instance does not seed; covered by tests/unit/Service/RegistrationChangeServiceTest.php::testTooLateToCancelYourself

- GIVEN it is 2026-11-28
- WHEN Anna tries to cancel her registration
- THEN the cancellation is refused and she is told to contact the organisers

### Requirement: One participant of a group booking cancels alone (REQ-RCT-002)

A signed-in user SHALL be able to add other participants to their booking, and
each participant MUST have their own registration, so cancelling one leaves
the others unchanged.

#### Scenario: Mila drops out

- GIVEN Joris booked himself and his daughter Mila for "Winter Court 2026"
- WHEN Joris cancels Mila's registration
- THEN Mila's registration is cancelled
- AND Joris's registration stays accepted

### Requirement: A paid cancellation is refunded or credited through shillinq (REQ-RCT-003)

When a paid registration is cancelled, larpinq SHALL request a refund or a
credit from shillinq as the event's policy (or the player's choice, when the
policy allows) says, and MUST record the request and its outcome on the
registration. Larpinq MUST NOT hold a credit balance itself.

#### Scenario: Mila's fee becomes credit

- GIVEN Mila's registration was paid with EUR 85 and the policy lets the player choose
- WHEN Joris cancels it and chooses credit
- THEN larpinq requests a credit of EUR 85 for Mila's player from shillinq
- AND the registration shows credit requested

### Requirement: A credit pays towards the player's next registration (REQ-RCT-005)

A credit that shillinq booked for a player SHALL be applied to the payment request of that player's next accepted registration, up to the amount of the request. The registration MUST show how much was paid from credit and what is still open. Larpinq MUST read the credit from shillinq and MUST NOT keep a balance of its own.

#### Scenario: Mila's credit pays for the spring event

- GIVEN Mila has EUR 85 credit in shillinq after cancelling "Winter Court 2026"
- WHEN her registration for "Spring Moot 2027" is accepted with a line of EUR 95
- THEN the registration shows EUR 85 paid from credit and EUR 10 still open

### Requirement: A registration can be transferred to another player (REQ-RCT-004)

A participant SHALL be able to offer their registration to another player, and
the transfer MUST take effect only when that player accepts; the place, ticket
and payment SHALL stay with the registration, and the new player picks a
character. An offer SHALL lapse after 7 days or at the cancel-by date.

#### Scenario: Sanne hands her place to Pieter

- GIVEN Sanne's registration is accepted and paid
- WHEN Sanne offers it to Pieter and Pieter accepts on My registrations
- THEN the registration belongs to Pieter, still accepted and paid
- AND Pieter is asked to pick his character
