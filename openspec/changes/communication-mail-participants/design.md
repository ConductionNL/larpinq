# Design: communication-mail-participants

## Context

Read at development `2af18d8`, with `registration-intake-and-capacity`
(registration status), `registration-ticket-types-and-options` (ticket role)
and `registration-payments-through-shillinq` (payment state).

- Larpinq sends no mail today (`grep -rniE 'sendMail|IMailer' lib src`: no
  hits).
- `player.userUid` links a player to a Nextcloud account; the contacts leaf on
  `player` links a contact card (`player-to-contacts-leaf`).
- `larping_attendance` holds check-in per event and character.
- OpenRegister's `EmailProvider` links existing Mail messages to objects and
  does not compose or send (`integration-email`).
- Game master endpoints are guarded in `EventsController`
  (`resolveGameMaster()`).

## Goals / Non-Goals

**Goals**: write once, pick the audience, see the count, send one mail each,
keep a record.

**Non-Goals**: cross-event announcements, per-recipient delivery status, rich
mail.

## Decisions

### D1. The message record

`eventMessage` (slug `larping_event_message`): `event`, `subject`, `body`,
`audience` (object: `statuses[]`, `checkedIn` boolean, `paymentOpen` boolean,
`ticketRoles[]`), `state` (`draft`, `queued`, `sent`, `failed`),
`recipientCount`, `sentCount`, `failedCount`, `noAddressCount`, `sentBy`,
`sentAt`. Read and write by `gamemasters` only.

### D2. Resolving the audience

`EventMessageService::recipients(message)` reads the event's registrations
filtered on the chosen statuses, payment state and ticket roles (bounded
pages), intersects with checked-in attendance when asked, and resolves each
player's address: the Nextcloud account's email for `userUid`, else the email
on the linked contact. Duplicates (one person, two registrations) get one
mail. `GET /api/events/{id}/messages/preview` returns the count and the first
ten names.

### D3. Sending

`POST /api/events/{id}/messages/{messageId}/send` (game masters) sets the
state to `queued` and adds a `SendEventMessageJob` (`QueuedJob`) that sends one
mail per recipient through `OCP\Mail\IMailer` with the event name and date
filled into the body, counts sent, failed and no-address, and sets `sent` or
`failed`. A message is sent once; sending again needs a copy.

### D4. Pages

EventDetail (in place): header action "Email participants" opening
`EmailParticipantsModal.vue` (subject, body, audience, count and names preview,
send), and an object-list "Messages" of `eventMessage` for the event with
subject, audience summary, counts and sent time.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Message record and access | Declarative, register fragment | A schema with a rule. |
| Audience resolution | Imperative, a service | Joins registrations, attendance and players across schemas. |
| Sending | Imperative, a queued job over the platform mailer | Side-effecting bulk work, an ADR-031 listed exception. |

## Seed data

A sent message "Practical letter for Winter Court 2026" to accepted
registrations, 3 recipients, 3 sent.

## Risks / Trade-offs

- [Mail volume] A few hundred mails go through the Nextcloud mail queue; the
  job sends in batches of 50 per run.

## Migration

None.

## Open Questions

None.
