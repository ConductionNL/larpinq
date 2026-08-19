# Tasks: leaf-integrations

## Implementation Tasks

### Task 1: Measure the fragment array-merge semantics before writing the fragment
- **spec_ref**: `openspec/changes/leaf-integrations/specs/leaf-integrations/spec.md#req-001--event-declares-the-talk-polls-and-deck-leaves`
- **files**: `lib/Service/ConfigFileLoaderService.php` (read-only), `lib/Settings/register.d/*.json` (read-only)
- **acceptance_criteria**:
  - GIVEN `mergeRegisterFragments()` run over the existing fragment set plus a probe fragment adding `linkedTypes` to `event` WHEN the merged `event.configuration.linkedTypes` is inspected THEN the mechanism (array union vs last-writer-wins) is recorded in design.md
  - GIVEN a last-writer-wins result WHEN the fragment is authored THEN it declares the full six-value array instead of the three-value delta
- [ ] Implement
- [ ] Test

### Task 2: Add the `register.d/leaf-integrations.json` fragment
- **spec_ref**: `openspec/changes/leaf-integrations/specs/leaf-integrations/spec.md#req-003--leaves-are-declared-via-a-register-fragment-not-the-monolith`
- **files**: `lib/Settings/register.d/leaf-integrations.json`
- **acceptance_criteria**:
  - GIVEN the new fragment WHEN run through `python3 -m json.tool` THEN it parses with zero errors
  - GIVEN the merged register WHEN inspected THEN `event.configuration.linkedTypes` contains all of `calendar`, `maps`, `forms`, `talk`, `polls`, `deck` and `setting.configuration.linkedTypes` contains `talk` and `polls` and no `deck`
  - GIVEN `larpingapp_register.json` and the six pre-existing fragments WHEN diffed against HEAD THEN they are byte-identical
- [ ] Implement
- [ ] Test

### Task 3: Re-import and verify the rendered leaf surface
- **spec_ref**: `openspec/changes/leaf-integrations/specs/leaf-integrations/spec.md#req-004--leaves-degrade-gracefully-when-their-app-is-absent`
- **files**: `lib/Settings/register.d/leaf-integrations.json`
- **acceptance_criteria**:
  - GIVEN a dev instance with Talk, Polls, and Deck enabled WHEN an event detail page renders THEN the talk, polls, and deck leaf tabs render via the registry and the calendar/maps/forms leaves are unchanged
  - GIVEN the setting detail page WHEN it renders THEN talk and polls leaves render and no deck leaf is present
  - GIVEN Deck disabled WHEN the event detail page renders THEN no deck tab is present and no error is raised
- [ ] Implement
- [ ] Test

### Task 4: Assert leaves stay read-only toward game state
- **spec_ref**: `openspec/changes/leaf-integrations/specs/leaf-integrations/spec.md#req-005--leaves-are-coordination-surfaces-not-game-state-write-paths`
- **files**: `tests/` (component/integration test)
- **acceptance_criteria**:
  - GIVEN a closed scheduling poll linked to an event WHEN the poll closes THEN the event object's `startDate`/`endDate` are unchanged
  - GIVEN the leaf surfaces WHEN exercised THEN no register write is issued by any leaf interaction
- [ ] Implement
- [ ] Test

### Task 5: Add a CHANGELOG entry
- **spec_ref**: `openspec/changes/leaf-integrations/specs/leaf-integrations/spec.md#req-001--event-declares-the-talk-polls-and-deck-leaves`
- **files**: `CHANGELOG.md`
- **acceptance_criteria**:
  - GIVEN `CHANGELOG.md` WHEN this change is applied THEN a new entry describes the talk/polls/deck leaf adoption under an "Unreleased" or next-version heading
- [ ] Implement
- [ ] Test
