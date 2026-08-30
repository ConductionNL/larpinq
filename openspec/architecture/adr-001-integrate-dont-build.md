# ADR-001: Integrate, don't build — consume OpenRegister leaves

## Status
Accepted

## Date
2026-05-26

## Context

Larpinq manages LARP domain entities (characters, skills, items, conditions,
effects, events, players, abilities, templates). Several of its features overlap
with capabilities OpenRegister already provides as **integration leaves** via
the integration registry (hydra ADR-019): calendar, maps, contacts, forms,
photos, files/notes/tasks, and more.

The app already has one clear integrate-don't-build precedent: **PDF export
delegates wholesale to DocuDesk** — `CharactersController.downloadPdf()`
resolves DocuDesk's `PdfService`/`TemplateService` via DI and degrades
gracefully when DocuDesk is absent (`openspec/specs/pdf-export/spec.md`).

When a new feature is needed, the app faces the recurring choice: consume the OR
abstraction, or build a parallel mechanism in-app. The latter produces duplicate
data models, drift, missed features, and impossible cross-app queries.

## Decision

Larpinq **consumes OpenRegister abstractions / integration leaves** rather
than reinventing them in-app, adopting **hydra ADR-022** (Apps Consume
OpenRegister Abstractions) as binding policy for this repo. Specifically:

- **Calendar leaf** for Event `startDate`/`endDate` (change `event-calendar-leaf`).
- **Maps leaf** for Event `location` (change `event-location-to-maps-leaf`).
- **Contacts leaf** for Player person data (change `player-to-contacts-leaf`).
- **Forms leaf** for the planned event sign-up / waiting list
  (change `event-signup-to-forms-leaf`).
- **Photos leaf** (optional) for Character portraits
  (change `character-photos-leaf`).

Leaves are surfaced on the generic `ObjectDetail.vue` detail pages and obtained
through the OR integration registry; each degrades gracefully when the leaf /
registry is unavailable, mirroring the DocuDesk PDF pattern.

### Non-target — in-game currency stays in-app

The fictional in-game currency on characters (`gold` / `silver` / `copper`,
`docs/Schema/Character.json`, README) is **NOT real money** — it is a LARP game
mechanic with no bank account, payment, or accounting semantics. It MUST NOT be
mapped to Cospend or any payments/expense leaf. This is a documented ADR-022
non-target: there is no OR abstraction whose domain it belongs to, so it stays
as plain Larpinq fields handled by the stat/domain logic.

## Consequences

- Smaller app, cross-app consistency, uniform audit/RBAC/retention on
  OR-backed data (per ADR-022 consequences).
- New leaf migrations follow this ADR; any future parallel mechanism requires an
  explicit ADR-022 exception ADR in this folder.

## Related

- **hydra ADR-022** — Apps Consume OpenRegister Abstractions.
- **hydra ADR-019** — Integration Registry Pattern (the leaf mechanism).
- **`openspec/specs/pdf-export/`** — the existing DocuDesk delegation precedent.
