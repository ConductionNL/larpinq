# game-mechanics Specification

## MODIFIED Requirements

### Requirement: Item CRUD

The system MUST support creating, reading, updating, and deleting items. Items represent magical or special objects that characters can hold. The Items index, detail, and dashboard-widget pages in `src/manifest.json` MUST reference the item schema by its real, namespaced slug `larping_item` (not the bare, collision-prone slug `item`) so OpenRegister resolves them to larpingapp's own item schema rather than another installed app's `item` schema.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| MECH-040 | System MUST support CRUD for items via `/api/objects/item` | MUST | Implemented |
| MECH-041 | Item MUST require a `name` field | MUST | Implemented |
| MECH-042 | Item MUST support assigning multiple effects via `effects[]` UUID array | MUST | Implemented |
| MECH-043 | Item MUST support `unique` flag to indicate one-of-a-kind artifacts | MUST | Implemented |
| MECH-044 | Item MUST track which characters hold it via the `characters[]` array | MUST | Implemented |
| MECH-045 | Items MUST be accessible from the main navigation sidebar with Sword icon | MUST | Implemented |
| MECH-046 | Item detail view MUST show relations and audit trail tabs | MUST | Implemented |
| MECH-047 | Every `"schema"` reference to the item schema in `src/manifest.json` (index page, detail page, dashboard KPI widget, and any object-list widget) MUST use the real slug `larping_item`, matching `lib/Settings/larpingapp_register.json`'s `x-openregister-schema-slug` | MUST | Implemented |
<!-- Previous behavior: src/manifest.json referenced the item schema by the bare slug "item" at 4 sites (dashboard KPI widget, index page config, detail page, and the effect-detail page's "items granting this effect" object-list widget). Because OpenRegister resolves schema slugs globally across all installed apps, "item" resolved to a different app's item schema instead of larpingapp's own, so the Items create/list/detail forms rendered the wrong schema's fields. -->

#### Scenario: Create a unique item

- GIVEN an effect "Arcane Power +10" exists
- WHEN a game master creates item "Hand of Vecna" with unique=true, effects=["arcane-effect"]
- THEN the item MUST be created as a unique artifact
- AND only one character SHOULD hold it at a time

#### Scenario: Create a non-unique item

- GIVEN an effect "Attack +1" exists
- WHEN a game master creates item "Generic Magic Sword" with unique=false, effects=["attack-effect"]
- THEN the item MUST be created as non-unique
- AND multiple characters MAY hold instances of it

#### Scenario: Item effects applied to character

- GIVEN item "Ring of Protection" has effect "+3 Defense"
- AND character "Frodo" has this item assigned
- WHEN stats are calculated
- THEN Defense MUST increase by 3
- AND the audit trail MUST show the item's effect

#### Scenario: Track item holders

- GIVEN item "Excalibur" is assigned to character "Arthur"
- WHEN viewing the item details
- THEN the characters[] array MUST include Arthur's UUID

#### Scenario: Items page resolves larpingapp's own item schema

- GIVEN `src/manifest.json`'s Items index page declares `config.schema`
- WHEN a user opens the Items page and clicks "New"
- THEN the create form MUST render larpingapp's own item fields (`name`, `effects`, `unique`, `characters`, etc.)
- AND it MUST NOT render fields from any other installed app's schema also named `item`
