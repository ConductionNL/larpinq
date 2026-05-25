---
status: draft
---

# Replace the free-text Event location with the maps leaf

## Why

`docs/Schema/Event.json` models `location` as a bare `string`. It is just free
text — no coordinates, no map, no directions, no reuse across events held at the
same venue. For a LARP app where players need to physically travel to a forest
camp or castle, a string is a poor fit.

Per **hydra ADR-022** and **ADR-019**, OpenRegister exposes a **maps leaf**
(location data + map widget) through the integration registry. LarpingApp MUST
consume the maps leaf for event location rather than keep a bespoke free-text
field as the only location surface. This mirrors the DocuDesk PDF
integrate-don't-build precedent (`openspec/specs/pdf-export/`).

## What Changes

- Surface the OR **maps leaf** on the Event detail page to capture and display
  the event's location (address / coordinates) and render a map widget.
- The location is stored as structured location data owned by the maps leaf /
  OR maps abstraction, not as a duplicate LarpingApp geo model.
- Backward compatibility: an existing free-text `location` string is treated as
  the initial address input for the maps leaf (geocoded best-effort); no data is
  lost.
- Graceful degradation: when the maps leaf is unavailable, fall back to showing
  the plain `location` string (read-only), mirroring the PDF graceful-degrade
  pattern.

## Impact

- Affected specs: `event-location-to-maps-leaf` (new capability delta). Touches
  the `events-players` location handling conceptually.
- Affected code (apply phase, NOT here): `src/views/ObjectDetail.vue` (event
  type), Event form location field.
- Depends on: OR integration registry (ADR-019) exposing the maps leaf.
- No stat-engine impact.
