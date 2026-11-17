# Design: rules-rulebook-and-spell-lists

## Context

Read at development `77c85f0`, with `worlds-lore-pages` (`larping_lore_page`,
category `rules`, visibility and reveal rules).

- `skill` (slug `larping_skill`): name, description, effect (text), effects,
  requiredSkills, requiredStats, requiredConditions, requiredEffects,
  requiredScore, setting; `x-openregister-shareable`. Costs are effects on the
  XP ability.
- `lib/Service/SkillRequirementService.php:203-229` `evaluateBudget()` checks
  one ability, the XP ability (`resolveXpAbility()`), for a negative value.
- The portal contribution offers `skillCatalog`, `itemCatalog` and
  `conditionCatalog` to the `player` audience.
- The registry allows `kind: 'page'` components (`SkillTree`).

## Goals / Non-Goals

**Goals**: spells and powers as their own lists with their own pool; a
readable, printable rulebook for players.

**Non-Goals**: per-character rulebooks, charges.

## Decisions

### D1. Skill lists

`skillList` (slug `larping_skill_list`): `setting`, `name`, `kind` (`skills`,
`spells`, `powers`), `costAbility` (uuid `$ref` ability), `description`,
`order`. `skill.list` (uuid `$ref` skillList). `hiddenFromRulebook` (boolean)
on skill, item and condition. Game masters and rules marshals write; larpers
read.

### D2. Budget per pool

`evaluateBudget()` becomes a loop over the cost abilities in play: the XP
ability plus the `costAbility` of each list used by the character's skills. The
report's `budget` stays the XP entry for compatibility and gains `budgets[]`
with one entry per pool (`ability`, `value`, `shortfall`, `ok`). A write is
refused when any pool goes below zero. The Stats tab of
`characters-stat-sheet-panel` shows each pool.

### D3. The Rulebook page

`Rulebook.vue` (registry `page`), route `/rulebook/:world?`: chapters from
`lorePage` with `category = rules` for the world, ordered, rendered from
markdown with the same renderer as the lore pages; then a reference built from
abilities, skill lists with their skills (cost and pool, prerequisites by
name, effect text), items and conditions, leaving out `hiddenFromRulebook`
objects. A print stylesheet sets one chapter per page. Reads use the object
store, so the read rules of lore pages apply.

### D4. Portal

`rulebookChapters` (lore pages of category rules, the audience's read rules)
and `skillLists` (lists with their skills) as catalogue collections, next to
the existing catalogues.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Lists, flags | Declarative, register fragment | Schemas and properties. |
| Budget per pool | Imperative, the existing requirement service | The engine already computes the pools; the check spans skills, effects and abilities. |
| The Rulebook page | A registered page component over the object store | A composed read of five schemas with print layout. |

## Seed data

World Aldmoor: lists "Martial skills" (skills, XP), "Arcane spells" (spells,
Mana) with "Firebolt" (3 mana) and "Ward" (2 mana), requiring skill "Arcane
lore"; chapters "How combat works" and "Casting a spell" (rules, players).

## Risks / Trade-offs

- [Report shape] Consumers of `budget` keep working; new consumers read
  `budgets[]`.

## Migration

None: skills without a list stay general and pay from XP.

## Open Questions

None.
