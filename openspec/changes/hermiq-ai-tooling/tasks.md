# Tasks: hermiq-ai-tooling

## Implementation Tasks

### Task 1: Implement `EventRosterService::awardXpToAttendees()` with the `#[McpTool]` attribute
- **spec_ref**: `openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-001--a-curated-event-scoped-xp-award-tool-exists`
- **files**: `lib/Service/EventRosterService.php`
- **acceptance_criteria**:
  - GIVEN an event with checked-in, registered, and no-show attendance rows WHEN the method runs THEN exactly one `xpAward` per checked-in character is created with server-stamped `awardedAt`/`awardedBy`, and no award for any other row
  - GIVEN an event with zero checked-in rows WHEN the method runs THEN it throws/returns an explanatory error and creates nothing
  - GIVEN the attribute WHEN inspected THEN it declares `scope: 'create'`, `readOnlyHint: false`, `destructiveHint: false`, `idempotentHint: false`
  - GIVEN a partial failure mid fan-out WHEN the response is inspected THEN it lists per-character created/failed results
- [ ] Implement
- [ ] Test

### Task 2: Expose the calculated character sheet as a read tool
- **spec_ref**: `openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-003--a-curated-calculated-character-sheet-read-tool-exists`
- **files**: `lib/Service/CharacterService.php`
- **acceptance_criteria**:
  - GIVEN a character with stat-affecting skills/items/conditions WHEN `getCharacterSheet` runs THEN the result matches `calculateCharacter()`'s totals and contains no `slNotesPrivate` key
  - GIVEN a character the principal cannot read WHEN invoked THEN the result is not-found, identical in shape to a nonexistent id
  - GIVEN any invocation WHEN it completes THEN no register object was created or modified
- [ ] Implement
- [ ] Test

### Task 3: Add `lib/Mcp/LarpingappScannableServices.php` and register the DI alias
- **spec_ref**: `openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-004--curated-tools-are-exposed-via-the-scannable-services-opt-in`
- **files**: `lib/Mcp/LarpingappScannableServices.php`, `lib/AppInfo/Application.php`
- **acceptance_criteria**:
  - GIVEN the class WHEN scanned THEN it implements `OCA\OpenRegister\Mcp\IMcpScannableServices`, carries SPDX + `@spec` docblock tags (DocuDesk `DocudeskScannableServices` pattern), and returns exactly `EventRosterService::class` and `CharacterService::class`
  - GIVEN a dev instance WHEN the tool catalog is listed THEN it is exactly the 21 derived tools plus the 2 curated tools
- [ ] Implement
- [ ] Test

### Task 4: Verify the governance layering end to end
- **spec_ref**: `openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-002--xp-awards-and-sheet-affecting-writes-require-a-game-master-approval-gate`
- **files**: `tests/` (integration), dev instance verification notes
- **acceptance_criteria**:
  - GIVEN Hermiq's grant editor WHEN the two tools are inspected THEN `awardXpToAttendees` classifies as an approval-required write and `getCharacterSheet` as an ungated read
  - GIVEN an approved gate for a five-character event WHEN the chat flow "award 5 XP to everyone who attended Saturday's event" completes THEN five awards exist, each attributed `mcp` with the invoking principal
  - GIVEN a rejected gate WHEN the flow ends THEN zero awards exist
  - GIVEN a non-gamemaster caller WHEN the fan-out attempts its writes THEN OR's `authorization.create` on `xpAward` rejects them
- [ ] Implement
- [ ] Test

### Task 5: Quality gates and CHANGELOG
- **spec_ref**: `openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-005--agent-created-xp-awards-are-attributable-and-grants-are-default-deny`
- **files**: `CHANGELOG.md`, touched PHP files
- **acceptance_criteria**:
  - GIVEN the touched/new PHP files WHEN `composer check:strict` runs THEN zero new errors or warnings against a self-measured baseline
  - GIVEN `CHANGELOG.md` WHEN this change is applied THEN a new entry describes the curated tool surface under an "Unreleased" or next-version heading
- [ ] Implement
- [ ] Test
