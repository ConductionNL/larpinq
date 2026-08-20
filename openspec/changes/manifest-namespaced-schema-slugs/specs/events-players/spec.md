# events-players Specification

## MODIFIED Requirements

### Requirement: Event CRUD Operations

The system MUST support creating, reading, updating, and deleting LARP events with date ranges, location, player assignments, and effect associations. The Events index, detail, dashboard-widget, and onboarding pages in `src/manifest.json` MUST reference the event schema by its real, namespaced slug `larping_event` (not the bare, collision-prone slug `event`) so OpenRegister resolves them to larpingapp's own event schema rather than another installed app's `event` schema.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| EVT-001 | Create events with name, description, start date, end date, and location | MUST | Implemented |
| EVT-002 | Update existing events with all editable fields | MUST | Implemented |
| EVT-003 | Delete events with confirmation dialog | MUST | Implemented |
| EVT-004 | List events with search and pagination | MUST | Implemented |
| EVT-005 | View event details with Characters (relations) and Logging tabs | MUST | Implemented |
| EVT-006 | Assign players to events via `players[]` UUID array | MUST | Implemented |
| EVT-007 | Assign effects to events via `effects[]` UUID array for post-event stat modifications | MUST | Implemented |
| EVT-008 | Event effects MUST be applied to associated characters during stat calculation | MUST | Implemented |
| EVT-009 | Event name MUST be required | MUST | Implemented |
| EVT-010 | Events MUST be accessible from the main navigation sidebar | MUST | Implemented |
| EVT-011 | Every `"schema"` reference to the event schema in `src/manifest.json` (index page, detail page, dashboard KPI/object-list widgets, setting-detail page's event widgets, and the onboarding `advanceOn` rule) MUST use the real slug `larping_event`, matching `lib/Settings/larpingapp_register.json`'s `x-openregister-schema-slug` | MUST | Implemented |
<!-- Previous behavior: src/manifest.json referenced the event schema by the bare slug "event" at 7 sites (onboarding advanceOn rule, dashboard KPI widget, dashboard recent-events widget, index page config, detail page, and two setting-detail page widgets). Because OpenRegister resolves schema slugs globally across all installed apps, "event" resolved to openconnector's CloudEvents "event" schema (fields: Source, Type, Spec Version, datacontenttype, dataschema…) instead of larpingapp's own event schema, so the Events create/list/detail forms rendered the wrong schema's fields. -->

#### Scenario: Create an event with effects

- GIVEN an effect "Event Blessing" (modifier +2, positive, targeting "Mana") exists
- WHEN a game master creates event "Summer LARP 2025" with start date 2025-06-01, end date 2025-06-03, location "Forest Camp"
- AND assigns the effect "Event Blessing"
- THEN the event MUST be stored with effects = [event-blessing-uuid]
- AND any character associated with this event MUST receive +2 Mana during stat calculation

#### Scenario: Update an event

- GIVEN event "Summer LARP 2025" exists
- WHEN the game master updates the end date to 2025-06-05 and adds a second effect
- AND saves the event
- THEN the event MUST be updated with the new end date and both effects
- AND characters associated with this event MUST have stats recalculated with both effects

#### Scenario: Delete an event

- GIVEN event "Summer LARP 2025" exists
- WHEN the game master deletes the event
- THEN the event MUST be removed
- AND characters that had this event assigned MUST retain it in their events[] array (stale reference)
- AND stat recalculation for those characters MUST gracefully skip the missing event

#### Scenario: List events with search

- GIVEN events "Summer LARP 2025", "Winter Gathering", and "Spring Festival" exist
- WHEN the user types "summer" in the search field
- THEN after debounce the list MUST show only "Summer LARP 2025"

#### Scenario: View event participants via relations tab

- GIVEN event "Summer LARP 2025" has characters associated with it
- WHEN a user views the event detail page
- AND opens the "Characters" tab
- THEN the related characters MUST be listed via the relations endpoint

#### Scenario: Events page resolves larpingapp's own event schema

- GIVEN `src/manifest.json`'s Events index page declares `config.schema`
- WHEN a user opens the Events page and clicks "New"
- THEN the create form MUST render larpingapp's own event fields (`name`, `startDate`, `endDate`, `location`, etc.)
- AND it MUST NOT render CloudEvents fields (`Source`, `Type`, `Spec Version`, `datacontenttype`, `dataschema`) from openconnector's colliding `event` schema
