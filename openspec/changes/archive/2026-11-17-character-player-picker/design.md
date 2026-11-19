# Design: character-player-picker

## Architecture Overview
Pure declarative schema edit inside `lib/Settings/larpinq_register.json`
(`.components.schemas.character`, `.components.schemas.player`). No new PHP
classes, no new Vue components, no new routes. Four OpenRegister/nc-vue declarative
engines already exist (or are landing on nc-vue `beta`) and do all the work:

1. **Object-relation inline select-or-create rendering** — a property shaped
   `{type:"string", format:"uuid", $ref:"<schema-slug>"}` is rendered by nc-vue's
   `CnFormDialog` via `CnResourceSelect` (`allowCreate`) as a searchable dropdown of
   that schema's objects that also lets the user create a new one inline, storing
   the chosen (or newly created) object's UUID. Already used elsewhere in the
   fleet, e.g. `procest_register.json`'s `case.caseType` (`$ref:"caseType"`).
2. **`x-openregister-references`** — resolves a UUID-valued field to the object it
   points at and exposes it as `@ref.<name>.<field>` inside the same object's other
   declarative expressions. Shape confirmed from `procest_register.json`
   (`case.x-openregister-references.caseType`).
3. **`x-openregister-calculations`** — materialises a derived field from a JSON-AST
   expression on save. Shape confirmed from `procest_register.json`
   (`case.x-openregister-calculations.startDate`, `materialise:true` +
   `expression:{...}`).
4. **2-value enum + `widget:"switch"` → toggle** — a property with a 2-item `enum`
   and `"widget":"switch"` is rendered by `CnFormDialog` as an
   `NcCheckboxRadioSwitch`, mapping the switch's on/off state to the two enum
   values. Purely a form-rendering hint — the underlying value stays a plain
   `enum` string, so OpenRegister's schema validation, `x-openregister-lifecycle`,
   and any notification rules keyed off the field value are untouched.

`character.ocName` becomes the `$ref` field; `character.x-openregister-references`
resolves it to the linked `player` object; `character.x-openregister-calculations`
reads `@ref.player.userUid` to materialise `character.ownerUid`. `player.userUid`
is a new plain field that renders via the (external, in-flight) nc-vue
`format:"user"` widget. `character.approved` keeps its existing
`enum:["no","approved"]` and gains `"widget":"switch"` to render as a toggle via
the (external, in-flight) nc-vue switch widget.

## Goals / Non-Goals

**Goals**
- Make `character.ocName` a real, structured link to a `player` object.
- Make `character.ownerUid` correct-by-construction, sourced from the linked
  player's Nextcloud account.
- Give a `player` object a structured Nextcloud-user link (`userUid`) so
  `ownerUid` has something real to derive from.
- Make `character.approved` render as a toggle instead of a select box, without
  changing its underlying two-value lifecycle.
- Do all of this without adding a single PHP or Vue file, and without any data
  migration.

**Non-Goals**
- Migrating existing free-text `ocName` values — there is no backfill; legacy
  values are re-linked going forward via the new inline select-or-create dropdown
  (see Decision 4 below).
- Building the three nc-vue auto-form widgets — external dependency, see proposal.
- Changing notification dispatch logic — `character-approved` already reads
  `ownerUid` via `{kind:"field", field:"ownerUid"}` and needs no edit.
- Changing `character.approved`'s `x-openregister-lifecycle` transitions or the
  `character-approved` notification trigger — only the form-rendering hint changes.
- Replacing or duplicating the player↔contacts leaf (ADR-001,
  `player-to-contacts-leaf`) — `userUid` is a Nextcloud-login link, complementary
  to the contacts leaf's real-world person data (name, email, address), not a
  replacement for it.

## Decisions

### Decision 1: `format:"uuid"` + `$ref` on `ocName`, not a new field
**Chosen:** Repurpose `character.ocName` in place rather than add a new
`playerId` field and deprecate `ocName`.
**Rationale:** `ocName` is already documented across three specs
(`character-management` CHAR-006, `events-players` PLR-006/007/010) as "the field
that links a character to a player" — the *intent* was always structural, only the
*type* was wrong (free text instead of a UUID reference). Reusing the field name
keeps every existing reference (notification field-recipients, spec prose, the
`x-openregister-references.player.field` pointer) correct without a rename.
**Alternative considered:** Add `character.playerId` (new UUID field) and leave
`ocName` as a legacy display-only string. Rejected — doubles the surface area for
zero benefit; the inline select-or-create dropdown lets a GM re-link `ocName`
directly (pick or create a player) without needing a second field.

### Decision 2: `ownerUid` becomes `visible:false, readOnly:true` + calculated
**Chosen:** Drop `ownerUid` from the manual form entirely and materialise it from
`@ref.player.userUid`.
**Rationale:** `ownerUid` exists solely to feed the `character-approved`
notification's `{kind:"field", field:"ownerUid"}` recipient. A manually-typed uid
field is a foot-gun (GM must remember to keep it in sync with the actual player);
a calculated field can never drift from the linked player's account.
**Alternative considered:** Leave `ownerUid` manually editable and add a separate
read-only "linked account" display field. Rejected — two fields for one concept,
and the manual field would still be able to drift from the calculated truth.

### Decision 3: `player.userUid` uses `format:"user"`, a widget that does not exist yet
**Chosen:** Ship the schema field now; consume the nc-vue widget once its beta
lands, degrading to a plain text box until then (see proposal Risk 1).
**Rationale:** Sequencing schema-first, widget-second avoids blocking this whole
change on an external repo's release cycle, and the degradation is safe (a GM can
still type a uid correctly; the schema, reference, and calculation all work
regardless of which widget renders the input).
**Alternative considered:** Block this change until the nc-vue widget ships.
Rejected — needlessly couples an internal JSON edit to an external repo's release
timeline; the config head is genuinely independent work.

### Decision 4: `character.approved` gains `"widget":"switch"`, keeping its enum
**Chosen:** Add `"widget":"switch"` to the existing `approved` property in place,
without touching its `enum:["no","approved"]`, `default`, `facetable`, or the
schema-level `x-openregister-lifecycle` block that drives the `no`↔`approved`
transition.
**Rationale:** This is a pure form-rendering hint (ADR-031: still declarative, no
Service class). The lifecycle engine and the `character-approved` notification
both key off the stored enum value, not off how the value is edited — a toggle and
a select box produce identical stored data, so nothing downstream needs to change.
**Alternative considered:** Convert `approved` to a `type:"boolean"` field.
Rejected — would require rewriting `x-openregister-lifecycle`'s transition
conditions and the notification rule's trigger value from `"approved"` to `true`,
touching code paths this change deliberately leaves untouched; the `widget:"switch"`
hint gets the same UX with zero blast radius.

### ADR-031: declarative-vs-imperative
All five behaviours in this change are DECLARATIVE — no new Service class:
- The player reference: `character.x-openregister-references` (OR's reference
  resolver).
- The derived owner uid: `character.x-openregister-calculations` with
  `materialise:true` (OR's calculation engine).
- The object-relation inline select-or-create dropdown: `format:"uuid"` + `$ref`
  (nc-vue's schema-driven `CnFormDialog` via `CnResourceSelect`, OR's `$ref`
  validation).
- The approved toggle: `"widget":"switch"` on the existing two-value `enum`
  (nc-vue's schema-driven `CnFormDialog` via `NcCheckboxRadioSwitch`).
- The existing notification: `character.x-openregister-notifications`, unchanged.

This is the ADR-031 default path — no code was written or needed to be written for
this change.

## Risks / Trade-offs
- [Existing `ocName` free-text values become unresolved UUID references] →
  Mitigation: no backfill; a GM re-links each character going forward via the new
  inline select-or-create dropdown (pick an existing player or type a new name to
  create one inline). Until re-picked, legacy characters show their raw stored
  string in the dropdown rather than crashing.
- [Three nc-vue widgets not yet available in the pinned nc-vue version] →
  Mitigation: each field renders via its prior, degraded form until the dependency
  bumps (plain text input for `userUid`, read-only `NcSelect` for `ocName`, plain
  select box for `approved`); functionally inert but not broken.
- [A GM could still type an arbitrary string into `userUid` before the widget
  ships, producing a uid that does not correspond to a real Nextcloud account] →
  Mitigation: `ownerUid`'s calculation simply materialises whatever string is
  present; a bad uid means notifications silently fail to resolve a recipient
  (same class of no-op as today, not worse). No security exposure — it cannot be
  used to impersonate, only to fail to notify.

## Migration Plan
Not applicable — no data migration in this change. Legacy `character.ocName`
free-text values are re-linked going forward via the new inline select-or-create
player dropdown, not migrated in bulk. Deploy is a single JSON file edit picked up
by the app's existing `larpinq_register.json` auto-import
(`register-config-json` capability) on the next `ConfigurationService.importFromApp()`
run (app enable/upgrade or manual re-import). Rollback: revert the JSON edit (see
proposal's Rollback Strategy).

## Open Questions
None.

## Nextcloud Integration
- Controllers: none touched.
- Services: none touched — this is a JSON-only change consumed by OpenRegister's
  existing declarative engines (reference resolution, calculation materialisation)
  and nc-vue's schema-driven form renderer.
- Mappers/Entities: none touched.
- Events/Hooks: none touched. The existing `character-approved`
  `x-openregister-notifications` rule keeps firing unchanged; it now has a
  populated `ownerUid` to resolve instead of an always-empty one.

## Security Considerations
No security impact. `ocName`/`ownerUid` were already readable/writable by anyone
who can edit a character; the change narrows `ownerUid` from manually-writable to
read-only-derived, which is strictly more restrictive. The `$ref` dropdown does not
expose any player data beyond what the existing player list view already exposes
to the same audience (GM-tier, per ADR-002).

## NL Design System
No new components. The `ocName` dropdown, `player.userUid` field, and `approved`
toggle all render through nc-vue's existing schema-driven `CnFormDialog`, which
already sources all styling from Nextcloud CSS variables (ADR-003) — no hardcoded
colors, no bespoke markup added by this change.

## File Structure
```
lib/
  Settings/
    larpinq_register.json   # only file touched
```

## Seed Data

### Schema: `player`
| Field | Object 1 | Object 2 | Object 3 |
|-------|----------|----------|----------|
| slug | player-alice | player-bob | player-carol |
| name | Alice de Vries | Bob Jansen | Carol Bakker |
| description | Plays melee-focused characters | Plays support/healer characters | Plays scout/ranger characters |
| userUid | alice | bob | carol |

**Related items per object:**
- Files: none
- Notes: none
- Tasks: none
- Contacts: each player object may separately link to an OR contact via the
  existing `player-to-contacts-leaf` integration (unrelated to `userUid` — see
  Non-Goals). Not seeded here.

### Schema: `character` (fields touched by this change only)
| Field | Object 1 | Object 2 |
|-------|----------|----------|
| slug | character-lancelot | character-mira |
| name | Sir Lancelot | Mira Nightshade |
| ocName | `<player-alice UUID>` | `<player-bob UUID>` |
| ownerUid | `alice` (materialised from `@ref.player.userUid`) | `bob` (materialised) |

@self envelope for each: `{"register": "larpinq", "schema": "character"}` and
`{"register": "larpinq", "schema": "player"}` respectively — matching the
existing register/schema slugs already declared in `larpinq_register.json`.
Use the nil UUID `00000000-0000-0000-0000-000000000000` as a placeholder in any
example JSON that needs a syntactically valid but non-real UUID.

## Trade-offs
See Decisions above for the per-decision alternatives considered. At the design
level, the overall trade-off is: staying 100% declarative (ADR-031 default) costs
one graceful-degradation window (Risk 1/2 above) in exchange for zero new code,
zero new test surface beyond schema validation, and a rollback that is a single
JSON diff revert.
