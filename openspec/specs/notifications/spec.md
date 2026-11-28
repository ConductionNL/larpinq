---
status: in-progress
---

# notifications Specification

**OpenSpec changes**:
- `character-player-picker` (archived 17 Nov 2026) — ownerUid is now materialised via x-openregister-calculations, fulfilling the "Structured owner uid prerequisite" requirement

## Purpose
Who larpinq notifies, and when: game masters when a character is submitted, the owning player when it is approved, and participants before an event. A player is reached through the Nextcloud account their player record names.

## Requirements

### Requirement: Character submission and approval notifications

The Larpinq `character` schema SHALL declare
`x-openregister-notifications` rules that notify the `gamemasters` group
on character submission (trigger `created`) and notify manage-ACL
holders plus the `gamemasters` group on approval (transition action
`approved`), with bilingual (nl/en) subjects. These rules SHALL NOT use
a `field:ocName` recipient because `ocName` is free text and does not
resolve to a Nextcloud uid.

#### Scenario: New character submitted

- **WHEN** a `character` object is created
- **THEN** the OpenRegister notification engine dispatches an `nc-notification` to the `gamemasters` group with a nl/en subject referencing `{{name}}`

#### Scenario: Character approved

- **WHEN** a `character` object transitions through the `approved` action
- **THEN** the engine dispatches an `nc-notification` to the object's manage-ACL holders and the `gamemasters` group with a nl/en subject referencing `{{name}}`

### Requirement: Structured owner uid prerequisite for per-player notifications

Larpinq SHALL populate the structured `ownerUid` field on `character` via
`x-openregister-calculations` (`materialise:true`, sourced from
`@ref.player.userUid` through `character.x-openregister-references.player`),
rather than requiring it to be set by hand or left permanently empty. The
`character-approved` rule's `{"kind":"field","field":"ownerUid"}` recipient
requires no changes — it already targets this field and now receives a populated
value whenever the character's linked player has `userUid` set.
<!-- Previous behavior: ownerUid existed as a manually-editable field with no code
     path that ever set it, so the field:ownerUid recipient was always empty and
     the character-approved notification never reached the submitting player. -->

#### Scenario: ownerUid enables player-targeted approval

- GIVEN a character whose `ocName` resolves to player "Alice" with
  `userUid = "alice"`
- WHEN the character transitions through the `approved` action
- THEN `character.ownerUid` MUST already be materialised to `"alice"` (set at save
  time via the calculation, not at notification time)
- AND the `character-approved` rule's `{"kind":"field","field":"ownerUid"}`
  recipient MUST resolve to Nextcloud user `alice`, notifying the submitting
  player alongside the object's manage-ACL holders and the `gamemasters` group

#### Scenario: Unlinked character has no player recipient

- GIVEN a character whose `ocName` does not resolve to any `player` object (e.g. a
  legacy free-text value not yet re-picked via the select-or-create dropdown)
- WHEN the character transitions through the `approved` action
- THEN `ownerUid` MUST remain empty (the calculation has no `@ref.player` to read)
- AND the `field:ownerUid` recipient MUST resolve to no additional recipient
  beyond the manage-ACL holders and `gamemasters` group, matching today's
  behavior for such characters

### Requirement: Event reminder notification

The Larpinq `event` schema SHALL declare an
`x-openregister-notifications` rule that sends the `players` group a
scheduled reminder ahead of an event's `startDate`, with bilingual
(nl/en) subjects.

#### Scenario: Upcoming event reminder

- **WHEN** the scheduled reminder window before an `event` object's `startDate` is reached
- **THEN** the engine dispatches an `nc-notification` to the `players` group with a nl/en subject referencing `{{name}}` and `{{startDate}}`
