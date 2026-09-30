---
kind: code
depends_on: []
---

# Proposal: worlds-copy-ruleset

## Summary

A group that starts a second campaign on the same rules, or a new season with
a revised ruleset, today re-enters every ability, skill, item, condition and
effect by hand. This change adds "Copy world" on the world page: a game master
gets a new world with a copy of every rule object of the old one, with all
their links (prerequisites, effects, targeted abilities) pointing at the
copies. Characters, events and XP stay behind.

## Motivation

One worlds row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Worlds is a core
area; the OpenSpec pass of 2026-09-27 decided `build`.

**`wld-copy-ruleset`**, "Start a new campaign from a copy of an existing
ruleset." Larpinq rates it no. Matrix evidence: "grep -rniE
'duplicate|clone|copyFrom' lib src: no hits relating to copying a
world/ruleset". Reached on: "nothing: no copy/duplicate action for settings or
their scoped schemas". Two competitors rate it yes, one partial:

- LarpManager (yes): "larpmanager/utils/core/copy_choices.py:47-50 copy 'Character Sheet' and 'Experience' from another event (orga_copy), larpmanager/fixtures/feature.yaml:1047-1060 template events" (source read at main 36f23d3).
- Kanka (yes): "routes/campaigns/bulks.php:24-25 copy selected entries (abilities, attribute templates, items) to another campaign via app/Http/Controllers/Bulks/CopyController.php; entity templates routes/campaigns/entities.php:404" (source read at tag 3.15).
- pretix (partial): "src/pretix/control/views/main.py:288-343 the event wizard copies an existing event via src/pretix/base/models/event.py:895 copy_data_from (products, quotas, questions, settings); there is no ruleset to copy, only the shop configuration".

## Affected Projects

- [ ] Project: `larpinq`: a copy service and endpoint, a "Copy world" action on the world page.

## Scope

### In Scope

- Copy a world with its abilities, skills, items, conditions and effects (the objects whose `setting` is that world), under a new name.
- Remap every reference between copied objects (`effects`, `abilities`, `requiredSkills`, `requiredStats`, `requiredConditions`, `requiredEffects`) to the copies; references to shared objects (no world) stay as they are.
- Copy the character field definitions and lore pages of the world when those changes have landed (`characters-custom-fields`, `worlds-lore-pages`).
- Game masters only. A summary of what was copied.

### Out of Scope

- Characters, players, events, XP awards and attendance: they belong to a campaign's history, not its rules.
- Copying into an existing world, or copying a selection.
- Unique-item holders: `item.characters` and `condition.characters` are emptied in the copy.

## Approach

A `WorldCopyService` reads the world's rule objects with bounded, filtered
queries, creates the copies in dependency order (abilities, effects, then
skills, items, conditions), and rewrites references through an old-to-new id
map. A `POST /api/worlds/{id}/copy` endpoint guarded for game masters runs it;
the world page gets a header action. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Service/WorldCopyService.php` (new), `lib/Controller/WorldsController.php` (new), one route in `appinfo/routes.php`.
- `src/modals/CopyWorldModal.vue` (new, registry entry) and a header action on SettingDetail in `src/manifest.json` (edited in place).

## Cross-Project Dependencies

None.

## Risks

### Risk 1: A copy fails halfway
**Severity:** Medium. **Mitigation:** the new world is created first with status `archived` and only set to `active` when every object is copied; on failure the service deletes what it created and reports the failing object.

### Risk 2: Large rulesets
**Severity:** Low. **Mitigation:** reads are paged (hydra ADR-058); a ruleset of a few hundred objects copies in one request. The endpoint refuses above 2000 objects with a message, rather than time out.

## Rollback Strategy

Remove the header action and the route. Copied worlds remain ordinary worlds.

## Open Questions

None.
