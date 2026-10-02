---
kind: code
depends_on: [worlds-lore-pages]
---

# Proposal: rules-rulebook-and-spell-lists

## Summary

Players learn the rules from the rulebook, and in many LARPs magic works apart
from ordinary skills: spells come from their own list, cost mana instead of
experience, and read differently. Larpinq keeps every rule as an object, but
players cannot read them as a book, and a spell is just another skill. This
change adds skill lists (general skills, spells, powers) with their own cost
pool, and a Rulebook page per world that puts the rules chapters (lore pages of
category rules) and a generated reference of abilities, skill lists, items and
conditions together in one readable, printable page for players, in larpinq
and in the portal.

## Motivation

Two rules rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for both: each has two competitors rated yes.

**`rul-publish-rulebook`**, "Publish the rulebook to players as readable
pages." Larpinq rates it no. Matrix evidence: "grep -rniE 'rulebook' lib src:
no hits beyond the seed row; the only 'publish' hits are generic OpenRegister
config-bundle install/export (src/manifest.json:337) and unrelated schema
'published' date metadata (lib/Settings/larpinq_register.json:21), neither of
which renders a readable rulebook page to players".

- LARP Portal (yes): "'Character Specific Rulebook report' generates a personalised, printable mini-rulebook of a character's own skills; general rule pages are also described ('PCs use it to learn about game rules'). https://larportal.com/larp-portal-tips.php"
- Kanka (yes): "journals/notes with visibility and public campaigns (routes/campaigns/entities.php:327,335; routes/campaigns/campaign.php:215-216)" (source read at tag 3.15).
- LarpManager (partial): "Handouts with public links and event texts, ability descriptions shown on the purchase page ...; no browsable rulebook page type".

**`rul-spell-lists`**, "Keep a distinct spell or power list, separate from the
general skill list, with its own costs and descriptions." Larpinq rates it no.
Matrix evidence: "grep -rniE 'spell' lib src: no gameplay hits ...; no separate
spell/power schema exists, a spell would have to be modelled as an ordinary
'skill' object with no distinguishing type".

- LarpManager (yes): "larpmanager/models/experience.py:34-52 SystemExp: separate experience systems each with its own abilities, awards and XP budget (larpmanager/utils/services/experience.py:463-519 per-system totals), ability types and descriptions" (source read at main 36f23d3).
- Kanka (yes): "abilities module with types, charges and descriptions, separate from properties (app/Models/Ability.php:40-45; routes/campaigns/entities.php:279 use a charge)".

The rulebook is where the lists are read, so the two ship together.

## Affected Projects

- [ ] Project: `larpinq`: a skill list schema and a cost pool per list, the budget check per pool, a Rulebook page, and a rulebook collection in the portal.

## Scope

### In Scope

- `skillList` per world: name, kind (skills, spells, powers), the ability its costs are paid from (default the XP ability), description, order. `skill.list` places a skill in a list; skills without a list are general skills.
- The skill requirement budget checked per cost ability: a spell costing 3 mana draws on the character's mana, not XP.
- A Rulebook page per world: chapters from lore pages of category rules that players may read, in order, followed by a generated reference: abilities, each skill list with its skills (cost, cost pool, prerequisites, effect text), items and conditions.
- A print layout for the Rulebook page.
- The rulebook chapters and the skill lists as collections in the portal for the `player` audience.

### Out of Scope

- A personalised rulebook of one character's skills (the Stats tab and the character sheet PDF cover a character).
- Charges or uses per event of a spell.

## Approach

A register fragment for `skillList` and `skill.list`; `SkillRequirementService`
evaluates one budget per cost ability; a `Rulebook.vue` page component reads
lore pages and rule objects through the object store; the portal contribution
gains two catalogue collections. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/rules-rulebook-and-spell-lists.json` (new).
- `lib/Service/SkillRequirementService.php`: budget per cost ability.
- `src/views/Rulebook.vue` (new, registry kind `page`), `src/manifest.d/rules-rulebook-and-spell-lists.json` (new): Rulebook page, Skill lists pages, menu entries.
- `lib/Portal/PortalContributionProvider.php`: `rulebookChapters` and `skillLists` collections.

## Cross-Project Dependencies

None beyond `worlds-lore-pages` in this repository.

## Risks

### Risk 1: A rulebook shows a secret mechanic
**Severity:** Medium. **Mitigation:** the reference lists only what players can read; rule objects get a `hiddenFromRulebook` flag for secret skills and items, which the page and the portal collections filter out.

### Risk 2: Existing builds change budget
**Severity:** Low. **Mitigation:** existing skills have no list and keep paying from XP; only skills placed in a list with another cost ability move pool.

## Rollback Strategy

Remove the page and the fragment; the budget falls back to XP only.

## Open Questions

None.
