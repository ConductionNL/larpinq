# Design: characters-stat-sheet-panel

## Context

Read at development `2af18d8`.

- `lib/Service/CharacterService.php:353-403` `calculateCharacter()` starts
  from `initializeAbilityScores()` (every ability at its `base`), then applies
  effects from skills, items, conditions and events (`applyEntityEffects()`),
  then XP awards (`applyXpAwards()`, `:420-458`). Each step appends an entry
  to `stats[<abilityId>]['audit']`: `{type, effect|award, old, new}`. The result
  is returned as `$character['stats']` and persisted nowhere.
- `lib/Service/SkillRequirementService.php:203-229` `evaluateBudget()` reads
  the XP ability's final value: negative means a shortfall. The XP ability is
  resolved by config with a name fallback (`resolveXpAbility()`).
- `lib/Controller/CharactersController.php:242-269` `requirementReport()` is
  the pattern: `#[NoAdminRequired]`, reads the character through
  `RegisterObjectFetcher::getObject()` so OpenRegister decides read access,
  answers 404 for an unreadable id.
- `src/manifest.json` CharacterDetail: an `XP awarded` stats-block sums
  `xpAward.amount`; the sidebar has only the History tab. EventDetail shows the
  precedent for a component tab: `{"id": "checkin", "component":
  "EventRoster"}` resolved through `src/registry.js` (`kind: 'section'`).
- `openspec/specs/larping-skill-widget/spec.md:182-233` specifies the
  breakdown panel and the per-ability audit trail, both `@e2e exclude` as not
  implemented.

## Goals / Non-Goals

**Goals**: show the engine's result and its reasons; show XP earned, spent and
left.

**Non-Goals**: persisting stats, the other skill-widget visuals.

## Decisions

### D1. A read endpoint, not a stored field

`GET /api/characters/{id}/stats` computes on request. Alternative: materialise
`stats` on the character with `x-openregister-calculations`; rejected because
the engine walks five schemas and applies non-cumulative rules that the
declarative calculation cannot express (hydra ADR-031 exception 2), and a
stored copy goes stale whenever a skill's effect changes.

### D2. Response shape

```json
{
  "abilities": [
    {"id": "<uuid>", "name": "Strength", "base": 10, "final": 14,
     "modifiers": [
       {"source": "skill", "sourceId": "<uuid>", "sourceName": "Swordsmanship",
        "effectName": "Blade training", "change": 3, "old": 10, "new": 13},
       {"source": "item", "sourceName": "Iron shield", "change": 1, "old": 13, "new": 14}
     ]}
  ],
  "xp": {"ability": "<uuid>", "earned": 40, "spent": 25, "left": 15}
}
```

`CharacterStatsPresenter` maps each audit entry to its source. The engine
records the effect; the presenter records which skill, item, condition or
event carried it by passing the stage name into the audit entry (a one-line
addition in `applyEntityEffects()`: `'source' => $property`, `'sourceId'`).
XP: `earned` is the XP ability's base plus the sum of `xpAward` entries and
positive effects; `spent` is the sum of negative effects on the XP ability;
`left` is the final value. The numbers match `evaluateBudget()` by
construction, and a PHPUnit test pins that.

### D3. The tab

A `CharacterStatSheet.vue` section component, registered as `kind: 'section'`
like `EventRoster` (the registry keeps custom widgets out, per the
custom-widget ratchet). The CharacterDetail sidebar gains `{"id": "stats",
"label": "Stats", "icon": "ChartBar", "component": "CharacterStatSheet"}`. It
lists abilities in a table (ability, base, modifiers, final), expands a row to
the ordered audit, marks negative changes with `var(--color-error)`, and
shows the XP line on top. Keyboard: rows expand with Enter and Space (hydra
ADR-059).

### D4. Access

The endpoint reads the character with `RegisterObjectFetcher::getObject()` as
the calling user, like `requirementReport()`, and answers 404 when it cannot.
With `characters-player-visibility`, only game masters and the owner can read a
character's skills, items and conditions, so only they get stats. A game master
who wants a condition's effect to stay a surprise keeps the condition off the
sheet until it takes effect; the panel does not hide sources the owner can read
anyway.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Computed stats with their sources | Imperative, existing engine plus a read endpoint | hydra ADR-031 exception 2: the calculation spans skills, items, conditions, events, effects, abilities and XP awards with non-cumulative rules. |
| XP earned, spent and left | Derived in the presenter from the same audit | One source of truth with the budget check. |
| The Stats tab | Manifest sidebar tab over a registered section component | Existing precedent (EventRoster). |

## Seed data

No schema change. The demo character "Mirela the Wanderer" gets the skill
"Swordsmanship" (+3 strength, costs 10 XP) and the item "Iron shield" (+1
strength) and two XP awards of 20, so the panel shows strength 14 and XP 40
earned, 10 spent, 30 left.

## Risks / Trade-offs

- [Audit entries without a source] Entries written before the one-line
  addition carry no `source`; the presenter labels them by effect name only.
- [Rounding] Values are integers in the engine; the panel shows them as they
  are.

## Migration

None.

## Open Questions

None.
