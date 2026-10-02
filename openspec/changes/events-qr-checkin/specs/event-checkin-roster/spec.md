# event-checkin-roster Specification (delta)

## Purpose

A steward checks participants in by scanning or typing the code of their
registration. From larpinq matrix row `evt-qr-checkin`.

## ADDED Requirements

### Requirement: Every accepted registration has a check-in code (REQ-EQC-001)

When a registration is accepted, larpinq SHALL give it a random check-in code
that cannot be guessed, readable only by game masters and the registration's
player. When the registration changes hands, larpinq MUST replace the code, so
the previous holder's code checks nobody in.

#### Scenario: Anna's registration is accepted

- GIVEN Anna's registration for "Winter Court 2026" is pending
- WHEN a game master accepts it
- THEN the registration has a check-in code
- AND Karel cannot read that code through the API

### Requirement: The player sees the code as a QR code (REQ-EQC-002)

The registration page, opened from My registrations, SHALL show the check-in
code of an accepted registration on a Check-in code tab as a QR code with the
code as text beneath it, and MUST print cleanly.

#### Scenario: Anna prints her code

- GIVEN Anna's registration is accepted
- WHEN Anna opens her registration from My registrations, goes to Check-in code and prints
- THEN the print shows the QR code, the code, her name, her character and the event

### Requirement: Stewards check in by scanning or typing a code (REQ-EQC-003)

The Check-in tab SHALL offer a scan mode that reads a QR code with the camera
where the browser supports it and always accepts a typed or scanner-entered
code, and MUST check in the participant the code belongs to and show their
name and character.

#### Scenario: Anna arrives at the gate

- GIVEN Anna's registration is accepted with a check-in code
- WHEN a game master enters her code in the scan mode of the Check-in tab
- THEN "Mirela the Wanderer" is checked in on the roster
- AND the panel shows "Anna de Vries, Mirela the Wanderer"

### Requirement: A code is checked in once and only for its event (REQ-EQC-004)

A second use of a code SHALL report that the participant is already checked in
with the time and the steward; a code of another event, an unknown code, or a
registration that is not accepted MUST check nobody in.

#### Scenario: The same code twice

- GIVEN Anna was checked in at 18:02 by steward Joris
- WHEN her code is scanned again
- THEN the panel says she was already checked in at 18:02 by Joris
