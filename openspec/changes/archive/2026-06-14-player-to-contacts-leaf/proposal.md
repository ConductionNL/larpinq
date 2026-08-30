---
status: draft
---

# Consume the contacts leaf for Player person data

## Why

The Player entity is skeletal: `{name, description}` (`events-players/spec.md`,
PLR-001..PLR-008). Players "represent real-world people". Yet there is no email,
phone, emergency contact, address, or any of the person fields a LARP organiser
actually needs to reach a participant — and no link to Nextcloud's own contacts.

Per **hydra ADR-022**, a person/contact is exactly the kind of model an app must
NOT reinvent — the ADR explicitly calls out "an app-local Person vs OR contacts"
as a duplicate-data-model anti-pattern. OpenRegister exposes a **contacts leaf**
via the integration registry (ADR-019). LarpingApp MUST consume the contacts
leaf for player/person data on the Player detail page rather than grow a bespoke
person model. Mirrors the DocuDesk PDF integrate-don't-build precedent.

## What Changes

- Surface the OR **contacts leaf** on the Player detail page so player person
  data (name, email, phone, address, etc.) is sourced from / linked to the OR
  contacts abstraction.
- The in-game relationship between a Player and their characters (the
  `ocName` linkage, PLR-006) stays in LarpingApp — that is LARP domain logic,
  not person data. Only the person/contact attributes move to the leaf.
- Backward compatibility: the existing Player `name`/`description` are mapped to
  the linked contact's display name / notes; existing `ocName` references by
  characters continue to resolve.
- Graceful degradation: when the contacts leaf is unavailable, the Player detail
  page falls back to the existing `{name, description}` fields.

## Impact

- Affected specs: `player-to-contacts-leaf` (new). Conceptually narrows the
  `events-players` Player model to in-game linkage + a contacts-leaf reference.
- Affected code (apply phase, NOT here): `src/views/ObjectDetail.vue` (player
  type), player form.
- Depends on: OR integration registry (ADR-019) exposing the contacts leaf; the
  OR contacts schema (ADR-022 "follow OR's schema when OR has a schema").
- No stat-engine impact.
