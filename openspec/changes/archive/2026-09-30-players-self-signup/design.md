# Design: players-self-signup

## Context

Read at development `77c85f0`; revised at build time against development
`0d7680db` and portaliq development `49d6be88` (see "Revised at build time").

- `lib/Portal/PortalContributionProvider.php` contributes the `player`
  audience to portaliq: collection `myCharacters` on `character` with
  `scopeField: ownerRef` and `scopeClaim: ownerRef`, catalogue collections,
  and action `createCharacter` (`type: create`, `scopeField: ownerRef`, fields
  name, ocName, background). The class is duck-typed by portaliq and inert
  without it (open changes `portal-contribution`, `portal-identity`).
- portaliq contract v2.1 (portaliq development): a create action's writer
  stamps `scopeField` with the subject's reference; `scopeClaim` resolves the
  scoping value from `portalAccount.claims.<appId>.<claimName>`, and an absent
  claim yields zero rows. Self-registration exists server side (`POST
  /portal/api/identity/register`, change `identity-ways-in-screens`). Claims
  are written by the owning app through a typed event with a result slot
  (open change `portal-identity-space`).
- `player` has `name`, `description`, `userUid` and the contacts leaf.

## Goals / Non-Goals

**Goals**: a portal visitor makes their own player profile and is linked to it
without a game master.

**Non-Goals**: account creation, Nextcloud accounts, care details.

## Decisions

### D1. The action and the collection

`createPlayerProfile`: `type: create`, schema `player`, `scopeField:
portalSubjectRef` (stamped by portaliq's writer), fields `name`,
`description`. `myProfile`: schema `player`, `scopeField: portalSubjectRef`,
no claim (the subject reference itself), fields `name`, `description`.

### D2. Linking the claim

`PlayerProfileClaimListener` on OpenRegister's `ObjectCreatedEvent` for
`player` objects with a `portalSubjectRef` (deferred, hydra ADR-078) sets
`selfRegistered: true` and dispatches portaliq's claim event for subject
`portalSubjectRef`, claim `ownerRef` (app `larpinq`) = the player's uuid. The result
slot is logged; a failure leaves the profile unlinked and visible to game
masters as "not linked".

### D3. One profile per account

A pre-write check refuses a second `player` with the same `portalSubjectRef`
(bounded query, limit 1), answered through the portal as a validation error.

### D4. Review

`player.reviewedAt` and `reviewedBy`; a `NewPlayers` index (fragment) lists
players with `selfRegistered` and no `reviewedAt`, with a "Mark reviewed"
action for game masters.

## Revised at build time (30 Sep 2026)

- **Claim name.** portaliq resolves a claim as `claims.<appId>.<claimName>`,
  and its claim event takes the app id and the claim name separately
  (`PortalAccountClaimRequestedEvent(appId, subjectRef, claimName, value)`,
  result `ok` or `refused`). So the claim is `ownerRef` under app `larpinq`,
  the name `myCharacters` already reads, not a dotted `larpinq.ownerRef`.
- **The subject reference is not a uuid.** `player.portalSubjectRef` is a plain
  string with no `format`.
- **A live defect fixed in the same change.** `createCharacter` declared
  `scopeField: ownerRef` with no `scopeClaim`, so portaliq stamped the subject
  reference into `character.ownerRef` (`format: uuid`) and every character
  created through the portal failed validation. The action now declares
  `scopeClaim: ownerRef`, so the stamp is the linked player's uuid and a
  subject without a profile is refused. `ocName` left the action's fields:
  `PortalProfileListener` sets it to the owner, so the character is played by
  the player who made it.
- **D2/D3 in one listener.** `PortalProfileListener` on `ObjectCreatingEvent`
  refuses a second profile (bounded read with the app's authority, limit 1) and
  sets `selfRegistered` and `awaitingReview`; on `ObjectCreatedEvent` it sends
  the claim. A missing portaliq or a non-`ok` answer is logged and the profile
  stays unlinked.
- **D4 review flag.** OpenRegister has no "is empty" filter, so the New players
  list filters on `awaitingReview: true` rather than on a missing `reviewedAt`.
  "Mark reviewed" is a bulk action on that list that clears `awaitingReview`
  through the object API (the player write rule already limits that to game
  masters); `PlayerReviewListener` stamps `reviewedBy` and `reviewedAt` from
  the session, and keeps the stored stamp on any other update. No new endpoint.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Profile fields and review flag | Declarative, register fragment | Properties. |
| The action and collection | The existing provider class (the portal contract is code) | ADR-046 contract. |
| Linking the claim | Imperative, a deferred listener dispatching a typed event | A command to another app (hydra ADR-041). |
| One profile per account | Imperative, a pre-write check | A uniqueness rule across objects. |

## Seed data

A self-registered player "Lotte Bakker" (portal subject, not reviewed) with no
characters yet.

## Risks / Trade-offs

- [Two identities] A person with both a portal account and a Nextcloud
  account can end up with two player records; the New players list shows the
  email so a game master can merge by hand.

## Migration

None.

## Open Questions

None.
