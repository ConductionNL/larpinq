# Design — event-location-to-maps-leaf

## Context

`docs/Schema/Event.json`: `"location": { "type": "string" }`. The
`events-players` spec scenario already uses `location "Forest Camp"`. It is
unstructured free text — adequate to label, useless to navigate.

## Decision

Consume the OpenRegister **maps leaf** (ADR-019 integration registry, ADR-022
consume-don't-build) on the Event detail page for location capture + display.
The maps leaf owns the structured location model (address, coordinates) and the
map widget.

### Why a leaf, not in-app

- Maps is an OR integration-registry leaf (ADR-022). A LarpingApp-local geo
  model + map embed would be the "app-local ... that mirror an OR integration"
  anti-pattern.
- One geo model across the fleet: an address means the same thing in LarpingApp
  as in procest/zaakafhandelapp.
- Precedent: PDF export delegates wholesale to DocuDesk.

### Migration of the existing free-text field

- The legacy `location` string is not discarded. On first edit through the maps
  leaf it is offered as the initial address line and geocoded best-effort.
- Until migrated, the plain string is shown read-only (this is also the
  graceful-degradation fallback when the maps leaf is absent).

## Alternatives considered

- **Keep free-text only** — rejected: no map, no navigation, doesn't satisfy the
  user need; leaves an OR abstraction unconsumed (ADR-022).
- **Embed a Leaflet/OSM map directly in LarpingApp** — rejected: duplicates the
  OR maps leaf, no cross-app consistency, ADR-022 violation.

## Risks

- Geocoding a free-text legacy value may be ambiguous ("Forest Camp"). Mitigated
  by treating it as a non-authoritative address hint the user can correct; the
  original string is preserved until the user confirms a location.
