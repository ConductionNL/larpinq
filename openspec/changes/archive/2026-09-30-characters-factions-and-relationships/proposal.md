---
kind: code
depends_on: []
---

# Proposal: characters-factions-and-relationships

## Summary

A LARP is its web of loyalties: the Crown's guard, the smugglers' ring, a
sister who is also a rival. Larpinq has no faction, no group and no
relationship between characters. This change adds factions that game masters
run (open or secret), groups that players form themselves with a leader who
invites and removes members, and relationships between two characters (family,
ally, rival and so on), all visible on the character page.

## Motivation

Three characters rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Characters is a
core area; the OpenSpec pass of 2026-09-27 decided `build` for all three.

**`chr-faction-membership`**, "Put characters in factions or groups and see
each faction's members." Larpinq rates it no. Matrix evidence:
"openspec/changes/beta-surface-alignment/proposal.md:13,31,70 and tasks.md:38,
explicit historical record: 'there is no Scene, Faction, or standalone NPC
entity/schema/controller' in this codebase, confirmed by grepping lib/ and src/
for 'faction'". Two competitors rate it yes, one partial:

- LarpManager (yes): "larpmanager/models/writing.py:590-670 Faction with primary, transversal and secret types, larpmanager/urls/orga.py:736 orga_factions, larpmanager/views/user/event.py:1036-1060 faction member pages" (source read at main 36f23d3).
- Kanka (yes): "routes/campaigns/entities.php:103,120,318,334 character organisations and organisation members with role and status (app/Models/OrganisationMember.php:47-55)" (source read at tag 3.15).
- LARP Portal (partial): "Staff 'Character List' report shows 'team affiliation' per character, but no dedicated faction-browsing feature is documented. https://larportal.com/larp-portal-tips.php"

**`chr-relationships`**, "Record relationships between characters, such as
family, allies and rivals." Larpinq rates it no. Matrix evidence: "grep -rniE
'relationship|\bally\b|allies|\brival\b' lib src openspec/specs
openspec/changes: only unrelated 'entity model relationships' ... no
character-to-character relationship field or entity". Three competitors rate
it yes:

- LarpManager: "larpmanager/models/writing.py:1065-1120 Relationship and RelationshipTag, larpmanager/urls/orga.py:546 orga_characters_relationships, larpmanager/urls/orga.py:1181 relationship tags".
- LARP Portal: "'Players can define relationships that their characters have to other PCs and NPC characters, including relatives, friends, and people in their back stories.' https://larportal.com"
- Kanka: "routes/campaigns/entities.php:354 entity relations with attitude, mirror, visibility (app/Models/Relation.php:55-65); connection map line 237; families 125-130".

**`chr-player-groups`**, "Let players form their own groups of characters,
with a group leader who invites and removes members." Larpinq rates it no.
Matrix evidence: "no group or guild schema (lib/Settings/larpinq_register.json:39-960);
factions are not modelled either (see chr-faction-membership)". Two competitors
rate it yes, one partial:

- LarpManager (yes): "larpmanager/models/writing.py:670-770 Guild, GuildRole and GuildMembership, larpmanager/urls/event.py:254-300 guild create, invite, kick and promote, larpmanager/tests/playwright/guild_all_test.py". Its changelog: https://github.com/LoSkana/larpmanager/commit/2258fd5292.
- LARP Portal (yes): "Teams: a player creates and names a team, others ask to join, the team manager approves or declines and can invite players. https://larportal.com/larp-portal-tips-092024.php"
- Kanka (partial): "organisations group characters with a free-text role and status per member ... no group leader who invites or removes members".

All three are connections between characters shown on the character page;
a player group is a faction that players run. One change keeps one model.

## Affected Projects

- [ ] Project: `larpinq`: three schemas (faction, faction member, relationship), their read and write rules, a guard on membership writes, faction pages and connection lists on the character page.

## Scope

### In Scope

- Factions per world with name, description, kind (`faction` run by game masters, `group` run by players), visibility (`open`, `secret`) and a leader character for groups.
- Membership objects with a role (leader, member) and a status (requested, invited, active, left, removed), so joining, inviting and removing leave a record.
- A player creates a group with their own character as leader, invites characters, accepts or declines requests, and removes members; a player asks to join an open group and accepts or declines an invitation.
- Relationships from one character to another with a kind (family, ally, rival, romance, enemy, mentor, other), a description, and who may see it (game masters, or also the owners of both characters).
- Faction pages, and on the character page the character's factions and relationships.

### Out of Scope

- A graph view of relationships. The lists come first.
- Faction effects on stats. Factions carry no effects.

## Approach

Schemas and rules are register declarations; the owner and leader of a
membership are materialised fields so OpenRegister's row rules can match them.
One guard, in a pre-write listener, keeps a player from making themselves an
active member without an invitation. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/characters-factions-and-relationships.json` (new): `faction`, `factionMember`, `relationship`.
- `lib/Listener/FactionMembershipListener.php` (new), registered in `lib/AppInfo/Application.php`.
- `src/manifest.d/characters-factions-and-relationships.json` (new): Factions index and detail pages, menu entry.
- `src/manifest.json`: CharacterDetail gains the Factions and Relationships lists (existing page, edited in place).

## Cross-Project Dependencies

OpenRegister row and field level security and `x-openregister-calculations`
(already used on `character.ownerUid`).

## Risks

### Risk 1: Secret factions leak through membership lists
**Severity:** High. **Mitigation:** a membership of a secret faction is readable only by game masters and by the member's own owner; the faction itself is readable only by them too. Newman tests assert a non-member player gets neither.

### Risk 2: Relationship visibility confuses players
**Severity:** Low. **Mitigation:** the relationship form states who will see it, and game-master-only relationships show a lock icon to game masters.

## Rollback Strategy

Remove the fragments and the listener. The objects stay as data.

## Open Questions

None.
