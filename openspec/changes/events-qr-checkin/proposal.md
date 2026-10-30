---
kind: code
depends_on: [registration-intake-and-capacity]
---

# Proposal: events-qr-checkin

## Summary

At the gate of a LARP site a steward checks in two hundred people in an hour.
Larpinq's check-in is a button per character in a roster the steward has to
search. This change gives every accepted registration a check-in code, shows
it to the player as a QR code, and adds a scan mode to the event's Check-in
tab: the steward points a phone at the code, or types it, and the right
participant is checked in.

## Motivation

One events row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build`: two competitors rate it yes.

**`evt-qr-checkin`**, "Check players in by scanning a QR code on their
ticket." Larpinq rates it no. Matrix evidence: "grep -rniE "qr.?code" lib src
--include=*.php --include=*.vue --include=*.js: no hits; there is no ticket
concept at all (see reg-tickets)".

- LarpManager (yes): "larpmanager/fixtures/feature.yaml:380-395 checkin feature 'Generates a unique QR code per registration and lets staff scan it', larpmanager/urls/orga.py:106 orga_checkin_scan, larpmanager/views/orga/checkin.py:166" (source read at main 36f23d3).
- pretix (yes): "src/pretix/base/pdf.py:883 renders the ticket secret as a QR code on tickets, and src/pretix/plugins/webcheckin/static/pretixplugins/webcheckin/components/app.vue:376 takes scanned codes into src/pretix/api/views/checkin.py:457 _redeem_process" (source read at tag v2026.7.0).

## Affected Projects

- [ ] Project: `larpinq`: a check-in code on the registration, a QR code on My registrations, a scan mode in the Check-in tab, and a check-in by code endpoint.

## Scope

### In Scope

- A random, unguessable check-in code per registration, made when it is accepted.
- The QR code and the code as text on the player's My registrations page, printable.
- A scan mode in `EventRoster.vue`: camera scanning where the browser supports it, and a code field that also takes input from a handheld scanner.
- `POST /api/events/{id}/checkin-code`: resolves the code to the registration and records attendance `checked-in` through the existing `recordAttendance()`, game masters only.
- Clear results: checked in, already checked in (with time and by whom), not accepted, wrong event, unknown code.

### Out of Scope

- Check-in without a network connection (row `evt-offline-checkin`, deferred).
- Wristbands and cards (row `evt-wristband-checkin`, deferred); a card printed with the same QR code works without extra code.
- Printed PDF tickets.

## Approach

The code is a registration property set by the registration service. The
roster view gains a scan panel. The endpoint sits next to `recordAttendance`
in `EventsController` with the same game master guard. Details in design.md.

## New Dependencies

- `qrcode` (npm, MIT) to render the QR image in the browser. The browser's
  `BarcodeDetector` reads codes; where it is missing, the typed field is used.

## Impact

- `lib/Settings/register.d/events-qr-checkin.json` (new): `registration.checkinCode`.
- `lib/Service/RegistrationService.php`: sets the code on acceptance.
- `lib/Controller/EventsController.php` and `appinfo/routes.php`: `checkinByCode()`.
- `src/views/EventRoster.vue`: the scan panel; `src/components/RegistrationQrCode.vue` (new) on My registrations.

## Cross-Project Dependencies

None.

## Risks

### Risk 1: A copied code checks in the wrong person
**Severity:** Medium. **Mitigation:** the result shows the participant's name and character, so the steward sees who the code belongs to; a second scan of the same code reports "already checked in" with the time.

### Risk 2: Guessing codes
**Severity:** Low. **Mitigation:** 128 random bits, base32; the endpoint is game master only and rate limited per user.

## Rollback Strategy

Hide the scan panel and remove the route. Codes stay on registrations, unused.

## Open Questions

None.
