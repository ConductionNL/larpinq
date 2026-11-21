# Email participants

## Overview

Game masters write to the participants of an event from the event page: the
practical letter two weeks before, a change of venue, a reminder to pay.
Larpinq mails each recipient on their own through the Nextcloud mailer, keeps
the message with the event and lists what was sent.

## How to use

1. Open the event and choose **Email participants** in the page header. The
   action is shown to game masters only.
2. Write a **Subject** and a **Message**. `{event}` and `{date}` in the message
   are replaced by the event's name and start date in every mail.
3. Choose who gets it:
   - **Registration status**: accepted (the default), waitlisted, pending.
   - **Ticket role**: player, crew, NPC. Leave all unticked for every role.
   - **Checked in only**: only participants whose character was checked in.
   - **Payment open only**: only registrations whose payment is still open.
4. Choose **Preview recipients**. Larpinq shows how many people get the
   message, the first ten names, and the people without an email address, so
   you can reach them another way. Each person is counted once, however many
   registrations they hold.
5. Choose **Send** and confirm. The mails go out in the background, 50 per
   run of the Nextcloud background jobs.

## The Messages list

The event page lists its messages with subject, audience, status, how many
were sent, how many failed, how many people had no address, and when the
last mail was sent. Only game masters can read the messages.

A message is sent once. To send the same text again, write a new message.

## Where the address comes from

A recipient's address is the email of their Nextcloud account: the account
the registration was made for, else the account linked to the player.
Players without an account email are counted as "no address".

## Limits

- Plain text only; no attachments.
- Larpinq records how many mails it handed to the mailer and how many the
  mailer refused, not whether each mail was delivered.
- Mail to players across events or a whole world is not part of this feature.
