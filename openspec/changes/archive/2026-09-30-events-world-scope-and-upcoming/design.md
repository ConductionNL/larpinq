# Design: events-world-scope-and-upcoming

## Context

Read at development `2af18d8`.

- `event.setting` (uuid `$ref` setting) is `visible: false`; EventDetail's
  data widget and the Events index do not show it. Other schemas' pickers
  filter on `setting` with `x-relation-filter`.
- `lib/Controller/PreferencesController.php` stores per-user keys through
  `IConfig` at `GET/PUT /api/preferences/{key}` (REQ-PREF-001/002).
- `openspec/specs/setting-management/spec.md:76-118` requires the per-user
  active-setting lens; its `@e2e exclude` note says the lens was deferred
  ("custom app-nav + useObjectStore plumbing").
- Dashboard (`src/manifest.json`, page `Dashboard`): widget `recent-events`,
  object-table on `larping_event`, `filter: {}`, sort `startDate desc`, limit 6.
- `@conduction/nextcloud-vue` `CnAppRoot` accepts registry components of kind
  `header` mounted into page header slots.

## Goals / Non-Goals

**Goals**: a settable event world; one world at a time in lists; upcoming
events first.

**Non-Goals**: picker defaults, calendar view.

## Decisions

### D1. The event world

A fragment sets `event.setting` to visible with the title "World". EventDetail
(in place) shows it in the data widget; the Events index shows a World column
and facet.

### D2. The switcher and the lens

`useActiveWorld()` loads `active-world` from the preferences API once per
session, exposes the active world (or none for all), and writes it on change.
If the stored world is archived or missing, it falls back to all and clears the
key. `WorldSwitcher.vue` (registry kind `header`) renders an `NcSelect` with
`inputLabel` "World" on the Dashboard and the index pages of world-scoped
schemas (characters, abilities, skills, items, conditions, effects, events).
The composable adds `setting` = the active world or empty to the list query of
those schemas through the object store's default query, so pagination, search
and counts come from the server. Detail pages and deep links are never
filtered.

Alternative: filter in the browser after fetching; rejected by the spec itself
("not by trimming a fetched page").

### D3. Upcoming by default

The Events index gets a default filter `startDate >= today` and sort
`startDate asc`, with a "Past events" toggle that flips both. The dashboard
widget becomes "Upcoming events": same filter and sort, limit 6, and the
view-all link opens the Events index with the upcoming filter.

## Deviations found while building (30 Sep 2026, at development 825fe894)

- **D2, where the switcher lives and how it narrows.** `@conduction/nextcloud-vue`
  2.57.1 does have the `header` registry kind (and `actions`), mounted through
  a page's `headerComponent` / `actionsComponent`; an earlier note that it did
  not was wrong. The switcher is therefore the `header` of the seven
  world-scoped index pages (it draws the page title it replaces) and sits in
  the Dashboard's `actions`, not in the app navigation the setting-management
  requirement names. It does not change the object store's default query:
  every world-scoped index page and dashboard widget carries the optional
  token `@workspace.activeWorld?` in its list filter, and the switcher writes
  the world into the page workspace (`cnWorkspaceContext`). App.vue provides
  an app-wide workspace; the Dashboard provides its own, so the switcher
  writes the choice into both. An unset world drops the filter (the `?`).
- **First paint.** `DashboardController::page()` hands the stored
  `pref_active-world` to the page as the `activeWorld` initial state, so lists
  are narrowed before their first fetch. The switcher then checks the world
  once per session and falls back to all worlds (clearing the preference)
  when it is archived or gone.
- **D3, the past toggle.** The Events index uses two quick filters, Upcoming
  (default, `startDate[gte]` `@today`) and Past (`startDate[lt]` `@today`),
  with sort `startDate` ascending for both; past events are not re-sorted
  newest first.
- **D1, the facet.** The World column comes from the schema (the property is
  now visible); no separate facet was added.
- **D4, shared entities.** OpenRegister's list filter (MagicSearchHandler at
  development) ANDs every operator on a property (`in`, `notIn`, `isnull`,
  ...), so "this world or none" cannot be said in one query, and `NOT IN`
  never matches a missing world either. The lens therefore lists the active
  world's own objects only; world-less objects show under "All worlds". The
  proposal's two-query fall-back was not built: it would break pagination
  and counts, which the requirement protects. A request to OpenRegister for
  an "or empty" filter is drafted at
  `~/memcap-work/build-all/for-ruben/openregister-filter-value-or-empty.md`.
- **Seed data.** No new seed objects: the e2e workflow builds its own worlds
  and events.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Event world visible and editable | Declarative, register fragment and manifest | A property and page config. |
| Upcoming filter and sort | Declarative, manifest filter with a date placeholder | Page config. |
| Active world lens | Frontend composable over the preferences API and list queries | A per-user view state; no server rule changes. |

## Seed data

Events "Summer Siege 2025" (Aldmoor, past), "Winter Court 2026" (Aldmoor,
upcoming), "Station Omega night" (Outer Rim, upcoming). With Aldmoor active,
the Events index shows only "Winter Court 2026".

## Risks / Trade-offs

- [Filter support] See the proposal's cross-project note on "this world or
  none".

## Migration

None.

## Open Questions

None.
