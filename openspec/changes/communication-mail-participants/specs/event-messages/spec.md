# event-messages Specification

## Purpose

Game masters email the participants of an event from the event page, choose
who gets the message, and keep a record of what was sent. From larpinq matrix
row `com-mail-participants`.

## ADDED Requirements

### Requirement: Game masters write a message to an event's participants (REQ-CMP-001)

A game master SHALL be able to write a message with a subject and a body for
an event and choose its audience by registration status, check-in, open
payment and ticket role.

#### Scenario: The practical letter

- GIVEN event "Winter Court 2026" with three accepted registrations
- WHEN a game master chooses "Email participants", writes "Practical letter" and picks accepted registrations
- THEN the message is saved as a draft for the event

### Requirement: The recipients are shown before sending (REQ-CMP-002)

Before sending, larpinq SHALL show how many people will receive the message and
the first ten names, count each person once, and MUST list players without an
email address separately.

#### Scenario: A player with no address

- GIVEN one of the three accepted players has no email address
- WHEN the game master previews the message
- THEN the preview shows 2 recipients and 1 without an address

### Requirement: Each recipient gets their own mail (REQ-CMP-003)

On sending, larpinq SHALL mail each recipient separately through the Nextcloud
mailer, with the event name and date filled in, and MUST record how many were
sent and how many failed. A message MUST be sent only once.

#### Scenario: Sending

- GIVEN the previewed message with 2 recipients
- WHEN the game master sends it
- THEN each recipient receives one mail addressed to them only
- AND the message shows 2 sent, 0 failed

### Requirement: Sent messages stay with the event (REQ-CMP-004)

The event page SHALL list the event's messages with subject, audience, counts
and time sent, readable by game masters only.

#### Scenario: What did we tell them

- GIVEN "Practical letter" was sent on 2026-11-28
- WHEN a game master opens "Winter Court 2026"
- THEN the Messages list shows "Practical letter", accepted registrations, 2 sent, 2026-11-28
