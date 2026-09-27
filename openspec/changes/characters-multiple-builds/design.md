# Design: characters-multiple-builds

## Context

Read at development `2af18d8`.

- `lib/Service/SkillRequirementService.php:86` `validate(array $candidate,
  array $oldCharacter = [])` returns `valid`, `requirements`, `budget` and
  `dependents` for a candidate character array; it calls
  `CharacterService::calculateCharacter()` for the stats it checks against.
- `lib/Controller/CharactersController.php:242-269` `requirementReport()`
  reads the character as the user and returns `validate(candidate: $character,
  oldCharacter: $character)`.
- `lib/Listener/CharacterRequirementListener.php` enforces the same checks on
  every character write, so applying a build through a normal write is checked
  without new code.
- `character.skills`, `items` and `conditions` are uuid arrays with a
  `setting` relation filter.

## Goals / Non-Goals

**Goals**: try alternatives safely; apply the chosen one through the normal
rules.

**Non-Goals**: cross-world builds, build history.

## Decisions

### D1. The build schema

`characterBuild` (slug `larping_character_build`): `character` (uuid `$ref`
character), `name`, `purpose` (`plan`, `test`, `other-game`), `notes`,
`skills`, `items`, `conditions` (uuid arrays with the same `$ref` and
`x-relation-filter` on the character's world), `ownerUid` (materialised from
the character). Rules: read, create and update by `gamemasters` and by larpers
where `ownerUid = $userId`; delete likewise.

Alternative: a second character record with a "test" flag (what users do
today). Rejected: it appears on the cast, rosters and reports, and its XP
awards are not the real character's.

### D2. The check

`GET /api/builds/{id}/report`: read the build as the user (404 if not
readable), read its character, form a candidate by replacing the character's
`skills`, `items` and `conditions` with the build's, and return
`SkillRequirementService::validate(candidate, oldCharacter: character)` plus
the computed stats of the candidate. XP awards stay the character's, so the
budget is real. Nothing is written.

### D3. Applying a build

`ApplyBuildModal.vue` on the build page, for game masters: shows the skills,
items and conditions that will be added and removed, and on confirm PATCHes
the character's three lists. `CharacterRequirementListener` checks the write;
a refusal shows its errors in the modal.

### D4. Pages

- Fragment: `BuildDetail` page (data widget with name, purpose, notes; related
  lists of skills, items, conditions; the `BuildReport` section component
  showing the check).
- CharacterDetail (in place): object-list "Builds" of `characterBuild`
  filtered on `character = @objectId`, create allowed.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Build objects and access | Declarative, register fragment | Properties, relations, row rules. |
| Checking a build | Imperative, read endpoint over the existing services | hydra ADR-031 exception 2: the requirement and budget check spans skills, effects, abilities and XP awards; the services already exist. |
| Applying | A normal character write | Keeps one enforcement point. |

## Seed data

"Mirela the Wanderer" gets build "Alchemist path" (plan: Herbalism, Potion
brewing) and build "Winter campaign" (other-game: Swordsmanship, Riding). The
first passes; the second fails the budget by 5 XP.

## Risks / Trade-offs

- [Performance] The check runs the stat engine once; the engine loads
  mechanics once per request.

## Migration

None.

## Open Questions

None.
