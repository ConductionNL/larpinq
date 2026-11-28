# phone-friendly-pages Specification

## Purpose

The pages people use at an event work on a phone. From larpinq matrix row
`adm-mobile-app`.

## ADDED Requirements

### Requirement: Event-day pages fit a phone (REQ-APF-001)

The Check-in tab, the character page with its Stats tab, My registrations, the
Cast page, lore pages, the Dashboard and the Events index SHALL work at 360
pixels wide without horizontal page scrolling, with tables shown as stacked
rows.

#### Scenario: A player checks her sheet between scenes

- GIVEN Anna opens "Mirela the Wanderer" on a phone 360 pixels wide
- WHEN the character page loads
- THEN the page does not scroll sideways
- AND her abilities read as stacked rows on the Stats tab

### Requirement: Check-in works one-handed (REQ-APF-002)

On a phone, the Check-in tab's actions MUST be at least 44 by 44 pixels, and
the scan code field SHALL span the width with text large enough that the phone
does not zoom.

#### Scenario: The steward at the gate

- GIVEN steward Joris opens the Check-in tab of "Winter Court 2026" on a phone
- WHEN he checks in "Mirela the Wanderer"
- THEN the check-in button is at least 44 by 44 pixels
- AND the page did not zoom when he typed a code
