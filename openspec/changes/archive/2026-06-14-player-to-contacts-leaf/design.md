# Design — player-to-contacts-leaf

## Context

`events-players/spec.md`: Player = `{name, description}`, required `name`,
referenced by characters via the character's `ocName` field (PLR-006). It is a
thin stand-in for a real-world person with none of the contactable attributes.

## Decision

Consume the OpenRegister **contacts leaf** (ADR-019, ADR-022) for player person
data on the Player detail page. Keep the LARP-domain linkage (Player ↔
characters via `ocName`) in LarpingApp.

### Why a leaf, not in-app

- ADR-022 names "an app-local Person vs OR contacts" as the canonical
  duplicate-data-model anti-pattern. A person model is the textbook case for
  consume-don't-build.
- "Follow OR's schema when OR has a schema" (ADR-022): use the OR contacts
  schema/register, not a local copy with same-ish fields.
- Cross-app: the same person can be a Player here and a contact elsewhere in the
  fleet, resolving to one identity.
- Precedent: PDF export delegates wholesale to DocuDesk.

### Boundary — what stays in LarpingApp

| Concern | Owner |
|---|---|
| Person attributes (email, phone, address, display name) | OR contacts leaf |
| Player ↔ character linkage (`ocName`, PLR-006) | LarpingApp (LARP domain) |
| In-game participation in events (`players[]`) | LarpingApp |

The Player object becomes essentially a reference to a contact plus the in-game
linkage; person fields are not duplicated locally.

### Migration

- Existing `name` → linked contact display name; `description` → contact notes.
- Existing character `ocName` references MUST keep resolving (the linkage layer
  is unchanged; only the backing person data moves).

## Alternatives considered

- **Grow the Player schema with email/phone/etc.** — rejected: this is precisely
  the app-local Person anti-pattern ADR-022 forbids.
- **Use Nextcloud Contacts directly (CardDAV) from LarpingApp** — rejected:
  contact linkage belongs behind the OR contacts leaf, not a per-app CardDAV
  integration (same posture as the calendar/CalDAV decision).

## Risks

- The `ocName` linkage currently keys on the Player UUID. If the contact becomes
  the identity anchor, the linkage layer must map Player→contact stably.
  Mitigated by keeping the Player object (and its UUID) as the LARP-side anchor
  that references a contact, rather than replacing it.
