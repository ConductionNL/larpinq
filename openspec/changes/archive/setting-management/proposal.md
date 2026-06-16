---
status: draft
---

# Setting (LARP world/campaign) management

## Why

The app's literal headline is "Manage your live roleplaying **setting**"
(info.xml summary; description: "Larping provides a tool to manage your
setting") — yet the setting is the one thing the app cannot manage. A
vestigial `setting` object type exists in `lib/Settings/larpingapp_register.json`
(a generic `{name, description, value}` key-value blob) and is registered in
`src/store/store.js` SCHEMA_SLUGS, but it has no manifest page, no navigation
entry, no spec, and no schema in `docs/Schema/`. The 2026-06-11 feature
re-evaluation (`FEATURE-REEVALUATION-2026-06-11/larpingapp.md`) flags this as
the headline promise-vs-reality gap and asks us to "decide the `setting`
entity's fate".

This change decides it: the setting becomes a first-class LARP
world/campaign entity. Real LARP organisations run multiple campaigns (a
fantasy summer campaign and a sci-fi winter one) with disjoint rule sets,
characters, and event seasons. Today every list in the app is one flat,
global pool — skills from one world pollute the Add-Skill picker of another,
and a GM cannot answer "what belongs to Summer Realm 2026?". Category peers
(LARPortal campaigns, World Anvil worlds, RPG campaign managers) all treat
the campaign/world as the top-level organising unit.

## What Changes

- **Repurpose the vestigial `setting` schema into a campaign entity.** The
  unused `{name, description, value}` key-value shape becomes
  `{name, description, status}` (status: `active` | `archived`), schema
  version 2.0.0. No UI ever read or wrote the old shape, so this is a safe
  repurpose, not a breaking migration.
- **Setting management UI.** New manifest pages: a `Settings` index page and
  a `SettingDetail` detail page (typed primitives, same pattern as every
  other entity), navigation entry, create/edit/delete modals. The detail
  page shows the campaign overview: the characters, events, and mechanics
  scoped to it.
- **Optional setting scoping on game entities.** `character`, `event`,
  `skill`, `item`, `condition`, `ability`, and `effect` schemas gain an
  optional `setting` UUID reference. `player` is deliberately NOT scoped — a
  player is a real person who spans campaigns. An entity without a setting is
  **shared** (visible in every setting), which makes all existing data
  backwards-compatible by definition.
- **Active-setting context.** The user picks an active setting (or "All
  settings") via a switcher; the choice is persisted per user through the
  existing preferences API (REQ-PREF-001/002). Index pages and dashboard
  widgets filter to the active setting plus shared entities, server-side via
  OpenRegister object filters (not client-side slicing, so pagination stays
  correct).
- **Scoped assignment pickers.** Add-Skill/Item/Condition/Event modals
  default their pickers to the active setting + shared entities;
  cross-setting picks remain possible but are visibly flagged. Scoping is an
  organisational lens, not a security boundary — authorization stays
  OR-delegated (ADR-022).
- **Guarded lifecycle.** A setting with scoped entities cannot be deleted
  (the confirmation dialog lists the counts); finished campaigns are
  archived instead. Archived settings disappear from the switcher default
  but their data stays readable.

## Impact

- Affected specs: `setting-management` (new capability — owns the setting
  entity, the scoping property, the active-setting context, pickers, and
  lifecycle). No existing requirement is contradicted; character-management /
  events-players / rpg-system stay untouched and gain the optional `setting`
  property purely additively (synced into their schema tables on archive if
  desired).
- Affected code (apply phase, NOT here):
  - `lib/Settings/larpingapp_register.json` — `setting` schema v2.0.0;
    optional `setting` property on character/event/skill/item/condition/
    ability/effect
  - `docs/Schema/Setting.json` (new)
  - `src/manifest.json` — `Settings` index + `SettingDetail` detail pages,
    menu entry
  - `src/store/store.js` / `src/store/modules/object.js` — active-setting
    filter plumbing on list fetches
  - Setting switcher component (app navigation footer) + persistence via
    the preferences API
  - Add-*-modals — picker default filter + cross-setting flag
  - `l10n/` nl + en strings; `appinfo/info.xml` version bump (cache-bust)
- Depends on: nothing new — OR object filters, preferences-api
  (REQ-PREF-001/002), and the manifest typed pages all exist.
- Relates to: `event-calendar-leaf` (a season view benefits from
  per-setting filtering), `setting-lore-xwiki-leaf` (future: world lore via
  the xwiki leaf per ADR-022 — deliberately OUT of scope here),
  `larpingapp-adopt-or-abstractions` (manifest page mechanics).
- Naming: the in-app "Game Settings" config page (type `settings`) is
  app configuration; the new "Settings" index manages LARP settings
  (worlds). The nav entry uses a Globe icon and the detail page is titled
  "Setting" to keep the two apart (see design.md).
