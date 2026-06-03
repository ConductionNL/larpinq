---
status: pr-created
---

# Design — character-photos-leaf

## Context

`docs/Schema/Character.json` has no image/portrait/avatar property. Characters
are visual personas; a reference photo is a natural, missing affordance.

## Decision

Consume the OpenRegister **photos leaf** (ADR-019, ADR-022) on the Character
detail page for portrait / reference images, rather than adding a bespoke
image-upload field to the Character schema.

### Why a leaf, not in-app

- The photos leaf is built on OR's files / object-interactions abstraction
  (ADR-022 lists object-interactions: "Files, notes, tags, audit per object").
  A LarpingApp image column + upload handler would duplicate that.
- Files get OR audit/retention/versioning for free.
- Precedent: PDF export delegates wholesale to DocuDesk.

## Alternatives considered

- **Add `portrait` (base64 / URL) to Character.json** — rejected: app-local
  blob handling duplicates the OR files abstraction (ADR-022), no
  audit/versioning, bloats the object.
- **Reuse the DocuDesk PDF path for images** — rejected: DocuDesk is for
  document rendering, not image galleries; photos is its own OR leaf.

## Scope note

Lower priority than the event/player leaf changes. Included for completeness of
the leaf-migration set. No stat-engine interaction.

## Risks

- Minimal. The only dependency is the photos leaf being exposed via the
  integration registry; absent that, the page degrades to today's behaviour.
