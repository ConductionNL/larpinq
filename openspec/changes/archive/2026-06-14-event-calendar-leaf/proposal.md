---
status: draft
---

# Surface the calendar leaf on the Event detail page

## Why

Events carry `startDate` and `endDate` (`docs/Schema/Event.json`) — they are
intrinsically temporal. Today the only date handling is a bespoke pair of
`date-time` form fields on the generic Event form; there is no calendar view,
no agenda, no scheduling conflict awareness, and no way to see a LARP season
laid out over time.

Per **hydra ADR-022** (Apps Consume OpenRegister Abstractions) and **ADR-019**
(Integration Registry Pattern), OpenRegister exposes a **calendar leaf** that
renders an object's date range as a calendar event and surfaces a calendar
widget on the object detail page. LarpingApp MUST consume this leaf instead of
building a parallel date/scheduling UI. This follows the existing
integrate-don't-build precedent already in the codebase — the PDF export
delegates entirely to DocuDesk (`openspec/specs/pdf-export/`).

## What Changes

- Surface the OR **calendar leaf** as a widget on the Event detail page
  (`src/views/ObjectDetail.vue` for the `event` object type).
- Treat the Event's `startDate` / `endDate` as the source of the calendar
  event's start/end, mapped through the integration registry's calendar
  provider rather than a local CalDAV implementation.
- The bespoke date-time form fields remain the canonical edit surface for the
  underlying object data; the calendar leaf is a read/visualise + cross-app
  scheduling surface, not a second write path.
- Graceful degradation: when the calendar leaf / OR integration registry is
  not available, the calendar widget is hidden (mirrors the DocuDesk PDF
  graceful-degradation pattern).

## Impact

- Affected specs: `event-calendar-leaf` (new capability delta).
- Affected code (apply phase, NOT in this change): `src/views/ObjectDetail.vue`,
  event detail tab wiring. No backend stat-engine impact — Event effects and
  stat calculation are untouched.
- Depends on: OR integration registry (ADR-019) exposing the calendar leaf;
  nc-vue leaf-host component.
