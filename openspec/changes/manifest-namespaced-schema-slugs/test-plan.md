# Test Plan: manifest-namespaced-schema-slugs

## Test Cases

### TC-1: Create Event shows larpingapp's own event fields
- **spec_ref**: `openspec/changes/manifest-namespaced-schema-slugs/specs/events-players/spec.md#requirement-event-crud-operations`
- **type**: functional
- **persona**: N/A
- **preconditions**: larpingapp is installed and enabled; `src/manifest.json`'s event references repointed to `larping_event`
- **steps**: navigate to the Events page, click "New"
- **expected result**: the create form shows larpingapp's own event fields — `name`, `description`, `startDate`, `endDate`, `location` — and does NOT show CloudEvents fields (`Source`, `Type`, `Spec Version`, `datacontenttype`, `dataschema`)
- **test command**: `/test-functional`

### TC-2: Create Item shows larpingapp's own item fields
- **spec_ref**: `openspec/changes/manifest-namespaced-schema-slugs/specs/game-mechanics/spec.md#requirement-item-crud`
- **type**: functional
- **persona**: N/A
- **preconditions**: larpingapp is installed and enabled; `src/manifest.json`'s item references repointed to `larping_item`
- **steps**: navigate to the Items page, click "New"
- **expected result**: the create form shows larpingapp's own item fields — `name`, `effects`, `unique`, `characters` — and does NOT show any field belonging to another installed app's `item` schema
- **test command**: `/test-functional`

### TC-3: Dashboard KPI/recent-list widgets and setting-detail event widgets resolve correctly
- **spec_ref**: `openspec/changes/manifest-namespaced-schema-slugs/specs/events-players/spec.md#requirement-event-crud-operations`
- **type**: regression
- **persona**: N/A
- **preconditions**: at least one larpingapp `event` and `item` object exists
- **steps**: open the larpingapp dashboard; open a setting's detail page
- **expected result**: the "Events" and "Items" dashboard KPI tiles count larpingapp's own event/item objects (not zero due to a schema mismatch); the setting-detail page's "Events in this setting" list shows larpingapp events, not an empty/mismatched list
- **test command**: `/test-regression`

### TC-4: Onboarding "create your first event" step still advances correctly
- **spec_ref**: `openspec/changes/manifest-namespaced-schema-slugs/specs/events-players/spec.md#requirement-event-crud-operations`
- **type**: functional
- **persona**: N/A
- **preconditions**: a fresh user has not completed onboarding
- **steps**: follow the onboarding tour to the "create-event" step, create and save an event
- **expected result**: the `advanceOn` `object-created` rule (now watching `larping_event`) fires and the tour advances to the next step
- **test command**: `/test-functional`

## Coverage Summary
- EVT-011 (events-players, manifest schema-slug requirement): covered by TC-1, TC-3, TC-4
- MECH-047 (game-mechanics, manifest schema-slug requirement): covered by TC-2, TC-3
- Existing Event/Item CRUD requirements (EVT-001..010, MECH-040..046): unaffected functionally, exercised incidentally by TC-1/TC-2

## Out of Scope
- Any change to `lib/Settings/larpingapp_register.json` (already correct) — not
  re-tested here.
- The upstream OpenRegister global-slug-resolution mechanism itself — tracked
  separately via `reference_or-cross-app-schema-slug-collision.md`, not this
  change's test surface.
