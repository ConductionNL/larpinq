# Design: characters-factions-and-relationships

## Context

Read at development `2af18d8`.

- No faction, group or relationship exists (`beta-surface-alignment`
  removed marketing copy that claimed factions). Schemas are added through
  `lib/Settings/register.d/` fragments.
- `character.ownerUid` is materialised from the linked player with
  `x-openregister-calculations` (`expression: {"prop": "@ref.player.userUid"}`)
  through `x-openregister-references`. The same mechanism can copy a
  character's owner onto another object that references it.
- Row rules with `match` and `$userId` and property rules come from
  OpenRegister's `row-field-level-security` spec.
- `lib/Listener/CharacterRequirementListener.php` shows the pre-write veto
  pattern and its registration behind `class_exists()` in
  `lib/AppInfo/Application.php`.
- New pages go in a `src/manifest.d/` fragment; CharacterDetail is edited in
  place (`mergeManifestFragments()` in `src/main.js:276-310` only appends).

## Goals / Non-Goals

**Goals**: factions and player groups with members and roles; relationships
between characters; secret things stay secret.

**Non-Goals**: graph view, stat effects.

## Decisions

### D1. Three schemas

- `faction` (slug `larping_faction`): `setting`, `name`, `description`, `kind`
  (`faction`, `group`), `visibility` (`open`, `secret`), `leader` (uuid `$ref`
  character, groups only), `leaderOwnerUid` (materialised from
  `@ref.leader.ownerUid`).
- `factionMember` (slug `larping_faction_member`): `faction`, `character`,
  `role` (`leader`, `member`), `status` (`requested`, `invited`, `active`,
  `left`, `removed`), `ownerUid` (materialised from the character),
  `groupLeaderUid` and `factionVisibility` (materialised from the faction).
  Lifecycle on `status`: `invite` (none to invited), `request` (none to
  requested), `accept` (invited or requested to active), `decline`, `leave`
  (active to left), `remove` (active to removed).
- `relationship` (slug `larping_relationship`): `from` and `to` (uuid `$ref`
  character), `kind` (`family`, `ally`, `rival`, `romance`, `enemy`, `mentor`,
  `other`), `description`, `knownTo` (`gamemasters`, `owners`), `fromOwnerUid`
  and `toOwnerUid` (materialised).

Alternative for membership: a `members[]` array on the faction. Rejected:
invite and request need a state per member, and an array gives a row rule
nothing to match for "my own membership".

### D2. Rules

| Schema | Read | Create / update |
|---|---|---|
| `faction` | gamemasters; larpers where `visibility = open`; larpers where `leaderOwnerUid = $userId` | gamemasters; larpers may create `kind = group` and update where `leaderOwnerUid = $userId` |
| `factionMember` | gamemasters; larpers where `factionVisibility = open`; where `ownerUid = $userId`; where `groupLeaderUid = $userId` | gamemasters; larpers where `ownerUid = $userId` or `groupLeaderUid = $userId` |
| `relationship` | gamemasters; larpers where `knownTo = owners` and (`fromOwnerUid` or `toOwnerUid` is `$userId`) | gamemasters; larpers create from their own character with `knownTo = owners` |

Delete: game masters; a group leader may delete their own group.

### D3. The membership guard

Row rules cannot see the status a write moves to. `FactionMembershipListener`
refuses, for a non game master: a membership created as `active` or `invited`
by someone who is not the group's leader (a player can only `request`); a
membership of a `kind = faction` faction created by a player at all (game
masters place characters in factions); `accept` of a request by anyone but the
leader, and `accept` of an invitation by anyone but the invitee.

### D4. Pages

- Fragment: `Factions` index (name, kind, visibility, world) and
  `FactionDetail` with a data widget and an object-list of `factionMember`
  filtered on the faction, with status and role columns.
- CharacterDetail (in place): an object-list "Factions and groups" of
  `factionMember` filtered on `character = @objectId`, and two object-lists
  "Relationships" (`from = @objectId`) and "Named by others" (`to =
  @objectId`).

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Schemas, lifecycle, materialised owners | Declarative, register fragment | Existing extensions. |
| Who reads and writes what | Declarative, row rules | `match` on materialised uids. |
| Who may move a membership to which status | Imperative, pre-write listener | hydra ADR-031 exception 2: the allowed transition depends on who the caller is relative to two other objects (the member's owner and the group's leader). |
| Faction and connection lists | Declarative, manifest | Object-lists. |

## Seed data

World "Aldmoor": faction "The Crown's Guard" (faction, open) with "Sir
Bertram" active; faction "The Ash Circle" (faction, secret) with "Mirela the
Wanderer" active; group "The Lantern Bearers" (group, open, leader "Tomas")
with "Lady Venn" invited. Relationships: "Mirela the Wanderer" to "Sir
Bertram", rival, known to owners; "Tomas" to "Lady Venn", family (sister),
game masters only.

## Risks / Trade-offs

- [Materialised uids go stale] When a character changes owner, its
  memberships' `ownerUid` recalculates on the next write of the membership;
  the calculation runs on read too if OpenRegister materialises on read.
  Design verification: a test changes the owner and reads the membership.

## Migration

None.

## Open Questions

None.
