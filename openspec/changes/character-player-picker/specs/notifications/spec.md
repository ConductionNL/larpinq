# notifications Specification

## MODIFIED Requirements

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
