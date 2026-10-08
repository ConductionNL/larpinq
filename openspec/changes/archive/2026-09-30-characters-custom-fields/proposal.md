---
kind: code
depends_on: []
---

# Proposal: characters-custom-fields

## Summary

Every LARP group's sheet has fields no other group has: a bloodline, a
guild rank, a patron god, a number of scars. In larpinq a new field means a
developer writes a register fragment. This change lets a game master define
extra character fields per world (text, number, choice or yes-no, visible to
game masters only or also to the player), and shows them on the character
page to fill in.

## Motivation

One characters row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Characters is a
core area; the OpenSpec pass of 2026-09-27 decided `build`.

**`chr-custom-fields`**, "Add custom fields to character sheets for your own
ruleset." Larpinq rates it no. Matrix evidence: "grep -rniE
'custom.?field|dynamic.?propert' lib src openspec: no hits. Schema properties
are only added via developer-authored register.d/*.json fragments
(lib/Settings/register.d/README.md) or the monolith larpinq_register.json,
there is no admin/GM-facing UI to add a field". Reached on: "nothing: schema
changes require a code change (a register fragment), not a UI action". Four
competitors rate it yes:

- LarpManager: "larpmanager/models/form.py:250-398 WritingQuestion with single, multiple, text, computed types, larpmanager/urls/orga.py:556-561 orga_character_form and orga_writing_form" (source read at main 36f23d3).
- MyLARP: "Manager feature: 'Customized character sheet configuration.' https://mylarp.com"
- LARP Portal: "Players 'answer custom campaign questions' as part of the Character Module. https://larportal.com"
- Kanka: "routes/campaigns/entities.php:195-215 properties (attributes) with types, plus attribute templates line 356 and 411" (source read at tag 3.15).

## Affected Projects

- [ ] Project: `larpinq`: a field definition schema, two value properties on `character`, a pre-write check, and a section on the character page.

## Scope

### In Scope

- `characterField` definitions per world: label, key, type (text, number, choice, yes-no), choices, visibility (game masters, or game masters and owner), order.
- Values on the character in `customFields` (readable by the owner and game masters) and `customFieldsPrivate` (game masters only).
- An "Extra fields" section on the character page that shows the definitions of the character's world and lets permitted users fill them in.
- A check on character writes that refuses a value of the wrong type or outside the choices.
- A Character fields page where game masters manage the definitions.

### Out of Scope

- Fields that feed the stat engine (computed fields). Abilities stay the mechanism for numbers the rules use.
- Custom fields on other schemas (items, events). The pattern can be reused later.
- Changing the OpenRegister schema of `character` from the user interface.

## Approach

Definitions are ordinary register objects. Values live in two object-typed
properties on the character, so field level security can keep the private ones
from players. A section component renders the form from the definitions. The
existing character pre-write listener validates values. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/characters-custom-fields.json` (new): schema `characterField` and the two character properties with their property authorization.
- `lib/Service/CustomFieldValidator.php` (new), called from `lib/Listener/CharacterRequirementListener.php`.
- `src/views/CharacterCustomFields.vue` (new, `kind: 'section'`), CharacterDetail in `src/manifest.json` (edited in place), and a `src/manifest.d/characters-custom-fields.json` fragment for the Character fields index and detail pages.

## Cross-Project Dependencies

OpenRegister property authorization (spec `row-field-level-security`).

## Risks

### Risk 1: A definition is deleted while characters hold values
**Severity:** Medium. **Mitigation:** values stay on the character under their key; the section shows orphaned keys to game masters as "no longer defined" with the value, so nothing is lost silently.

### Risk 2: Search and exports do not know the fields
**Severity:** Low. **Mitigation:** OpenRegister exports object-typed properties as JSON; the design lists this as a known limit rather than inventing columns.

## Rollback Strategy

Remove the section and the listener call. Definitions and values stay as data.

## Open Questions

None.
