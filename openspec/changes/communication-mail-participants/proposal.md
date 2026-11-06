---
kind: code
depends_on: [registration-intake-and-capacity]
---

# Proposal: communication-mail-participants

## Summary

Two weeks before an event the organisers send the practical letter: the
address, what to bring, the costume rules. Today a game master copies
addresses out of larpinq into a mail program. This change adds "Email
participants" to the event page: a game master writes a message, picks who
gets it (accepted, waitlisted, checked in, unpaid, a ticket role), sees how
many people that is, and sends it. Larpinq mails each recipient on their own,
keeps the message with the event, and lists what was sent.

## Motivation

One communication row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build`: two competitors rate it yes.

**`com-mail-participants`**, "Email everyone signed up for an event from
inside the tool." Larpinq rates it no. Matrix evidence: "grep -rniE
"mailParticipant|sendMail" lib src --include=*.php --include=*.vue
--include=*.js: no hits".

- LarpManager (yes): "larpmanager/urls/orga.py:1801 orga_send_mail, larpmanager/views/orga/member.py:525-563 archive of sent event mail" (source read at main 36f23d3).
- pretix (yes): "src/pretix/plugins/sendmail/views.py:243 OrderSendView emails everyone who ordered for an event, filtered by product, status or check-in, with history at :524" (source read at tag v2026.7.0).
- LARP Portal (partial): "'Messaging between players/staff' is mentioned generally; mass-emailing everyone signed up for one event specifically isn't confirmed. https://larportal.com"

## Affected Projects

- [ ] Project: `larpinq`: an event message schema, a send service on a queued job, and an "Email participants" action with a sent list on the event page.

## Scope

### In Scope

- `eventMessage`: event, subject, body (plain text with the event name and date as placeholders), audience filter, sent by, sent at, recipient count, state (draft, queued, sent, failed with counts).
- Audience filters: registration status (accepted, waitlisted, pending), attendance checked in, payment state open, ticket role (player, crew, npc).
- A preview of the recipient count before sending.
- Sending one mail per recipient through Nextcloud's mailer on a queued background job, to the email of the player's Nextcloud account or the player's contact.
- The sent messages listed on the event page, readable by game masters.

### Out of Scope

- Mail to players across events or a whole world (row `com-announcements`, deferred).
- A delivery status per recipient (row `com-mail-outbox`, deferred): larpinq records how many were handed to the mailer and how many failed.
- Rich formatting and attachments.

## Approach

A register schema holds the message. `EventMessageService` resolves the
audience from registrations and attendance with bounded queries and queues a
`QueuedJob` that sends through `OCP\Mail\IMailer`. A modal on the event page
composes and previews. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/communication-mail-participants.json` (new).
- `lib/Service/EventMessageService.php`, `lib/BackgroundJob/SendEventMessageJob.php`, `lib/Controller/EventMessagesController.php` (new), routes.
- `src/modals/EmailParticipantsModal.vue` (new), a header action and a sent-messages list on EventDetail in `src/manifest.json` (in place).

## Cross-Project Dependencies

None. OpenRegister's email leaf links existing Mail messages and does not send
(spec `integration-email`: "The tab SHALL NOT provide compose/send"), so the
platform mailer is used.

## Risks

### Risk 1: A mail goes to the wrong group
**Severity:** Medium. **Mitigation:** the modal shows the recipient count and the first ten names before sending, and sending needs a confirmation.

### Risk 2: Players without an email address
**Severity:** Low. **Mitigation:** they are counted and listed as "no address" in the result, so a game master can reach them another way.

## Rollback Strategy

Hide the action; messages stay as records.

## Open Questions

None.
