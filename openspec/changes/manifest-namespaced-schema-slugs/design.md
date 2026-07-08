# Design: manifest-namespaced-schema-slugs

## Architecture Overview
Pure JSON edit inside `src/manifest.json` — the declarative, nc-vue-driven page/
widget definition file that `CnPageRenderer` and the dashboard widget engine read
to build the Events and Items pages. No PHP, no register schema, no Vue component
changes. The fix is a mechanical value substitution at 11 sites: every
`"schema": "event"` becomes `"schema": "larping_event"`, and every
`"schema": "item"` becomes `"schema": "larping_item"`.

### Root cause: global, non-namespaced schema-slug resolution
OpenRegister resolves a `"schema"` reference — wherever one appears in a
manifest page config, widget `content`, or `advanceOn` rule — by a **global**
`lower(slug)` lookup across every installed app's registers. Slugs are not
namespaced per app; two apps can each define a schema named `event`, and
whichever one OpenRegister's slug index resolves first wins for *every*
consumer of that bare slug, regardless of which app's manifest wrote the
reference. This is the same mechanism documented in
`reference_or-cross-app-schema-slug-collision.md`.

`lib/Settings/larpingapp_register.json` already anticipated this: its `event`
and `item` schema definitions carry the namespaced real slugs `larping_event`
and `larping_item` (confirmed via `x-openregister-schema-slug` on each), unlike
every other larpingapp schema (`character`, `player`, `ability`, `skill`,
`condition`, `effect`, `setting`, `xpAward`), which all keep their bare key as
their real slug — those names are unique enough across the fleet not to
collide.

### The bug
`src/manifest.json` was never updated to match. It still writes the bare,
pre-namespacing slugs `"event"` and `"item"` at every reference site. Because
OpenRegister resolves by the *global* slug index, not by which app wrote the
reference, `"event"` resolves to **openconnector's CloudEvents `event` schema**
(fields: Source, Type, Spec Version, datacontenttype, dataschema…) and
`"item"` resolves to a different installed app's `item` schema — not
larpingapp's own. The Events and Items create/list/detail forms therefore
render the wrong schema's fields.

## Reference Sites (enumerated by grep, `src/manifest.json`)

### `"schema": "event"` — 7 sites, all repointed to `"schema": "larping_event"`
| Line | Site |
|------|------|
| 30 | Onboarding `advanceOn` rule for the "create your first event" tour step (`type: "object-created"`) |
| 86 | Dashboard KPI widget `kpi-events` (count metric) |
| 90 | Dashboard object-list widget `recent-events` |
| 383 | Events index page `config.schema` |
| 392 | Events detail page `schema` (page-level object-type binding) |
| 448 | Setting-detail page's `setting-events-kpi` stat widget (count filtered by `setting`) |
| 450 | Setting-detail page's `setting-events` object-list widget |

### `"schema": "item"` — 4 sites, all repointed to `"schema": "larping_item"`
| Line | Site |
|------|------|
| 87 | Dashboard KPI widget `kpi-items` (count metric) |
| 267 | Items index page `config.schema` |
| 276 | Items detail page `schema` (page-level object-type binding) |
| 358 | Effect-detail page's `effect-items` object-list widget (items granting the effect) |

## Investigation: no other file needs a repoint
Grepped for `event`/`item` schema references outside `src/manifest.json`:
- `src/menu-layout.json` — no schema references at all (navigation entries key
  off route names, not schema slugs).
- `src/registry.js` — no schema references (component registry keyed by
  section/component name).
- `src/views/ObjectDetail.vue` — contains a `LEAVES_BY_OBJECT_TYPE` map keyed
  by `'character' | 'event' | 'player'`, but this key is the component's
  `objectType` **prop** (a generic "which integration leaves apply" lookup, not
  an OpenRegister `"schema"` reference — no manifest section currently binds
  `ObjectDetail` with `object-type="event"`, so this map is inert for `event`
  today and out of scope for this fix regardless). Not repointed.
- `lib/Settings/larpingapp_register.json` — already correct
  (`larping_event`/`larping_item`); not touched by this change.

## Nextcloud Integration
- Controllers: none touched.
- Services: none touched — OpenRegister's existing schema-slug resolver picks up
  the corrected slugs automatically once the manifest is re-read; no explicit
  re-import step is required (manifest.json is read live by the frontend, unlike
  the register JSON's app-boot auto-import).
- Mappers/Entities: none touched.
- Events/Hooks: none touched.

## Security Considerations
No security impact. This fixes a schema-resolution bug that currently causes
the Events/Items forms to read/write the wrong (unrelated app's) schema; after
the fix, users can only read/write larpingapp's own `event`/`item` objects via
these pages, which is strictly more correct and no more permissive than today.

## NL Design System
Not applicable — no component or styling changes. The Events/Items pages
continue to render through the same nc-vue `CnPageRenderer`/`CnFormDialog`
components; only the schema slug they resolve against changes.

## File Structure
```
src/
  manifest.json   # only file touched
```

## Trade-offs
The only alternative considered was renaming the schemas back to bare `event`/
`item` in `lib/Settings/larpingapp_register.json` instead of fixing the
manifest. Rejected — the whole reason `larping_event`/`larping_item` exist is to
*avoid* the global slug collision (per
`reference_or-cross-app-schema-slug-collision.md`); reverting the namespacing
would just reintroduce the same wrong-schema bug this change fixes, and would
also require re-import of the register JSON (a broader, riskier change) versus
this change's single manifest-file, zero-schema-change fix.
