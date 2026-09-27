# Design: registration-intake-and-capacity

## Context

Read at development `2af18d8`.

- `lib/Settings/register.d/event-signup-to-forms-leaf.json` gives `event`
  `configuration.linkedTypes: ["forms"]`; EventDetail renders the forms leaf
  (`src/manifest.json`, widget `event-forms`). The form and its submissions live
  in Nextcloud Forms; larpinq reads none of them.
- `openspec/specs/event-signup-to-forms-leaf/spec.md` requires that capacity,
  confirmed versus waitlisted, and waiting list order stay in larpinq and that
  confirmed sign-ups appear in `event.players[]`; and that larpinq keeps no
  parallel submission store.
- `event.players[]` (uuid `$ref` character) is the participant list that the
  roster (`EventRosterService::buildRoster()`), the run sheet, the check-in tab
  and the XP pages read.
- `character.ocName` is the uuid of the character's `player`; `player.userUid`
  is the player's Nextcloud user; `character.ownerUid` is derived from it.
- `character.status` (active, retired, dead) arrives with
  `characters-status-and-bulk-edit`.
- Nextcloud Forms dispatches `OCA\Forms\Events\FormSubmittedEvent($form,
  $submission)` (`lib/Service/FormsService.php:782` in nextcloud/forms);
  `getWebhookSerializable()` returns `form` (with `id`) and `submission` (with
  `id` and `userId`).
- hydra ADR-078: pre-write (`*ing`) listener work is synchronous and may mutate
  the object; post-write (`*ed`) work is deferred through
  `ListenerDeferralService` unless it is one of four named inline categories.

## Goals / Non-Goals

**Goals**: every sign-up becomes a registration; capacity, waiting list and
approval hold under concurrency; accepted registrations are the event's
participants; the player chooses a character.

**Non-Goals**: money, cancellation flows, storing form answers.

## Decisions

### D1. The registration

`registration` (slug `larping_registration`): `event` (uuid `$ref`
larping_event), `player` (uuid `$ref` player, may be empty), `submitterUid`
(the Nextcloud user who signed up), `playerUid` (materialised from
`@ref.player.userUid`), `character` (uuid `$ref` character, with
`x-relation-filter` `{"ocName": "@object.player", "status": "active", "setting":
"@object.eventSetting"}`), `eventSetting` (materialised from the event), `status`
(`pending`, `accepted`, `waitlisted`, `declined`, `cancelled`), `submissionId`
(the Forms submission id), `submittedAt`, `decidedAt`, `decidedBy`,
`waitlistPosition` (derived).

Rules: read by `gamemasters` and by larpers where `playerUid` or
`submitterUid` is `$userId`; create by the Forms listener (system context);
update by `gamemasters`, and by the player on `character` only (property rule).

### D2. Event settings

`event.capacity` (integer, empty means no limit), `event.approvalRequired`
(boolean, default false), `event.signupForm` (integer, the Forms form id this
event takes sign-ups from, chosen by the game master when linking the form).

### D3. From submission to registration

`FormSubmissionListener` handles `FormSubmittedEvent` (registered behind
`class_exists('OCA\Forms\Events\FormSubmittedEvent')`). It reads the form id,
finds the event with `signupForm` equal to it (a filtered query with limit 1),
and creates a registration with the submitter, the player whose `userUid` is
the submitter (limit 1), `submissionId`, and status `pending`. An anonymous
submission creates nothing and logs. A second submission from the same user
for the same event updates nothing and is logged; the first registration
stands.

### D4. Deciding the status

`RegistrationListener` on the pre-write events of `registration` (synchronous,
ADR-078 rule 2) calls `RegistrationService::decide()` when a registration is
created, or when a game master moves it to `accepted`:

- It takes a lock per event (`ILockingProvider`, key
  `larpinq/event-capacity/<eventId>`).
- It counts places taken: registrations with status `accepted` plus characters
  in `event.players[]` that no registration covers.
- Create without approval: `accepted` if a place is free, else `waitlisted`.
  Create with approval: `pending`. Accept by a game master: `accepted` if a place
  is free, else `waitlisted`.
- It stamps `decidedAt` and `decidedBy`.

The lock is released by the post-write handler of the same registration,
placed inline under ADR-078 category `correctness` (a lock held into a later
cron run would block every sign-up).

### D5. Keeping the participants and promoting the waiting list

The post-write handler (deferred, ADR-078 rule 1) adds the registration's
character to `event.players[]` when it becomes `accepted` with a character,
removes it when it leaves `accepted`, and when a place frees up, accepts the
oldest `waitlisted` registration (by `submittedAt`) through the same
`decide()`.

### D6. Pages

- Fragment: `Registrations` index (event, player, character, status,
  submitted), a registration detail page with accept and decline actions for
  game masters (lifecycle transitions on `status`), and `MyRegistrations` for
  players (the same index, reduced by the row rules) where the player picks the
  character.
- EventDetail (in place): object-list "Registrations" filtered on the event,
  with status; a stats entry for places taken of capacity; `capacity`,
  `approvalRequired` and `signupForm` in the event data widget.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Registration data, rules, materialised uids | Declarative, register fragment | Schemas and row rules. |
| Status transitions (accept, decline) | Declarative lifecycle on `status`, with the service deciding the outcome | The state machine is declarative; the capacity outcome is not. |
| Submission to registration | Imperative, a listener on a Forms event | An external integration (hydra ADR-031 exception list). |
| Capacity, waiting list, participants | Imperative, service and listeners | hydra ADR-031 exception 2: counts across registrations and the event under a lock, and writes to another schema. |

## Seed data

Event "Winter Court 2026" (capacity 3, approval required, sign-up form 1):
registrations of Anna with "Mirela the Wanderer" (accepted), Karel with "Old
Captain Harrow" (declined, retired character), Sanne with "Lady Venn"
(accepted), Joris with "Tomas" (accepted), and Pieter with no character yet
(waitlisted, position 1).

## Risks / Trade-offs

- [Deferred sync] `event.players[]` updates on the next background run after a
  decision, not in the same request. The registrations list shows the decision
  at once; the roster follows within a minute.
- [Two sources] Manual edits of `event.players[]` remain possible; they count
  against capacity (D4).

## Migration

None. Existing events have no capacity and no registrations; their
`players[]` stays as it is.

## Open Questions

None.
