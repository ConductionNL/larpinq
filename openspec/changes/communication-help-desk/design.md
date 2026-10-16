# Design: communication-help-desk

## Context

Read at development `77c85f0`, with `players-self-signup` (portal players get
a player profile and the `larpinq.ownerRef` claim).

- `lib/Portal/PortalContributionProvider.php`: the `player` audience with
  `myCharacters` (`scopeClaim: ownerRef`) and `createCharacter`
  (`scopeField: ownerRef`); `notifications` is an empty list.
- Notifications in the register: `character` declares
  `x-openregister-notifications` rules with `nc-notification`, recipients by
  group or by a uid field (`ownerUid`).
- No question, ticket or message schema exists in larpinq.

## Goals / Non-Goals

**Goals**: ask, answer, reply, close; one list for the organisers; the asker
hears back.

**Non-Goals**: attachments, a public FAQ.

## Decisions

### D1. Schemas

- `helpQuestion` (slug `larping_help_question`): `player`, `askerUid`
  (materialised from the player's `userUid`), `ownerRef` (the player's uuid,
  for portal scoping), `subject`, `body`, `event`, `character`, `status`
  (`open`, `answered`, `closed`; lifecycle `answer`, `reopen`, `close`),
  `assignee` (`format: user`), `lastMessageAt`.
- `helpMessage` (slug `larping_help_message`): `question`, `author` (uid or
  portal subject), `fromOrganiser` (boolean), `body`, `createdAt`.

Rules: read by `gamemasters` and by larpers where `askerUid = $userId`;
create a question by `larpers`; create a message by `gamemasters` and by the
asker; update and close by `gamemasters`.

### D2. Status follows the thread

`HelpMessageListener` (deferred post-write on `helpMessage`): a message with
`fromOrganiser` sets the question `answered`; a message from the asker on an
answered question sets it `open` again. A closed question refuses new messages
(pre-write check).

### D3. Notifications

On `helpQuestion`: rule `question-asked` (trigger created, recipients group
`gamemasters`) and `question-answered` (trigger transition `answer`, recipient
field `askerUid`), channel `nc-notification`. The portal contribution declares
the same `answered` change on `myQuestions` for portal players.

### D4. Pages

- `Help` (fragment): the asker's own questions (row rules reduce the index),
  "Ask a question", and the thread on the detail page.
- `HelpDesk` (fragment, game masters): open and answered questions sorted by
  `lastMessageAt`, filters on status and assignee, marking older than 3 days.
- Dashboard (in place): a stats entry "Open questions" for game masters.
- Portal: `askQuestion` (`type: create`, `scopeField: ownerRef`, fields
  subject, body, event) and `myQuestions` (`scopeClaim: ownerRef`).

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Schemas, access, lifecycle, notifications | Declarative, register fragment | OpenRegister extensions. |
| Status from the thread | Imperative, a deferred listener | A write to one object caused by another object's creation. |
| Closed questions refuse messages | Imperative, a pre-write check | Depends on another object's status. |

## Seed data

Anna asks "Can I bring a real bow?" about "Winter Court 2026"; game master
Joris answers "Only LARP-safe bows, checked at the gate"; status answered.

## Risks / Trade-offs

- [Portal notifications] Depend on portaliq's open change; the fallback is
  reading "My questions".

## Migration

None.

## Open Questions

None.
