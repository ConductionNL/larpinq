# Test Plan: character-player-picker

## Test Cases

### TC-1: ocName renders and saves as a player UUID dropdown
- **spec_ref**: `openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-character-crud-operations`
- **type**: functional
- **persona**: N/A
- **preconditions**: players "Alice", "Bob" exist; character create modal is open
- **steps**: open the character create modal, fill name "Sir Lancelot", select "Alice" from the OC Name dropdown, save
- **expected result**: the saved character's `ocName` equals Alice's player UUID (not the string "Alice")
- **test command**: `/test-functional`

### TC-2: ownerUid is not an editable form field
- **spec_ref**: `openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-character-crud-operations`
- **type**: functional
- **persona**: N/A
- **preconditions**: a character exists and is open in edit mode
- **steps**: open the character edit modal and inspect the rendered fields
- **expected result**: no `ownerUid` input is rendered anywhere in the form
- **test command**: `/test-functional`

### TC-3: ownerUid is materialised from the linked player's userUid
- **spec_ref**: `openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-player-reference-resolution-on-character-objects`
- **type**: functional
- **persona**: N/A
- **preconditions**: player "Alice" exists with `userUid = "alice"`
- **steps**: create a character with `ocName` set to Alice's UUID and save
- **expected result**: the saved character's `ownerUid` equals `"alice"`
- **test command**: `/test-functional`

### TC-4: player.userUid links to a Nextcloud account
- **spec_ref**: `openspec/changes/character-player-picker/specs/events-players/spec.md#requirement-player-to-nextcloud-account-link`
- **type**: functional
- **persona**: N/A
- **preconditions**: player "Bob" exists, has no `userUid` set
- **steps**: open the player edit modal, set the Nextcloud-user field to "bob", save
- **expected result**: `player.userUid` equals `"bob"`; if the nc-vue `format:"user"` widget has not yet shipped, the field renders as a plain text input and still stores the typed value correctly
- **test command**: `/test-functional`

### TC-5: character-approved notification reaches the linked player
- **spec_ref**: `openspec/changes/character-player-picker/specs/notifications/spec.md#requirement-structured-owner-uid-prerequisite-for-per-player-notifications`
- **type**: functional
- **persona**: N/A
- **preconditions**: character "Sir Lancelot" linked to player "Alice" (`userUid = "alice"`), submitted for approval
- **steps**: as a `gamemasters`-group user, approve the character
- **expected result**: Nextcloud user `alice` receives an `nc-notification`, in addition to manage-ACL holders and the `gamemasters` group
- **test command**: `/test-functional`

### TC-6: unlinked character does not error on approval
- **spec_ref**: `openspec/changes/character-player-picker/specs/notifications/spec.md#requirement-structured-owner-uid-prerequisite-for-per-player-notifications`
- **type**: regression
- **persona**: N/A
- **preconditions**: a character exists whose `ocName` does not resolve to any player object (legacy free-text value, not yet re-picked)
- **steps**: approve the character
- **expected result**: approval succeeds without error; only manage-ACL holders and `gamemasters` are notified (no crash from an unresolved `@ref.player`)
- **test command**: `/test-functional`

### TC-7: approved renders as a toggle and drives the same lifecycle
- **spec_ref**: `openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-character-crud-operations`
- **type**: functional
- **persona**: N/A
- **preconditions**: a character exists with `approved = "no"` and is open in edit mode
- **steps**: open the character edit modal, locate the "Approved" field, flip the toggle, save
- **expected result**: the "Approved" field renders as a toggle (not a select box); flipping it and saving stores `approved = "approved"`, and the existing `x-openregister-lifecycle`/`character-approved` notification behavior fires exactly as it did with the old select box
- **test command**: `/test-functional`

### TC-8: register JSON remains valid OpenAPI + x-openregister
- **spec_ref**: `openspec/changes/character-player-picker/specs/character-management/spec.md#requirement-character-crud-operations`
- **type**: regression
- **persona**: N/A
- **preconditions**: `lib/Settings/larpingapp_register.json` edited
- **steps**: re-import the register via `ConfigurationService.importFromApp()` (app enable/upgrade)
- **expected result**: import succeeds with no schema validation errors; existing `character-submitted` and `character-approved` notification rules are unaffected
- **test command**: `/test-regression`

## Coverage Summary
- CHAR-006, CHAR-011 (character-management): covered by TC-1, TC-2, TC-3
- REQ-092 (character-management, `@ref.player` resolution): covered by TC-3
- REQ-041 (events-players, `player.userUid`): covered by TC-4
- notifications "Structured owner uid prerequisite" (MODIFIED): covered by TC-5, TC-6
- `approved` toggle rendering (character-management): covered by TC-7
- Register JSON validity / no regression to existing notification rules: covered by TC-8

## Out of Scope
- Migration correctness for legacy `ocName` free-text values — there is no
  backfill; re-linking happens going forward via the inline select-or-create
  dropdown (TC-1 covers picking an existing player; creating a new player inline
  from the character form follows the same `CnResourceSelect` allowCreate flow
  used elsewhere in the fleet, not re-tested here).
- The three nc-vue auto-form widgets' internal rendering/accessibility — external
  dependency, tracked in the `nextcloud-vue` repo.
