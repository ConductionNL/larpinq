# Tasks: larpinq-mcp-adoption

## Implementation Tasks

### Task 1: Add the `register.d/larpingapp-mcp-adoption.json` fragment, including the three bounded write verbs
- **spec_ref**: `openspec/changes/larpinq-mcp-adoption/specs/larpinq-mcp-adoption/spec.md#req-001-curated-read-only-mcp-dialect-on-6-referencelookup-schemas`
- **files**: `lib/Settings/register.d/larpingapp-mcp-adoption.json`
- **acceptance_criteria**:
  - GIVEN the exact JSON in design.md WHEN it is written to `lib/Settings/register.d/larpingapp-mcp-adoption.json` THEN all 9 curated schemas carry a `configuration.x-openregister-mcp` block with `enabled: true`
  - GIVEN the same file WHEN inspected THEN `character` has `search`/`get`/`create` only, `xpAward` has `search`/`get`/`create` only, `attendance` has `search`/`get`/`update` only, the other 6 schemas have `search`/`get` only, and no `delete` verb appears anywhere
  - GIVEN a dev Nextcloud instance WHEN `larpinq.character.create` is called via MCP THEN the created object's `approved` field is `"no"`, and WHEN `larpinq.attendance.update` sets `status` THEN `checkedInAt`/`checkedInBy` remain server-stamped and are not settable from the call's input
- [ ] Implement
- [ ] Test

### Task 2: Cross-check every `search.filters` entry against real schema properties
- **spec_ref**: `openspec/changes/larpinq-mcp-adoption/specs/larpinq-mcp-adoption/spec.md#req-005-every-declared-search-filter-names-a-real-schema-property`
- **files**: `lib/Settings/register.d/larpingapp-mcp-adoption.json`, `lib/Settings/larpingapp_register.json` (read-only reference), `lib/Settings/register.d/event-checkin-roster.json` (read-only reference for `attendance`)
- **acceptance_criteria**:
  - GIVEN each curated schema's `search.filters` list WHEN diffed against that schema's `properties` map (monolith, plus the `event-checkin-roster.json` fragment for `attendance`) THEN every filter name is present as a key
  - GIVEN the merged register (monolith + all fragments) WHEN validated by OpenRegister's `McpAnnotationValidator` THEN zero `mcp-unknown-filter-property` errors are reported
- [ ] Implement
- [ ] Test

### Task 3: Validate JSON, run `openspec validate`, re-import into a dev instance, and confirm the write-verb RBAC gates
- **spec_ref**: `openspec/changes/larpinq-mcp-adoption/specs/larpinq-mcp-adoption/spec.md#req-007-mcp-dialect-is-declared-via-a-register-fragment-not-the-monolith`
- **files**: `lib/Settings/register.d/larpingapp-mcp-adoption.json`
- **acceptance_criteria**:
  - GIVEN the new fragment WHEN run through `python3 -m json.tool` THEN it parses with zero errors
  - GIVEN a dev Nextcloud instance WHEN `ConfigFileLoaderService::loadConfigurationFile()` re-runs (fragment signature changed) THEN the merged register imports without error and all 21 derived tools (9 schemas × search/get, plus 3 extra write verbs) appear in OpenRegister's MCP tool listing
  - GIVEN a non-gamemaster caller WHEN `larpinq.xpAward.create` or `larpinq.attendance.update` is invoked THEN OpenRegister's object-level `authorization` gate rejects the call
- [ ] Implement
- [ ] Test

### Task 4: Add a CHANGELOG entry
- **spec_ref**: `openspec/changes/larpinq-mcp-adoption/specs/larpinq-mcp-adoption/spec.md#req-008-mcp-tools-are-derived-without-app-level-php`
- **files**: `CHANGELOG.md`
- **acceptance_criteria**:
  - GIVEN `CHANGELOG.md` WHEN this change is applied THEN a new entry describes the 9-schema MCP dialect adoption (6 read-only + 3 bounded writes) under an "Unreleased" or next-version heading
- [ ] Implement
- [ ] Test

## Verification
- [ ] All tasks checked off
- [ ] `openspec validate` passes
- [ ] Manual testing against acceptance criteria
- [ ] Code review against spec requirements

## Tests (company-wide ADR-009)
- N/A — this change is config-only (a JSON register fragment); it adds no PHP business logic, no API endpoint, and no UI, so the docker-exec PHPUnit rule does not apply. Verification is the import/tool-listing and write-bound checks in Task 3, plus OpenRegister's own `McpAnnotationValidator` and object-level `authorization` enforcement (cross-repo, already covered by OpenRegister's test suite).

## Documentation (company-wide ADR-010)
- N/A — no user-facing feature or UI is introduced; the audience for this change is an AI agent (Hermiq) via MCP tool listings, not a human Nextcloud user. No screenshot applies.

## i18n (company-wide ADR-005)
- N/A — no new user-facing strings. Tool `description` text is agent-facing prose (English, per fleet convention for agent-facing text), not UI copy subject to Dutch/English translation.
