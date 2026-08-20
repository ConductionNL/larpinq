# Tasks: character-player-picker

## Implementation Tasks

### Task 1: Change character.ocName to a player object-relation dropdown
- **spec_ref**: `openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-character-crud-operations`
- **files**: `lib/Settings/larpingapp_register.json`
- **acceptance_criteria**:
  - GIVEN `character.properties.ocName` WHEN edited THEN it becomes `{type:"string", format:"uuid", $ref:"player", title:"Player", description:"The player who plays this character"}` and stays in `character.required`
  - GIVEN the register is re-imported WHEN the character form renders THEN `ocName` shows as an inline select-or-create player dropdown, not a text input
- [ ] Implement
- [ ] Test

### Task 2: Add player.userUid Nextcloud-user field
- **spec_ref**: `openspec/changes/character-player-picker/specs/events-players/spec.md#requirement-player-to-nextcloud-account-link`
- **files**: `lib/Settings/larpingapp_register.json`
- **acceptance_criteria**:
  - GIVEN `player.properties` WHEN edited THEN it gains `userUid: {type:"string", format:"user", title:"Nextcloud user", description:"The Nextcloud user account this player signs in as. Used for character-ownership notifications."}`
  - GIVEN the field is set to `"alice"` WHEN saved THEN `player.userUid` persists as `"alice"`
- [ ] Implement
- [ ] Test

### Task 3: Add character.x-openregister-references.player
- **spec_ref**: `openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-player-reference-resolution-on-character-objects`
- **files**: `lib/Settings/larpingapp_register.json`
- **acceptance_criteria**:
  - GIVEN `character` WHEN edited THEN it gains `x-openregister-references: {"player": {"schema":"player", "mode":"relatedObject", "field":"ocName"}}`
  - GIVEN a character linked to a player with `userUid = "bob"` WHEN declarative expressions evaluate THEN `@ref.player.userUid` resolves to `"bob"`
- [ ] Implement
- [ ] Test

### Task 4: Make character.ownerUid a read-only calculated field
- **spec_ref**: `openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-character-crud-operations`
- **files**: `lib/Settings/larpingapp_register.json`
- **acceptance_criteria**:
  - GIVEN `character.properties.ownerUid` WHEN edited THEN it becomes `{type:"string", title:"Owner UID", visible:false, readOnly:true, description:"Auto-derived Nextcloud uid of the owning player's user account (materialised from the linked player). Not user-editable."}`
  - GIVEN `character.x-openregister-calculations` WHEN edited THEN it gains `{"ownerUid": {"type":"string", "materialise":true, "expression":{"prop":"@ref.player.userUid"}}}`
  - GIVEN a character linked to player "alice" WHEN saved THEN `ownerUid` materialises to `"alice"` with zero dispatcher changes to the existing `character-approved` notification
- [ ] Implement
- [ ] Test

### Task 5: Add "widget":"switch" to character.approved
- **spec_ref**: `openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-character-crud-operations`
- **files**: `lib/Settings/larpingapp_register.json`
- **acceptance_criteria**:
  - GIVEN `character.properties.approved` WHEN edited THEN it gains `"widget":"switch"` while keeping `enum:["no","approved"]`, `default:"no"`, and `facetable:true` unchanged
  - GIVEN `character.x-openregister-lifecycle` and the `character-approved` notification rule THEN neither is edited by this task
  - GIVEN the register is re-imported WHEN the character form renders THEN `approved` shows as a toggle, and flipping it drives the same `no`↔`approved` lifecycle transition as before
- [ ] Implement
- [ ] Test

### Task 6: Seed data — sample players and linked characters
- **spec_ref**: `openspec/changes/character-player-picker/design.md#seed-data`
- **files**: `_registers.json` (or the app's equivalent seed-data manifest, per ADR-016)
- **acceptance_criteria**:
  - GIVEN a fresh install WHEN seed data loads THEN 3 `player` objects exist with `name` and `userUid` set (`alice`, `bob`, `carol`)
  - GIVEN the same install WHEN seed data loads THEN at least 2 `character` objects exist with `ocName` referencing seeded players, demonstrating `ownerUid` materialisation end-to-end
- [ ] Implement
- [ ] Test

## Verification
- All tasks checked off
- `openspec validate` passes
- Manual testing against acceptance criteria (see test-plan.md TC-1 through TC-7)
- Code review against spec requirements

## Tests (company-wide ADR-009)
- No PHP business logic changes in this change — pure JSON schema edit; PHPUnit N/A
- Newman/Postman N/A — no new/changed API endpoints
- Browser tests (Playwright MCP) covering TC-1 through TC-7 from test-plan.md
- `openspec validate` and register JSON re-import (TC-8) pass

## Documentation (company-wide ADR-010)
- Feature documentation updated in `docs/features/` describing the new player picker, Nextcloud-user link, and approved toggle, with Playwright MCP screenshots of the character and player forms

## i18n (company-wide ADR-005)
- Dutch (`nl_NL`) and English (`en_US`) strings added for the `ocName`/"Player", `userUid`/"Nextcloud user", and `approved`/"Approved" field titles and descriptions in `lib/Settings/larpingapp_register.json`

## Quality checklist

- All new/changed business logic covered by PHPUnit unit tests (`tests/Unit/`) — N/A, no PHP changes in this change
- New/changed API endpoints covered by Newman/Postman tests — N/A, no API changes
- UI changes covered by Playwright browser tests
- All tests pass (`composer test`, `newman run`)
- Feature documentation updated in `docs/` if user-facing (ADR-010)
- Dutch (`nl_NL`) and English (`en_US`) translation strings added for any new user-facing strings (ADR-007)
- `openspec validate` passes
