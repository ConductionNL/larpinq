# Design: characters-custom-fields

## Context

Read at development `2af18d8`.

- `character` (schema version 1.4.0) has fixed properties; new ones arrive
  only through `lib/Settings/register.d/` fragments deep-merged by
  `ConfigFileLoaderService` and imported with a version that includes the
  fragment signature (`register.d/README.md`).
- Worlds are `setting` objects; mechanics carry an optional `setting` and
  pickers filter on it (`x-relation-filter`).
- `lib/Listener/CharacterRequirementListener.php` vetoes character writes on
  OpenRegister's pre-write events and diff-scopes on changed fields.
- Field level security in OpenRegister (spec `row-field-level-security`) works
  per property, not per key inside an object.
- CharacterDetail (`src/manifest.json`) uses `data` widgets with fixed
  `include` lists, which cannot render fields that exist only as data. The
  registry allows `kind: 'section'` components (EventRoster precedent).

## Goals / Non-Goals

**Goals**: game masters add fields without a developer; players fill in the
ones meant for them; private fields stay private.

**Non-Goals**: computed fields, other schemas, editing the OpenRegister schema.

## Decisions

### D1. Definitions as objects, values on the character

Schema `characterField` (slug `larping_character_field`): `setting` (uuid
`$ref` setting, empty means every world), `label`, `key` (lower case, unique
per world), `fieldType` (`text`, `number`, `choice`, `yes-no`), `choices`
(array of strings, for `choice`), `visibility` (`gamemasters`, `owner`),
`order` (integer), `help` (text). Authorization: create, update and delete by
`gamemasters`; read by `larpers`.

Alternative: add real properties to the `character` schema from the UI through
OpenRegister's schema API. Rejected: the next register import from larpinq's
own JSON would overwrite them, and a GM-typed property name would become a
database column for every world.

### D2. Two value properties

`character.customFields` (object, key to value) holds fields with visibility
`owner`; `character.customFieldsPrivate` (object) holds `gamemasters` fields.
Property authorization on `customFieldsPrivate`: read and update by
`gamemasters`. On `customFields`: read by `gamemasters` and the owner (match
`ownerUid` on `$userId`), update likewise. Splitting by visibility is what lets
OpenRegister strip the private values on every path.

### D3. Validation

`CustomFieldValidator::validate(array $values, array $definitions)` checks
each key exists in the character's world (or global), the value matches the
type (number is numeric, yes-no is boolean, choice is one of `choices`), and
that a value sits in the property its visibility names. The requirement
listener calls it when `customFields` or `customFieldsPrivate` changed and
vetoes with a field error per key.

### D4. The section

`CharacterCustomFields.vue`, a body section on CharacterDetail below "Game
state & notes", loads the definitions for the character's world through the
object store, renders one input per definition in `order` (text input, number
input, `NcSelect` with `inputLabel`, `NcCheckboxRadioSwitch`), and saves the two
objects with one PATCH. A user who can read only `customFields` sees only those
definitions. Orphaned keys show to game masters, read-only.

### D5. Managing definitions

A `CharacterFields` index and `CharacterFieldDetail` detail page in a manifest
fragment, under the world menu group. A definition's world filter matches the
Settings (worlds) pages.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Field definitions | Declarative, a register schema | Ordinary objects. |
| Who reads which values | Declarative, property authorization | OpenRegister strips per property. |
| Value validation against definitions | Imperative, the existing pre-write listener | hydra ADR-031 exception 2: the valid shape of a character property depends on other objects (the definitions of its world). |
| Rendering fields that exist as data | A registered section component | A `data` widget needs a fixed field list. |

## Seed data

World "Aldmoor": definitions "Bloodline" (choice: human, elven, dwarven,
owner), "Patron god" (text, owner), "Scars" (number, owner) and "True
allegiance" (choice: crown, rebels, none, gamemasters). "Mirela the Wanderer"
holds bloodline elven, patron god "The Grey Lady", scars 2, and true
allegiance rebels.

## Risks / Trade-offs

- [Exports] Values export as a JSON column; per-field columns are out of scope.
- [Key changes] Renaming a key orphans values; the definition page warns and
  keeps `key` read-only after the first value exists.

## Migration

None.

## Open Questions

None.
