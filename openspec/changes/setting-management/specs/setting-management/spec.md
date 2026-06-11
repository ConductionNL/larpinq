---
status: draft
---

# Setting Management

## Purpose

Make the LARP setting (world/campaign) a first-class managed entity — the
app's headline promise ("Manage your live roleplaying setting"). Covers the
repurposed `setting` schema, the management UI, optional scoping of game
entities to a setting, the per-user active-setting lens on lists and
pickers, and the guarded archive/delete lifecycle. Scoping is an
organisational lens, not a security boundary; authorization stays
OR-delegated (ADR-022).

## ADDED Requirements

### Requirement: Setting MUST be a managed campaign entity

The `setting` object type MUST represent a LARP world/campaign with schema
`{name (required), description, status}` where `status` is `active` or
`archived` (default `active`), replacing the vestigial `{name, description,
value}` key-value shape (schema version 2.0.0). Settings MUST be manageable
through the standard entity pattern: a `Settings` index manifest page with
search and pagination, create/edit modals, and a navigation entry visually
distinct from the "Game Settings" app-config page (Globe icon, listed with
the content types).

#### Scenario: Create a setting

- GIVEN a game master opens the Settings index page
- WHEN they create setting "Summer Realm 2026" with a description and no explicit status
- THEN the setting MUST be stored with status `active`
- AND it MUST appear in the Settings index list

#### Scenario: Vestigial key-value shape is gone

- GIVEN the register configuration has been re-imported
- WHEN the `setting` schema is inspected
- THEN it MUST be version 2.0.0 with properties name, description, and status
- AND it MUST NOT define the legacy `value` property

### Requirement: Game entities MUST support optional setting scoping

The `character`, `event`, `skill`, `item`, `condition`, `ability`, and
`effect` schemas MUST gain an optional `setting` property holding the UUID of
a setting object. An entity with the property set belongs to that setting;
an entity without it is shared and MUST be treated as belonging to every
setting. The `player` schema MUST NOT be scoped (players are real people who
span campaigns). Existing data (no `setting` value) MUST remain fully valid
and visible without migration.

#### Scenario: Scope a character to a setting

- GIVEN setting "Summer Realm 2026" exists
- WHEN a GM edits character "Sir Lancelot" and assigns the setting
- THEN the character's `setting` property MUST store the setting's UUID
- AND the character MUST appear in lists when "Summer Realm 2026" is the active setting

#### Scenario: Unscoped entity is shared across settings

- GIVEN condition "Poisoned" has no `setting` value
- WHEN any setting is active
- THEN "Poisoned" MUST appear in the Conditions index and in the Add-Condition picker

#### Scenario: Stranded setting reference does not break the entity

- GIVEN skill "Relic Lore" references a setting UUID that no longer exists
- WHEN lists are filtered or the skill detail is opened
- THEN the skill MUST be treated as shared (never hidden everywhere, never an error)

### Requirement: A per-user active setting MUST filter lists server-side

The user MUST be able to choose an active setting (any `active` setting, or
"All settings") via a switcher in the app navigation. The choice MUST
persist per user through the preferences API (REQ-PREF-001/002). While a
setting is active, index pages and dashboard widgets MUST show only entities
scoped to it plus shared entities, filtered server-side via OpenRegister
object filters so pagination, search, and counts stay correct. Detail pages,
deep links, and relations tabs MUST NOT be filtered (the lens narrows lists,
it never blocks direct access). If the persisted setting was archived or
deleted, the lens MUST fall back to "All settings".

#### Scenario: Switching the active setting filters the characters index

- GIVEN characters "Knight A" (scoped to "Summer Realm"), "Robot B" (scoped to "Winter Station"), and "Wanderer C" (unscoped)
- WHEN the user selects "Summer Realm" in the setting switcher and opens the Characters index
- THEN the list MUST contain "Knight A" and "Wanderer C"
- AND MUST NOT contain "Robot B"
- AND the filter MUST be applied in the OpenRegister list query, not by trimming a fetched page

#### Scenario: Active setting persists across sessions

- GIVEN the user selected "Summer Realm" as active setting
- WHEN they log in again later (any device)
- THEN the switcher MUST show "Summer Realm" as active
- AND lists MUST be filtered accordingly

#### Scenario: All settings shows everything

- WHEN the user selects "All settings"
- THEN index pages and dashboard widgets MUST show entities of every setting and shared entities

#### Scenario: Dashboard respects the lens

- GIVEN the dashboard KPI widgets count characters and events
- WHEN "Summer Realm" is the active setting
- THEN the counts MUST reflect only Summer Realm plus shared entities

#### Scenario: Deep link to an out-of-lens character still works

- GIVEN "Winter Station" character "Robot B" and active setting "Summer Realm"
- WHEN the user opens a direct link to Robot B's detail page
- THEN the detail page MUST render normally

### Requirement: Assignment pickers MUST default to the active setting

The Add-Skill/Item/Condition/Event modals MUST filter their pickers to
entities of the active setting plus shared entities by default. A "show all
settings" toggle MUST allow cross-setting picks; a selected entity from
another setting MUST be visibly flagged with that setting's name. Cross-
setting assignment MUST NOT be rejected server-side (the lens is
organisational, not a rule).

#### Scenario: Picker filtered by active setting

- GIVEN skills "Sword Mastery" (Summer Realm), "Laser Calibration" (Winter Station), and "First Aid" (unscoped)
- AND the active setting is "Summer Realm"
- WHEN a GM opens the Add-Skill modal for a character
- THEN the picker MUST offer "Sword Mastery" and "First Aid" only

#### Scenario: Cross-setting pick is flagged, not blocked

- GIVEN the active setting is "Summer Realm"
- WHEN the GM enables "show all settings" and selects "Laser Calibration" (Winter Station)
- THEN the selection MUST display the flag "Winter Station"
- AND saving MUST succeed

### Requirement: Setting detail MUST give a campaign overview

A `SettingDetail` manifest page MUST show the setting's name, description,
and status, and an overview of the entities scoped to it (at minimum
characters and events, with the mechanics types as counts or tabs), so a GM
can answer "what belongs to this campaign" in one place.

#### Scenario: Detail lists scoped content

- GIVEN setting "Summer Realm 2026" with 3 scoped characters and 2 scoped events
- WHEN the GM opens the setting's detail page
- THEN the page MUST show the 3 characters and 2 events as scoped content
- AND shared (unscoped) entities MUST NOT be listed as belonging to the setting

### Requirement: Setting deletion MUST be guarded; archiving is the end-of-campaign path

Deleting a setting that still has scoped entities MUST be blocked in the UI,
with the confirmation dialog listing per-type counts of scoped entities. An
empty setting MUST be deletable with the standard confirmation. Setting
`status` to `archived` MUST remove the setting from the switcher's default
list and from picker defaults, while its detail page and all scoped data
remain readable. Consumers MUST tolerate a setting deleted via the raw API
by treating the stranded references as shared.

#### Scenario: Delete blocked while populated

- GIVEN setting "Summer Realm 2026" has 3 characters and 1 event scoped to it
- WHEN the GM attempts to delete it
- THEN the deletion MUST be blocked
- AND the dialog MUST state the counts (3 characters, 1 event) and suggest reassigning or archiving

#### Scenario: Archive a finished campaign

- GIVEN setting "Summer Realm 2025" with scoped data
- WHEN the GM sets its status to `archived`
- THEN it MUST disappear from the setting switcher's default options
- AND its detail page and scoped characters/events MUST remain readable

#### Scenario: Empty setting can be deleted

- GIVEN setting "Test World" with no scoped entities
- WHEN the GM deletes it and confirms
- THEN the setting MUST be removed
