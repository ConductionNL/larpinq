---
kind: code
depends_on: []
---

# Proposal: characters-stat-sheet-panel

## Summary

Larpinq recalculates every character's abilities from their skills, items,
conditions, events and XP awards, and records which source moved each number.
None of it reaches a page: a game master cannot see a character's strength, why
it is 14, or how much XP the character has left to spend. This change adds a
Stats tab to the character page that shows each ability with its base, every
modifier with its source, the final value, and an XP line with earned, spent
and left.

## Motivation

Two rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for both.

**`chr-stat-breakdown`**, "See which skill, item or condition caused each
change to a stat." Characters is a core area. Larpinq rates it no, although
the matrix carried built.state built. Matrix evidence:
"lib/Service/CharacterService.php:405-458 (applyXpAwards and applyEntityEffects
append a per-source 'audit' array onto each ability score);
lib/Service/SkillRequirementService.php:96-99 (only reads calculated['stats']
for internal context) and its validate() return shape at lines 77-82
(valid/requirements/budget/dependents, no 'stats' or audit is serialised out);
lib/Controller/CharactersController.php:266-268 (returns $report, i.e. exactly
that shape)". Reached on: "nothing: the per-source audit trail is computed
server-side but never leaves the server in any API response a page consumes".
The row note: "openspec/specs/larping-skill-widget/spec.md:186 independently
marks a 'character stat breakdown widget' as not yet implemented". No
competitor rates it yes; LarpManager and Kanka are rated no, so this is a
place where larpinq's engine can lead. The archived change
`2026-03-21-larping-skill-widget` specified the panel and it never shipped.

**`prg-xp-balance`**, "See how much XP a character has earned in total."
Larpinq rates it partial, built. Matrix evidence: "src/manifest.json:620-640
(CharacterDetail char-stats-xp-awarded stats-block, sum of xpAward.amount
filtered by character)". The row note: "'earned' (total awarded) IS shown.
'Spent' and 'has left' are NOT: SkillRequirementService::evaluateBudget
(lib/Service/SkillRequirementService.php:203-229) computes exactly this number
for every write, but src/views/SkillTree.vue never renders report.budget.value
or .shortfall ... A GM cannot see a character's remaining XP anywhere in the
app." Two competitors rate it yes:

- LarpManager: "larpmanager/utils/services/experience.py:463-519 total, used and available per system, shown on the sheet (larpmanager/templates/elements/sheet/experience.html:3-23)" (source read at main 36f23d3).
- LARP Portal: "'Personal point viewing' is explicitly listed as a PC capability. https://larportal.com/how-it-works.php"

Both rows read the same engine output on the same page, so they are one
change. It also lifts row `chr-live-stats` (deferred in this pass as partial,
built, no demand): the panel shows the computed values that row finds missing.

## Affected Projects

- [ ] Project: `larpinq`: a read endpoint for a character's computed stats, and a Stats tab on the character detail page.

## Scope

### In Scope

- `GET /api/characters/{id}/stats`: every ability with base, ordered modifiers (source type, source name, change, old and new value) and final value, plus an XP summary (earned, spent, left).
- A Stats tab in the CharacterDetail sidebar rendering it, negative modifiers marked, and "No modifiers" for an untouched ability.
- The same read rules as the character: whoever can read the character's build can read its stats.

### Out of Scope

- Storing computed stats on the character. They stay computed on read.
- Comparing characters, the effect chain diagram and the other widgets of the larping-skill-widget spec.

## Approach

A thin controller method next to `requirementReport` calls
`CharacterService::calculateCharacter()` and shapes its `stats[].audit` into
the response; the XP summary reads the XP ability's audit (awards are earned,
negative effects are spent). A `CharacterStatSheet.vue` section component,
registered like `EventRoster`, renders it as a sidebar tab. Details in
design.md.

## New Dependencies

None.

## Impact

- `appinfo/routes.php`: one route. `lib/Controller/CharactersController.php`: `stats()`.
- `lib/Service/CharacterStatsPresenter.php` (new): shapes engine output, resolves source names.
- `src/views/CharacterStatSheet.vue` (new), registered in `src/registry.js` as `kind: 'section'`.
- `src/manifest.json`: CharacterDetail sidebar gains the Stats tab (an existing page, edited in place).
- `openspec/specs/larping-skill-widget/spec.md`: the stat breakdown and audit trail requirements point at this change.

## Cross-Project Dependencies

None.

## Risks

### Risk 1: A player sees modifiers from secrets
**Severity:** Medium. **Mitigation:** the endpoint reads the character as the calling user. With `characters-player-visibility`, a player reads the build of their own character only, and every modifier in the panel comes from that build. A hidden condition a game master does not want revealed is a game design choice the panel makes visible; design.md D4 says how a game master keeps one out.

### Risk 2: Calculation cost on every open
**Severity:** Low. **Mitigation:** `calculateCharacter()` already loads the mechanics once per request (`loadAllEntities`, #217); the tab fetches only when opened.

## Rollback Strategy

Remove the tab from the manifest; the endpoint can stay unused.

## Open Questions

None.
