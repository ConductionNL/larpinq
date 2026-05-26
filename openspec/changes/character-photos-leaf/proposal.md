---
status: draft
---

# Offer the photos leaf on the Character detail page

## Why

Characters (`docs/Schema/Character.json`) have name, type, background, faith and
the in-game currency fields — but **no portrait / image field**. A LARP
character is a visual, costumed persona; players and game masters routinely want
a reference photo (costume, makeup, kit) attached to the character sheet.

Rather than add a bespoke image-upload field + storage to LarpingApp, per
**hydra ADR-022** + **ADR-019** the app SHOULD consume the OpenRegister
**photos leaf** (image gallery widget backed by the object-interactions /
files abstraction) on the Character detail page. Mirrors the DocuDesk PDF
integrate-don't-build precedent.

This change is **optional / lower priority** relative to the four event/player
leaf changes; it is included so the migration set is complete.

## What Changes

- Surface the OR **photos leaf** on the Character detail page so portraits /
  reference photos can be attached and viewed.
- Images are stored via the OR files / object-interactions abstraction the
  photos leaf is built on — not a new LarpingApp image column.
- Graceful degradation: when the photos leaf is unavailable, no portrait widget
  is shown and the character detail page works as today, mirroring the PDF
  graceful-degrade pattern.

## Impact

- Affected specs: `character-photos-leaf` (new).
- Affected code (apply phase, NOT here): `src/views/ObjectDetail.vue` (character
  type).
- Depends on: OR integration registry (ADR-019) exposing the photos leaf.
- No stat-engine impact — portraits are presentation, not stats.
