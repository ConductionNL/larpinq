# event-feedback Specification

## Purpose

After an event, every participant who was there is asked for feedback through
a Nextcloud Form, and game masters see who answered. From larpinq matrix row
`evt-post-event-survey`.

## ADDED Requirements

### Requirement: An event has a feedback form (REQ-EPS-001)

A game master SHALL be able to link a Nextcloud Form to an event as its
feedback form, with a moment it opens (by default the end of the event) and a
moment it closes. The form, its questions and its answers MUST stay in
Nextcloud Forms.

#### Scenario: A game master sets up the post-event letter

- GIVEN event "Summer Siege 2025" ends on 2025-08-17 16:00
- WHEN a game master links form "Summer Siege post-event letter" as its feedback form
- THEN feedback for the event opens on 2025-08-17 16:00 and closes 21 days later

### Requirement: Participants who were there are invited once (REQ-EPS-002)

When feedback opens, larpinq SHALL send each participant whose attendance is
checked in one Nextcloud notification and one email with the form link.
Participants marked no-show MUST NOT be invited.

#### Scenario: Anna gets her invitation

- GIVEN Anna was checked in at "Summer Siege 2025" and Karel was a no-show
- WHEN feedback opens
- THEN Anna receives a notification and an email with the form link
- AND Karel receives nothing

### Requirement: Those who have not answered are reminded once (REQ-EPS-003)

Five days before feedback closes, larpinq SHALL send one reminder to invited
participants who have not answered.

#### Scenario: Sanne forgot

- GIVEN Sanne was invited and has not answered
- WHEN it is five days before feedback closes
- THEN Sanne receives one reminder

### Requirement: Game masters see who answered (REQ-EPS-004)

The event page SHALL show game masters how many invited participants answered
and who has not, and MUST link to the answers in Nextcloud Forms.

#### Scenario: Half the letters are in

- GIVEN Anna answered and Sanne did not
- WHEN a game master opens "Summer Siege 2025"
- THEN the page shows 1 of 2 answered and lists Sanne as not answered
- AND a link opens the form's results in Nextcloud Forms
