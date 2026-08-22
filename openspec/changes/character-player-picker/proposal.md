---
kind: config
---

# Proposal: character-player-picker

## Summary
Turns `character.ocName` — today a free-text "type the player's name" string — into a
proper object-relation dropdown that stores the UUID of a `player` object, adds a
Nextcloud-user picker (`player.userUid`) to the `player` schema, declares an
`x-openregister-references` link so a character can resolve its linked player,
makes `character.ownerUid` an auto-derived (`x-openregister-calculations`,
`materialise: true`) field sourced from `@ref.player.userUid` instead of a manually
typed uid, and adds a `switch` widget hint to `character.approved` so its existing
two-value lifecycle enum renders as a toggle. All five behaviours are pure
declarative edits to `lib/Settings/larpinq_register.json` — no PHP or Vue code
changes, and no data migration.

## Motivation
- `ownerUid` ("Nextcloud uid of the player who owns this character... Used for
  per-player notifications") already exists on `character` and is already wired into
  the `character-approved` notification recipient
  (`{kind:"field", field:"ownerUid"}`), but nothing in the app ever sets it — a
  `grep -rn ownerUid lib src` outside the register JSON returns zero hits. The
  notification rule is silently inert.
- `ocName` is documented as "referencing a Player object"
  (`openspec/specs/character-management/spec.md` CHAR-006, PLR-006 in
  `events-players`) but the schema field is still free text
  (`type:"string"`, no `$ref`). A GM types a name; nothing guarantees it matches an
  actual `player` object, and there is no way to programmatically resolve "which
  player plays this character" from the character record.
- Together these two gaps mean the player-ownership notification chain the spec
  describes cannot exist today: there is no structured link from a character to a
  player, and no structured link from a player to the Nextcloud account that should
  receive notifications.
- This change closes both gaps declaratively, per ADR-031 (declarative-over-imperative
  default): a searchable player dropdown on the character form, a Nextcloud-user
  dropdown on the player form, and a calculation that keeps `ownerUid` in sync with
  the linked player's account — zero new Service classes.

## Affected Projects
- [x] Project: `larpinq` — `lib/Settings/larpinq_register.json` schema edits
      (`character.ocName`, `player.userUid`, `character.x-openregister-references`,
      `character.x-openregister-calculations`, `character.approved`)
- [x] Project: `nextcloud-vue` (EXTERNAL, not opsx-tracked here) — three new
      auto-form widget behaviours consumed by this change's fields; see Cross-Project
      Dependencies

## Capabilities
- `character-management` — MODIFIED (CHAR-006 mechanism, new CHAR-011, new
  reference-resolution requirement, `approved` toggle rendering)
- `events-players` — ADDED (new `player.userUid` requirement)
- `notifications` — MODIFIED (ownerUid prerequisite requirement now fulfilled)

## Scope

### In Scope
- Change `character.properties.ocName` to `{type:"string", format:"uuid", $ref:"player"}`
  so it renders as an inline select-or-create dropdown of `player` objects (nc-vue's
  `CnFormDialog` renders `$ref` fields via `CnResourceSelect`, allowCreate) and
  stores the chosen (or newly created) player's UUID.
- Add `player.properties.userUid` (`format:"user"`) so a GM can link a `player`
  object to the Nextcloud user account that plays them.
- Add `character.x-openregister-references.player` (`mode: relatedObject`,
  `field: ocName`) so OpenRegister resolves the linked player object and exposes it
  as `@ref.player.<field>`.
- Redefine `character.properties.ownerUid` as `visible:false, readOnly:true` and add
  `character.x-openregister-calculations.ownerUid`
  (`materialise:true`, `expression:{prop:"@ref.player.userUid"}`) so it is
  auto-derived from the linked player's Nextcloud uid and dropped from the manual
  form.
- Add `"widget":"switch"` to `character.properties.approved`, keeping its existing
  `enum:["no","approved"]`, `default`, and `facetable` untouched, so it renders as a
  toggle instead of a select box. `x-openregister-lifecycle` and the
  `character-approved` notification rule are untouched — this is a form-rendering
  hint only, still fully declarative (ADR-031).
- Seed data for the new `player.userUid` field and a couple of `character` objects
  demonstrating the new `ocName` → `player` → `ownerUid` chain.

### Out of Scope
- Any data migration for legacy `character.ocName` free-text values. There is no
  backfill: a GM re-links each character going forward via the new inline
  select-or-create player dropdown (pick an existing player, or type a new name to
  create one inline). Until a character is re-picked, its dropdown shows the raw
  stored string (degraded, not broken) — see Risk 2.
- Implementing the three nc-vue auto-form widgets themselves — they ship from the
  `nextcloud-vue` repo's `beta` branch as an external, non-opsx-tracked dependency
  (see Cross-Project Dependencies).
- Any change to the `character-approved` notification recipient — it already reads
  `{kind:"field", field:"ownerUid"}` and keeps working unchanged once `ownerUid` is
  populated by the new calculation. The `approved` toggle drives the exact same
  `no`↔`approved` lifecycle transition as the select box it replaces.
- Any change to `character-submitted`'s group-based recipient.

## Approach
Five coordinated edits to the OpenAPI schemas under `.components.schemas` in
`lib/Settings/larpinq_register.json`, all consumed by OpenRegister's declarative
engines (object-relation `$ref` rendering, `x-openregister-references` resolution,
`x-openregister-calculations` materialisation) and by nc-vue's schema-driven
`CnFormDialog`. See `design.md` for the exact JSON shapes, precedent from
`procest_register.json`, and the ADR-031 rationale for staying fully declarative.

## New Dependencies
- `@conduction/nextcloud-vue` bump to consume three new auto-form enhancements once
  they ship on `beta` (currently pinned `^1.0.0-beta.138`): a `format:"user"` NC-user
  picker, a `$ref` inline select-or-create resource picker, and a 2-value-enum +
  `widget:"switch"` toggle. This is a version bump of an existing dependency, not a
  new package. These are fleet-wide auto-form primitives, not larpinq-specific:
  roughly 90 NC-user-shaped fields and 412 reference-shaped properties exist across
  the fleet, so all three widgets are reusable infrastructure that many apps will
  benefit from once they land.

## Impact
- `lib/Settings/larpinq_register.json` — `character` and `player` schema
  definitions under `.components.schemas`.
- Character create/edit form (`CnFormDialog`, schema-driven) — `ocName` renders as an
  inline select-or-create player dropdown instead of a text input; `ownerUid`
  disappears from the form (now `visible:false`); `approved` renders as a toggle
  instead of a select box.
- Player create/edit form (`CnFormDialog`, schema-driven) — gains a Nextcloud-user
  dropdown field.
- No PHP controller, service, or Vue component changes.

## Cross-Project Dependencies
This change has three **external, non-opsx-tracked** cross-project dependencies, all
landing in `@conduction/nextcloud-vue`'s `CnFormDialog` on its `beta` branch and
shipping as a single beta version bump:

1. **`format:"user"` NC-user picker** — `resolveWidget()` (`src/utils/schema.js`)
   maps `format:"user"` → widget `"user"`; `CnFormDialog` renders it as an
   `NcSelectUsers`-backed single-select storing the uid string. Consumed by
   `player.userUid`.
2. **`$ref` object reference → inline select-or-create** — `CnFormDialog` renders
   `$ref` reference fields using the existing `CnResourceSelect` (`allowCreate`)
   instead of a plain read-only `NcSelect`, so a user can pick an existing object OR
   create one inline. Consumed by `character.ocName` (`$ref:"player"`).
3. **2-value enum + `widget:"switch"` → toggle** — `CnFormDialog` renders such a
   property as an `NcCheckboxRadioSwitch`, mapping on/off to the two enum values.
   Consumed by `character.approved`.

That work lives in the `nextcloud-vue` repo on its `beta` branch and ships as a beta
version bump — it is tracked there, not here. Merging this change's JSON edits
before the nc-vue widgets ship is safe: each field simply renders via its prior,
degraded form (plain text input for `userUid`, read-only `NcSelect` for `ocName`,
plain select box for `approved`) until the dependency is bumped.

## Risks

### Risk 1: nc-vue widgets ship after this change merges
**Severity:** Low — **Mitigation:** Each of the three fields degrades gracefully to
its prior rendering (`userUid`: plain text input, still stores a string uid if typed
correctly; `ocName`: read-only `NcSelect`, still stores the picked UUID but without
inline create; `approved`: plain select box, same two values); no data corruption,
no broken form. Cosmetic-only until the nc-vue beta bump lands.

### Risk 2: existing free-text `ocName` values no longer resolve as UUIDs
**Severity:** Medium — **Mitigation:** No data migration. Legacy free-text `ocName`
values are re-linked going forward via the new inline select-or-create player
dropdown — a GM picks an existing player or types a new name to create one inline.
Until re-picked, an old character shows its raw stored string in the dropdown
(degraded, not broken — `$ref` dropdowns display the raw stored string when it
doesn't match a known object's UUID).

### Risk 3: `ownerUid` becomes read-only, losing any previously hand-set values
**Severity:** Low — **Mitigation:** Nothing in the app currently sets `ownerUid`
(zero code references), so there is no existing hand-set data to lose. Going
forward it is always correct-by-construction from the linked player.

## Rollback Strategy
Revert the five JSON edits in `lib/Settings/larpinq_register.json` (single file,
single commit). `ocName` reverts to free text, `ownerUid` reverts to a manually
editable field, `approved` reverts to a plain select box, and the
`x-openregister-references`/`x-openregister-calculations` blocks are removed. No
data migration is required to roll back — UUID-shaped `ocName` values simply become
opaque strings again (still valid free text).

## Open Questions
None — the design, JSON shapes, and field-by-field rendering behaviour were
verified against `procest_register.json` precedent and the current
`larpinq_register.json` contents before this proposal was written.
