# Design — setting-management

## Context

The `setting` object type already exists end-to-end as plumbing: it is in the
register config (`lib/Settings/larpingapp_register.json`, slug `setting`,
shape `{name, description, value}`), in the register's schema list, and in
`src/store/store.js` SCHEMA_SLUGS — but it has no manifest page, no schema
doc, and no spec. It was scaffolded as a generic key-value "game setting or
configuration value" and never used. Meanwhile the app summary promises
"Manage your live roleplaying setting".

All other entities follow one uniform pattern: schema in the register config,
typed `index` + `detail` manifest pages, generic `useObjectStore` CRUD, and
detail tabs via OR relations. The cheapest credible campaign management is to
make `setting` exactly that — plus one cross-cutting concern no other entity
has: scoping everything else.

## Decision

### 1. Repurpose, don't add — `setting` becomes the campaign entity

The existing slug/object type is kept and its schema is rewritten to
`{name (required), description, status: active|archived (default active)}`,
version 2.0.0. Adding a parallel `campaign` type would leave the headline
term "setting" dangling and strand a dead schema. Risk is nil: no view,
modal, or service ever read `value`; any stray object keeps its `name` and
`description` and simply ignores the dropped property (OR schemas are not
retroactively destructive).

App-config-style key-values (the old intent) are already covered by the real
"Game Settings" admin/config surface — the vestigial type never had a job.

### 2. Scoping is an optional UUID property; absent means shared

`character`, `event`, `skill`, `item`, `condition`, `ability`, and `effect`
gain an optional `setting` string (UUID of a setting object). Semantics:

- **Set** → the entity belongs to that one setting.
- **Absent/empty** → the entity is shared and visible under every setting.

This single rule makes the whole existing dataset valid without migration
(everything is shared until a GM starts sorting), and it gives organisations
a natural place for cross-campaign material (generic conditions like
"Poisoned", system-wide abilities like "XP").

`player` is NOT scoped: a player is a real person, not game content, and the
in-flight `player-to-contacts-leaf` moves player identity toward NC contacts
anyway. Single-reference (not `settings[]` array) is deliberate: "belongs to
exactly one campaign or is shared" is the model peers use and keeps the
filter semantics trivially explainable; multi-setting sharing is what the
shared (unscoped) state is for.

### 3. Active-setting context: a per-user lens, server-side filter

- A switcher (app-navigation footer) offers: each `active` setting, plus
  "All settings". The choice persists per user via the existing preferences
  API (REQ-PREF-001/002, key `activeSetting`).
- Index pages and dashboard widgets pass the filter to OpenRegister
  (`setting = <uuid> OR setting unset`) so pagination/search/counts stay
  correct — never client-side slicing of a paginated result.
- Detail pages, deep links, and relations tabs are NOT filtered: a direct
  link to an out-of-setting character must keep working (the lens narrows
  lists, it never 404s objects).
- **The lens is not a security boundary.** RBAC stays fully OR-delegated
  (ADR-022); a user can always switch to "All settings". Anyone needing real
  per-campaign access control should use OR register/schema RBAC.

### 4. Pickers default to the lens, cross-setting stays possible

Add-Skill/Item/Condition/Event modals filter their pickers to the active
setting + shared entities by default. A GM can toggle "show all settings";
a pick from another setting is accepted but rendered with the foreign
setting's name as a visible flag. No server-side veto: mixing is sometimes
legitimate (crossover events, migrating content), and hard enforcement
would entangle this change with the `skill-requirement-enforcement` write
hook for marginal benefit. Revisit only if real-world misuse shows up.

### 5. Lifecycle: block delete while populated, archive instead

- Deleting a setting that still has scoped entities is blocked in the UI;
  the confirmation dialog shows per-type counts (characters, events, skills,
  …) so the GM knows what to reassign first. This guard is a data-integrity
  courtesy, not security (see 3) — a raw API delete merely strands UUID
  references, which every consumer already treats as unset/shared-equivalent
  (mirrors CALC-006 tolerance).
- `status: archived` is the end-of-campaign path: the setting leaves the
  switcher default and pickers, but its detail page and all scoped data stay
  readable (LARP campaigns are history, not garbage).

### 6. Naming collision handled in presentation only

"Settings" (LARP worlds, new index page, Globe icon) vs "Game Settings"
(existing app-config page, type `settings`) vs NC personal/admin settings.
The entity keeps the domain-correct name — it is the app's headline term —
and the UI disambiguates with icon, ordering (Settings lives with the other
content types at the top of the menu; Game Settings stays at the bottom),
and the detail-page title "Setting".

## Alternatives considered

- **New `campaign` entity, keep `setting` as key-value** — rejected: leaves
  the headline term unimplemented and ships a dead schema; "setting" is the
  established LARP word for the world/campaign.
- **`settings[]` array (entity in many settings)** — rejected: complicates
  every filter and picker for a need the shared/unscoped state already
  covers.
- **Mandatory setting on every entity + data migration** — rejected: breaks
  every existing installation's data flow for no gain; opt-in scoping with
  shared-by-default is strictly additive.
- **Hard server-side scoping enforcement (veto cross-setting writes)** —
  rejected for now: the lens is organisational; enforcement costs an OR
  pre-write listener and breaks legitimate crossover/migration flows.
- **Client-side filtering of fetched lists** — rejected: breaks pagination
  and counts; OR query filters are the supported path.

## Risks

- **UI confusion between the three "settings" surfaces** — mitigated by
  icon/title/ordering (Decision 6); watch first usability feedback.
- **Filter must be threaded through every list fetch** — the object store is
  the single chokepoint (`useObjectStore` list calls), so the change is
  central, but a missed call site silently shows unfiltered data; e2e tests
  assert the filter on each index page.
- **Stranded `setting` UUIDs after raw API deletes** — consumers treat
  unknown setting refs as shared + the detail page shows "unknown setting";
  no crash path.
- **Preference key drift across devices** — preferences API is per-user
  server-side, so the lens follows the user; "All settings" is the fallback
  when the persisted setting was archived or deleted.
