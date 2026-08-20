---
kind: config
---

# Proposal: manifest-namespaced-schema-slugs

## Summary
Repoints every `src/manifest.json` reference from the bare schema slugs `"event"`
and `"item"` to their real, namespaced OpenRegister slugs `"larping_event"` and
`"larping_item"`. `lib/Settings/larpingapp_register.json` already declares these
two schemas under the namespaced slugs (to avoid a global OpenRegister slug
collision — see Motivation), but `src/manifest.json` was never updated to match,
so OpenRegister resolves `"event"`/`"item"` to a *different app's* schema of the
same bare name. This is a pure manifest JSON edit — no schema, PHP, or Vue code
changes.

## Motivation
- `lib/Settings/larpingapp_register.json` defines the `event` and `item` schemas
  with real slugs `larping_event` and `larping_item` respectively (confirmed via
  `.components.schemas.event`/`.components.schemas.item`'s
  `x-openregister-schema-slug`), while every other larpingapp schema
  (`character`, `player`, `ability`, `skill`, `condition`, `effect`, `setting`,
  `xpAward`) keeps its bare key as its real slug.
- OpenRegister resolves a manifest page/widget's `"schema"` reference by a
  **global** `lower(slug)` lookup across all installed apps' registers (see
  `reference_or-cross-app-schema-slug-collision.md`) — slugs are not namespaced
  per app. `event` and `item` are common enough words that other apps already
  claim them: `event` collides with openconnector's CloudEvents `event` schema
  (fields: Source, Type, Spec Version, datacontenttype, dataschema…), and `item`
  collides with another installed app's `item` schema.
- `src/manifest.json` still references the bare slugs `"event"` and `"item"` in
  11 places (7 for `event`, 4 for `item`) — the Events and Items index pages,
  detail pages, dashboard KPI/list widgets, and one onboarding `advanceOn` rule.
  Because OpenRegister resolves by bare slug, every one of these currently
  resolves to the *wrong* schema, so the Events and Items create/list/detail
  forms render fields from an unrelated app's schema instead of larpingapp's own
  `startDate`/`endDate`/`location` (event) or item fields.
- This is a plain configuration-drift bug: the register JSON was namespaced
  correctly, but the manifest was never updated to follow. Fixing the manifest
  references is the complete fix — no schema change is needed or wanted.

## Affected Projects
- [x] Project: `larpingapp` — `src/manifest.json` schema-reference repoint only

## Capabilities
- `game-mechanics` — MODIFIED (Item CRUD: manifest schema-slug requirement)
- `events-players` — MODIFIED (Event CRUD Operations: manifest schema-slug requirement)

## Scope

### In Scope
- Repoint all 7 `"schema": "event"` references in `src/manifest.json` to
  `"schema": "larping_event"`.
- Repoint all 4 `"schema": "item"` references in `src/manifest.json` to
  `"schema": "larping_item"`.
- Verify no other file (`src/menu-layout.json`, `src/registry.js`,
  `src/views/ObjectDetail.vue`) carries a schema-slug reference to `event` or
  `item` that also needs repointing (investigated — none found; see design.md).

### Out of Scope
- Any change to `lib/Settings/larpingapp_register.json` — the `larping_event`/
  `larping_item` slugs are already correct there; this change only makes the
  manifest agree with them.
- Renaming the `event`/`item` *object types* used elsewhere in this app's own
  code as filter keys or route params (e.g. `filter: {"event": "@objectId"}` on
  `xpAward`, or the `event`/`item` keys in `ObjectDetail.vue`'s
  `LEAVES_BY_OBJECT_TYPE` map) — those are internal app-level identifiers, not
  OpenRegister schema-slug lookups, and are unaffected by this bug (see
  design.md for the distinction).
- Resolving the upstream slug-collision mechanism itself (OpenRegister's global,
  non-namespaced `lower(slug)` resolution) — that is a cross-app OpenRegister
  concern already tracked via `reference_or-cross-app-schema-slug-collision.md`;
  this change only fixes larpingapp's own manifest to use its already-namespaced
  slugs.

## Approach
Mechanical find-and-replace of the `"schema"` value at each of the 11 identified
sites in `src/manifest.json`, from the bare `"event"`/`"item"` to the namespaced
`"larping_event"`/`"larping_item"` that `larpingapp_register.json` already
declares. See `design.md` for the full site-by-site list and the root-cause
mechanics of the collision.

## New Dependencies
None.

## Impact
- `src/manifest.json` — Events index/detail/dashboard-widget/onboarding
  `"schema"` references (7 sites) and Items index/detail/dashboard-widget
  `"schema"` references (4 sites).
- Events and Items create/edit/detail forms — will render larpingapp's own
  `event`/`item` fields instead of the colliding app's schema fields once fixed.
- No PHP, register-schema, or Vue component changes.

## Cross-Project Dependencies
None. This is a self-contained manifest fix within `larpingapp`; the schemas it
repoints to (`larping_event`, `larping_item`) already exist in this app's own
`lib/Settings/larpingapp_register.json`.

## Risks

### Risk 1: A manifest reference site is missed
**Severity:** Low — **Mitigation:** All 11 sites were enumerated by exhaustive
grep of `src/manifest.json` for `"schema": "event"` and `"schema": "item"`
before this proposal was written (see design.md for the full list with line
numbers); `tasks.md` has one task per site so each is independently verifiable,
and the test plan manually exercises both the Events and Items create/list/
detail surfaces end-to-end after the fix.

### Risk 2: A schema-slug lookup elsewhere in the app (non-manifest) was missed
**Severity:** Low — **Mitigation:** `src/menu-layout.json`, `src/registry.js`,
and `src/views/ObjectDetail.vue` were grepped for `event`/`item` schema
references as part of scoping this change; none were found (the only `event`/
`item` occurrences in those files are internal object-type keys — e.g.
`ObjectDetail.vue`'s `LEAVES_BY_OBJECT_TYPE` map — not OpenRegister schema-slug
lookups, per the Out of Scope note above).

## Rollback Strategy
Revert the `src/manifest.json` edit (single file, single commit). The Events and
Items pages return to resolving the colliding schema — a regression, but not a
crash, and identical to current production behavior.

## Open Questions
None — the mismatched slugs were confirmed directly against
`lib/Settings/larpingapp_register.json` (`larping_event`, `larping_item`) and
every reference site was enumerated by grep before this proposal was written.
