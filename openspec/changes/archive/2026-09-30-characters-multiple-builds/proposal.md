---
kind: code
depends_on: []
---

# Proposal: characters-multiple-builds

## Summary

Players plan: "if I take Herbalism now, can I still reach Master Alchemist
next season?" In larpinq the only way to try is to change the real sheet or
copy the whole character. This change adds builds: named alternative skill
sets for a character that a player or game master can check against the rules
and the XP budget without touching the sheet, and that a game master can apply
to the sheet when the choice is made.

## Motivation

One characters row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Characters is a
core area, and the row has a feature request plus a competitor yes; the
OpenSpec pass of 2026-09-27 decided `build`.

**`chr-multiple-builds`**, "Keep more than one build or sheet for the same
character, such as a test build or a build for another game." Larpinq rates it
no. Matrix evidence: "lib/Settings/larpinq_register.json:39-230 (a character
holds one skills, items and conditions list); there is no build, skill-set or
version object, so a second build means a second character record". Reached
on: "nothing: see evidence".

- Demand: a Kanka feature request, https://app.kanka.io/roadmap/51.
- LARP Portal (yes): "'Build more than one version of same character with same character points' and 'Build test characters'; each build is a skill set. https://larportal.com/features.php , https://larportal.com/larp-portal-tips-042025.php"
- LarpManager and Kanka are rated no: one sheet per character, and copying makes a separate character.

## Affected Projects

- [ ] Project: `larpinq`: a build schema, a check endpoint that runs the existing rules on a build, an apply action, and a Builds list on the character page.

## Scope

### In Scope

- `characterBuild` objects: a character, a name, a purpose (plan, test, other game), and skills, items and conditions lists.
- A check of a build against the skill requirements and the XP budget, with the same report shape as the character's requirement report, and without writing anything.
- Applying a build to the character: a game master replaces the character's skills with the build's in one write that passes the normal requirement check.
- Owner and game master access, the same as the character.

### Out of Scope

- Builds for another installation or another campaign's ruleset: a build uses the rules of the character's world.
- Keeping history of applied builds beyond the character's audit trail.

## Approach

A register fragment adds the schema. A read-only endpoint composes a
candidate character from the character and the build and runs
`SkillRequirementService::validate()` and the stat engine on it. Applying is a
normal character write from the page. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/characters-multiple-builds.json` (new).
- `appinfo/routes.php` and `lib/Controller/CharactersController.php`: `GET /api/builds/{id}/report`.
- `src/manifest.d/characters-multiple-builds.json` (new): BuildDetail page. `src/manifest.json`: CharacterDetail gains the Builds list (edited in place).
- `src/views/BuildReport.vue` (new, `kind: 'section'`) and `src/modals/ApplyBuildModal.vue` (new).

## Cross-Project Dependencies

None beyond OpenRegister, already used.

## Risks

### Risk 1: A build drifts from the rules
**Severity:** Low. **Mitigation:** the check runs on request against the current rules; the build page shows the date of the last check and re-runs it when opened.

### Risk 2: Applying a build overwrites the sheet
**Severity:** Medium. **Mitigation:** only game masters apply; the modal lists what will be added and removed; the character's audit trail keeps the previous lists and OpenRegister revert can restore them.

## Rollback Strategy

Remove the fragment, route and components. Builds stay as data.

## Open Questions

None.
