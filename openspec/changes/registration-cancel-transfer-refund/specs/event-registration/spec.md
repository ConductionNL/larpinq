# event-registration Specification (delta)

## Purpose

A registration can be cancelled on its own, also from a group booking,
transferred to another player, and a paid cancellation is settled as a refund
or credit through shillinq. From larpinq matrix rows `reg-refunds`,
`reg-player-credit`, `reg-transfer` and `reg-partial-cancel`.

## ADDED Requirements

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
