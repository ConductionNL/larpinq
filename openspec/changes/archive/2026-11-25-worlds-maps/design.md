# Design: worlds-maps

## Context

Read at development `2af18d8`.

- The only map in larpinq is the Nextcloud Maps leaf on `event.location`
  (`lib/Settings/register.d/event-location-to-maps-leaf.json`): one real-world
  address per event.
- `@conduction/nextcloud-vue` `type: "map"` pages (`CnMapPage`, `CnMapWidget`,
  Leaflet) take `config.{center, zoom, layers, markers, height, clustering,
  autoFit}`. Layer types: `tile`, `wms`, `wfs`, GeoJSON. Markers come from
  inline features or `markers.dataSource.url` with `latField`, `lngField` and
  `popupField`; `dataSource.{register, schema}` is reserved.
- Lore pages arrive with `worlds-lore-pages` (`larping_lore_page`).
- Files: character portraits use the photos leaf; an item has a files leaf
  (`item-files` integration widget).

## Goals / Non-Goals

**Goals**: an uploaded map per world with pins that link to lore, readable by
players when meant for them.

**Non-Goals**: layers, fog of war, drawing.

## Decisions

### D1. Schemas

- `worldMap` (slug `larping_world_map`): `setting`, `title`, `image` (file,
  stored as an OpenRegister file property), `width`, `height` (pixels, read from
  the image on upload), `visibility` (`gamemasters`, `players`).
- `mapPin` (slug `larping_map_pin`): `map` (uuid `$ref` worldMap), `label`,
  `x`, `y` (pixels from the top left), `kind` (`town`, `camp`, `ruin`,
  `danger`, `other`), `lorePage` (uuid `$ref` lorePage), `visibility`.

Rules on both: read by `gamemasters`, and by `larpers` where `visibility =
players`; write by `gamemasters`. A pin of a game-master map is unreachable for
players because its map is.

### D2. The map page

`WorldMap` page, `type: "map"`, route `/maps/:id`: one layer `{"type":
"image", "url": <the map's image>, "width", "height"}` and markers from
`dataSource.url` = the OpenRegister objects API for `mapPin` filtered on the
map, with `latField: y`, `lngField: x`, `popupField: label`. The popup links
to the pin's lore page.

Alternative: a larpinq Leaflet component. Rejected: hydra ADR-012 and ADR-072
put map rendering in nextcloud-vue; a second Leaflet wrapper in one app is the
duplication those ADRs exist to stop.

### D3. Managing

`Maps` index (fragment) on `worldMap` with create and edit; the map's detail
lists pins (object-list, create allowed) with x and y fields. When
`CnMapWidget` emits a click position on an image layer, the game master's map
page offers "Add pin here", which opens the pin form with x and y filled.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Maps and pins | Declarative, register fragment | Schemas and rules. |
| Showing the map | Declarative, `type: "map"` page | nextcloud-vue renders it. |

No larpinq PHP or Vue.

## Seed data

World Aldmoor: map "The valley of Aldmoor" (players, 2400 by 1600) with pins
"Aldmoor city" (town, lore "The city of Aldmoor"), "North gate" (ruin, lore
"The fall of the north gate") and "Ash grove" (danger, game masters).

## Risks / Trade-offs

- [Dependency] The page waits for the nextcloud-vue image layer.

## Migration

None.

## Open Questions

None.
