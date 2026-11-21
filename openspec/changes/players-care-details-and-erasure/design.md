# Design: players-care-details-and-erasure

## Context

Read at development `77c85f0`, with `registration-intake-and-capacity`
(registration accepted by `RegistrationService::decide()`).

- `player` holds `name`, `description`, `userUid`, and the contacts leaf.
- OpenRegister (development specs): `field-level-encryption` encrypts
  properties flagged `x-openregister-encrypted: true`, decrypts only for
  authorised reads and keeps them out of search and facets;
  `row-field-level-security` strips unreadable properties everywhere;
  `retention-management` computes an archiefactiedatum from a property,
  builds destruction lists in a background job, requires approval, and issues
  destruction certificates, with legal holds; `gdpr-data-subject-rights` and
  hydra ADR-047 give the data-subject workflow to OpenRegister, with the leaf
  app declaring its schemas in scope.

## Goals / Non-Goals

**Goals**: collect care details safely, give the event team what it needs,
erase the event copies after the event with a record.

**Non-Goals**: a larpinq AVG screen, age and consent, diet counts.

## Decisions

### D1. Fields on the player

`allergies`, `medicalNotes` (text, `x-openregister-encrypted: true`),
`dietaryNeeds` (text, encrypted), `emergencyContactName`,
`emergencyContactPhone` (text, encrypted). Property rules: read and update by
`gamemasters` and by larpers where `userUid = $userId` (the player's own
account); portal players get them through `players-self-signup`'s profile when
that change adds them to `myProfile`.

### D2. The event copy

`eventCareRecord` (slug `larping_event_care_record`): `event`, `registration`,
`player`, the five fields (encrypted), `eventEndDate` (copied), and retention
metadata. Made by `RegistrationService` when a registration becomes accepted,
updated when the player changes their details before the event starts, and
never after. Read by `gamemasters` only.

Alternative: read the player's details live during the event and erase
nothing per event. Rejected: the row asks for erasure per finished event, and
a player's standing details are theirs to keep or remove, while the event's
copy is the organisers' and has a purpose that ends.

### D3. Retention and erasure

`eventCareRecord` declares retention with `archiefnominatie: vernietigen`,
afleidingswijze "eigenschap" on `eventEndDate`, and a period of
`event.careRetentionDays` (default 30) days. OpenRegister's destruction job
puts due records on a destruction list; a game master (or the configured
archivist group) approves it; OpenRegister destroys the records and issues
the destruction certificate that is the record of what was erased.

### D4. Data-subject scope

The fragment declares `player` and `eventCareRecord` in scope for
OpenRegister's data-subject workflow (ADR-047), with `player.userUid` and the
contact's email as the subject identifiers. Larpinq adds no screen.

### D5. Pages

PlayerDetail (in place): a "Care details" data widget. EventDetail (in place):
a "Care" object-list of `eventCareRecord` for the event with the five fields
and a count of records due for erasure.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Fields, encryption, access | Declarative, register fragment | OpenRegister extensions. |
| Retention and erasure with a certificate | Declarative retention metadata, OpenRegister's workflow | OpenRegister owns destruction. |
| Data-subject requests | Declarative scope, OpenRegister's workflow | hydra ADR-047. |
| Copy on acceptance | Imperative, the existing registration service | A write at a state change of another object. |

## Seed data

"Anna de Vries": allergy "hazelnuts", emergency contact "Joost de Vries,
+31 6 00000000". Her accepted registration for "Winter Court 2026" has a care
record with retention date 30 days after the event.

## Risks / Trade-offs

- [Search] Encrypted fields cannot be searched; the event care list is short
  enough to read.

## Migration

None.

## Open Questions

None.
