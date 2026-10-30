---
kind: config
depends_on: [worlds-lore-pages]
---

# Proposal: worlds-maps

## Summary

Every campaign has a map: the valley the game site plays, the kingdom beyond
it. A game master wants to upload that drawing, pin the places on it, and let
players click a pin to read about the place. Larpinq only geocodes an event's
real address. This change adds world maps: an uploaded image per world with
pins that link to lore pages, shown on a map page that players can read.

## Motivation

One worlds row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Worlds is a core
area; the OpenSpec pass of 2026-09-27 decided `build`.

**`wld-maps`**, "Upload an in-game map and pin locations on it." Larpinq rates
it no. Matrix evidence: "grep -rniE '\bmap\b' lib src: only hits are
event.location geocoded through the Nextcloud Maps app via
lib/Settings/register.d/event-location-to-maps-leaf.json (a single address
string per event, not an uploadable annotated map), plus unrelated PHP
array-map/Db-mapper hits". Reached on: "nothing: only per-event
location-to-Maps integration exists, not a world map with pins". One
competitor rates it yes:

- Kanka: "routes/campaigns/entities.php:328-331 maps with layers, groups and markers; markers link to entities (60-72 entity map markers)" (source read at tag 3.15).

LarpManager is rated no ("no map upload or location pin model in
larpmanager/models/").

## Affected Projects

- [ ] Project: `larpinq`: two schemas (world map, map pin), a map page, and a pin list on the world page.
- [ ] Project: `nextcloud-vue` (not specified here): an `image` layer type with a flat, non-geographic coordinate system in `CnMapWidget`, so `type: "map"` pages can show a picture instead of tiles.

## Scope

### In Scope

- `worldMap` per world: title, the image file, its width and height in pixels, visibility (game masters, players).
- `mapPin` on a map: label, x and y on the image, an icon kind (town, camp, ruin, danger, other), an optional lore page link, visibility.
- A map read page per world map showing the image with its pins; a pin opens its label and a link to the lore page.
- Game masters place pins by entering coordinates on the pin form, or by clicking on the map once the widget emits click positions.
- Players see a map and its pins only when both are for players.

### Out of Scope

- Layers, fog of war and pins that move over time.
- Drawing on the map.

## Approach

Declarative in larpinq: schemas and read rules in a register fragment, a
`type: "map"` page in a manifest fragment whose layer is the world map's image
and whose markers come from the map's pins. The image layer needs one addition
to `@conduction/nextcloud-vue`, named as a cross-project dependency. Details in
design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/worlds-maps.json` (new): `worldMap`, `mapPin`.
- `src/manifest.d/worlds-maps.json` (new): Maps index, map page, menu entry.
- `src/manifest.json`: SettingDetail gains a "Maps" list (edited in place).

## Cross-Project Dependencies

`@conduction/nextcloud-vue` `CnMapWidget` supports tile, WMS, WFS and GeoJSON
layers today (`docs/components/cn-map-widget.md`). This change needs a layer
`{"type": "image", "url": ..., "width": ..., "height": ...}` rendered with
Leaflet's flat coordinate system, and markers given as x and y. That belongs in
nextcloud-vue and is reported for the coordinator; larpinq's pages wait for it.
`markers.dataSource.{register, schema}` is reserved in the widget; until its
resolver lands the page uses `dataSource.url` on the OpenRegister objects API.

## Risks

### Risk 1: The widget addition does not land
**Severity:** Medium. **Mitigation:** the schemas and the pin list work without it; the map page is the last task and is gated on the nextcloud-vue release.

### Risk 2: Large images
**Severity:** Low. **Mitigation:** the image is a Nextcloud file; the page loads it once and Leaflet scales it; the form warns above 10 MB.

## Rollback Strategy

Remove the fragments and the SettingDetail list. Maps and pins stay as data.

## Open Questions

None.
