# Design: worlds-lore-pages

## Context

Read at development `2af18d8`.

- Worlds are `setting` objects (`name`, `description`, `status`) with a
  SettingDetail page listing characters and events (`src/manifest.json`, page
  `SettingDetail`, widgets `setting-characters`, `setting-events`).
- Nothing in larpinq holds lore (`beta-surface-alignment` removed claims of
  it). The archived `setting-management` proposal named a future
  `setting-lore-xwiki-leaf` "via the xwiki leaf per ADR-022".
- larpinq ADR-001: consume OpenRegister leaves; "any future parallel mechanism
  requires an explicit ADR-022 exception ADR in this folder".
- OpenRegister ships `CollectivesProvider` and `XwikiProvider` leaves, and row
  level security with `match`, operators such as `$lte`, and the `$now`
  variable evaluated in SQL (spec `row-field-level-security`, scenario
  "Time-based access via $now variable with operator").
- `@conduction/nextcloud-vue` offers `type: "wiki"` pages (`CnWikiPage`,
  read-only markdown article with an optional sidebar tree, props
  `contentField`, `titleField`, `sidebarSchema`, `treeField`) and
  `CnMarkdownEditor`.
- New pages go in `src/manifest.d/`; SettingDetail is edited in place.

## Goals / Non-Goals

**Goals**: lore per world; players read what is theirs; timed reveals that
cannot leak.

**Non-Goals**: mentions, reveal notifications, per-player pages.

## Decisions

### D1. Lore pages are register objects (ADR-003)

`lorePage` (slug `larping_lore_page`): `setting` (uuid `$ref` setting),
`title`, `body` (string, `format: markdown`), `category` (`place`, `faction`,
`history`, `rules`, `other`), `visibility` (`gamemasters`, `players`),
`revealFrom` (date-time), `parent` (uuid `$ref` lorePage), `order` (integer).

Alternatives considered: the Collectives leaf and the XWiki leaf on `setting`.
Both give a good editor, but access is granted per collective or space, not
per page, and neither has a reveal moment, so `wld-share-with-players` and
`wld-scheduled-reveal` would stay unmet. The change adds
`openspec/architecture/adr-003-lore-pages-are-register-objects.md` recording
this exception to ADR-001 and hydra ADR-022.

### D2. Read rules

```json
"authorization": {
  "read": [{"group": "gamemasters"},
           {"group": "larpers", "match": {"visibility": "players", "revealFrom": {"$lte": "$now"}}}],
  "create": ["gamemasters"], "update": ["gamemasters"], "delete": ["gamemasters"]
}
```

`revealFrom` is required for pages with visibility players: the create form
fills it with the current moment, so "visible now" is the default and an empty
value never needs its own rule. The rule runs in the database query, so the
index, the read page, search and exports all obey it.

### D3. Pages

- `Lore` index (fragment) on `lorePage`: title, category, world, and for game
  masters visibility and reveal moment; filter by world and category. Create
  and edit use the standard form; `body` renders with `CnMarkdownEditor`.
- `LoreArticle` read page (fragment), `type: "wiki"`, `contentField: body`,
  `titleField: title`, sidebar tree over `lorePage` by `parent`.
- SettingDetail (in place): object-list "Lore" of `lorePage` filtered on the
  world.
- Menu: "Lore" under the World group.

### D4. Categories feed other changes

`rules` pages are the prose chapters of the rulebook in
`rules-rulebook-and-spell-lists`; `faction` pages can be linked from factions
in `characters-factions-and-relationships` later.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Lore page data | Declarative, register fragment | A schema. |
| Visibility and timed reveal | Declarative, row rule with `$now` | OpenRegister evaluates it in SQL. |
| Reading and managing | Declarative, manifest pages (`index`, `wiki`) | Existing page types. |

No PHP.

## Seed data

World Aldmoor: "The city of Aldmoor" (place, players, revealed), "The war of
the two queens" (history, players, revealed), "The Ash Circle" (faction, game
masters), "The fall of the north gate" (history, players, reveal on
2026-10-10 18:00, the evening of the event).

## Risks / Trade-offs

- [Clock] `$now` is the server's time; the form shows the reveal moment in the
  game master's time zone and stores UTC.
- [Editor] The markdown editor is plain; rich layout is out of scope.

## Migration

None.

## Open Questions

None.
