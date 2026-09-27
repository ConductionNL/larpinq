# Design: worlds-copy-ruleset

## Context

Read at development `2af18d8`.

- A world is a `setting` object (`name`, `description`, `status` active or
  archived). `ability`, `skill` (slug `larping_skill`), `item` (`larping_item`),
  `condition` and `effect` carry an optional `setting`; empty means shared
  across worlds (spec `setting-management`).
- References between rule objects: `effect.abilities[]`; `skill.effects[]`,
  `requiredSkills[]`, `requiredStats[]` (abilities), `requiredConditions[]`,
  `requiredEffects[]`; `item.effects[]`, `item.characters[]`;
  `condition.effects[]`, `condition.characters[]`.
- `lib/Service/RegisterObjectFetcher.php` offers `getObjects()` with filters
  and limits and `saveObject()` through OpenRegister with RBAC on.
- `lib/Controller/EventsController.php` shows the game master guard
  (`resolveGameMaster()`, group `gamemasters` or admin); ADR-002 asks new code to
  use one shared constant `Application::GM_GROUP`.
- `lib/Controller/SettingsController.php` is app configuration at
  `/api/settings`, so world endpoints need another name.

## Goals / Non-Goals

**Goals**: a complete, internally linked copy of a world's rules in one action.

**Non-Goals**: copying campaign history, partial copies, merging into a world.

## Decisions

### D1. What is copied, in which order

1. The world: new `setting` with the given name, status `archived` while the
   copy runs.
2. Abilities, then effects (remapping `abilities[]`), then skills, items and
   conditions (remapping `effects[]` and the `required*` arrays).
3. When present: `characterField` definitions and `lorePage` objects of the
   world (remapping `parent`).

Each copy gets `setting` = the new world. A reference to an object outside the
world (shared, or in another world) is kept as it is. `item.characters[]` and
`condition.characters[]` start empty.

### D2. The id map

`WorldCopyService::copy(string $worldId, string $name): array` keeps an
old-to-new id map per type and rewrites arrays before each save. Skills that
require skills are saved in two passes: first without `requiredSkills`, then
updated once every skill has its new id, so a cycle in the old data cannot
block the copy.

### D3. Endpoint and guard

`POST /api/worlds/{id}/copy` with body `{"name": "..."}` in a new
`WorldsController`, `#[NoAdminRequired]` with a game master check through
`Application::GM_GROUP` (adding the constant is ADR-002's follow-up). Answers
201 with `{world, counts: {abilities, effects, skills, items, conditions}}`.
Refuses above 2000 objects with 422.

### D4. The action

SettingDetail gets a header action "Copy world" opening `CopyWorldModal.vue`
(name field, a count of what will be copied, confirm), shown to game masters.
On success the modal links to the new world.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Copying a world's rules with remapped references | Imperative, a service and endpoint | hydra ADR-031 exception 2: a multi-schema copy with an id remap has no declarative form; OpenRegister's configuration export copies registers, not a subset of objects remapped into a new owner. |

## Seed data

No schema change. The Playwright test copies the seeded world Aldmoor to
"Aldmoor season 2" and checks that the copy of "Swordsmanship" points at the
copy of its strength effect.

## Risks / Trade-offs

- [Rollback on failure] Deleting created objects is best effort; the report
  lists anything it could not remove.
- [Audit] Each copy is a new object with its own audit trail; the copy does not
  carry the old history, which is intended.

## Migration

None.

## Open Questions

None.
