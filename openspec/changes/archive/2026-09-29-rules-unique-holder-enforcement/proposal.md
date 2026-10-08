---
kind: code
depends_on: []
---

# Proposal: rules-unique-holder-enforcement

## Summary

A unique item and a unique condition each belong to one character at a
time. Larpinq stores the `unique` flag on both schemas and shows it on the
item page, but nothing reads it: a game master can hand the one Crown of
Aldmoor to three characters and the write goes through. This change adds a
server-side check on every write path that refuses a second holder and names
the character who holds it now.

## Motivation

Two rules rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Both were marked
`specified` with no change directory behind them. The OpenSpec pass of
2026-09-27 decided `build`: no open or archived change enforces the flag, so
this pass writes the change rather than leave the claim.

**`rul-unique-item-not-enforced`**, "Keep a unique item to a single holder,
so it cannot be assigned to two characters at once." Larpinq rates it no.
Matrix evidence: "lib/Settings/larpinq_register.json:589-594 (item.unique:
'Whether this is a unique artifact (only one instance can exist)', default
true); grep -rn "'unique'" lib --include=*.php (excluding the register JSON
files): only unrelated hits in lib/Portal/PortalContributionProvider.php:297,313,
no listener or service reads item.unique to block a second holder". Reached
on: "nothing: the flag is declared and shown on the ItemDetail data widget,
but no write-time check exists". The row note: "item.characters is a plain
many-to-many relation with no server-side guard, so a 'unique' item can
currently be added to any number of characters."

**`rul-unique-condition-not-enforced`**, "Prevent a unique condition from
being applied to more than one character at a time." Larpinq rates it no.
Matrix evidence: "lib/Settings/larpinq_register.json:661-666 (condition.unique:
'Whether only one character can have this condition at a time', default
false); same grep as rul-unique-item-not-enforced shows no code reads
condition.unique".

No competitor is rated yes on either row. LarpManager: "larpmanager/models/inventory.py:54
Inventory.owners is many-to-many with characters and has no uniqueness rule".
Kanka: "inventories allow the same item on any number of entities
(app/Models/Inventory.php:39-51, no unique constraint)". These are own-code
rows: the product declares a rule and does not keep it.

## Affected Projects

- [ ] Project: `larpinq`: a pre-write listener on character, item and condition writes, and the error it returns to the character and item pages.

## Scope

### In Scope

- Refuse a character write that adds a unique item or a unique condition another character already holds.
- Refuse an item or condition write that lists more than one character while `unique` is true.
- Refuse switching `unique` on for an item or condition that two or more characters hold today, naming them.
- Count holders on both sides of the relation: `character.items[]` / `character.conditions[]` and `item.characters[]` / `condition.characters[]`.
- A message on the character and item pages that names the current holder.

### Out of Scope

- Keeping `character.items[]` and `item.characters[]` in sync with each other. The check reads both; syncing them is a separate data-model change.
- A transfer action that moves an item from one character to another in one step. Today a game master removes it from one sheet and adds it to the other; that stays.
- Quantities or copies of an item.

## Approach

Follow the pre-write veto pattern `CharacterRequirementListener` already
uses: a new `UniqueHolderListener` on OpenRegister's `ObjectCreatingEvent`
and `ObjectUpdatingEvent`, scoped to the character, item and condition
schemas, looks up the other holders with a filtered, bounded query and
vetoes the write with a field error. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Listener/UniqueHolderListener.php` (new) and its registration in `lib/AppInfo/Application.php::register()`.
- `lib/Service/UniqueHolderService.php` (new): the holder lookup, shared by the three schemas.
- `lib/Service/RegisterObjectFetcher.php`: used with a filter and a limit, no signature change.
- No register or manifest change: the `unique` flags already exist.

## Cross-Project Dependencies

OpenRegister's vetoable pre-write events, which larpinq already consumes
(`CharacterRequirementListener`). Nothing new.

## Risks

### Risk 1: Existing data already breaks the rule
**Severity:** Medium. **Mitigation:** the check only fires when a write adds a holder or switches `unique` on. An unrelated edit to a character that already shares a unique item with another is not blocked. An occ command, `larpinq:unique-holders:check`, lists the conflicts that exist today so a game master can clean them up.

### Risk 2: The holder lookup scans the character register
**Severity:** Low. **Mitigation:** the lookup filters on the item or condition id and passes a limit of 2 (one other holder is enough to refuse), per hydra ADR-058. If the OpenRegister filter cannot match inside an array, design.md names the bounded fallback.

## Rollback Strategy

Remove the listener registration from `Application::register()`. Writes go
back to unchecked; no data changes.

## Open Questions

None.
