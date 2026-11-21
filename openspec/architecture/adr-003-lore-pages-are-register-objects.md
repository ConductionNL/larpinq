# ADR-003: Lore pages are register objects, not a wiki leaf

## Status

Accepted

## Date

2026-09-30

## Context

ADR-001 says Larpinq consumes OpenRegister leaves instead of building its own
mechanism, and that any parallel mechanism needs an explicit exception here.
Lore was expected to come from a wiki leaf: OpenRegister ships a
`CollectivesProvider` and an `XwikiProvider`, and the archived
`setting-management` proposal named a future xwiki leaf on `setting`.

The lore rows ask for two things a wiki leaf cannot give:

- `wld-share-with-players`: some pages are for players and some are game master
  secrets, per page. Collectives and XWiki grant access per collective or
  space, not per page.
- `wld-scheduled-reveal`: a page opens to players at a set moment, without a
  game master doing anything. Neither wiki has a reveal moment.

## Decision

A lore page is an OpenRegister object of the schema `lorePage`
(`larping_lore_page`, `lib/Settings/register.d/worlds-lore-pages.json`). Who
may read it is a row rule on that schema: game masters read every page;
members of `larpers` read a page whose `visibility` is `players` and whose
`revealFrom` is at or before `$now`. OpenRegister evaluates the rule in the
database query, so the list, the page, search and exports all obey it.

This is an exception to ADR-001 and hydra ADR-022 for lore only. It still
consumes OpenRegister: storage, row rules, search, export and the audit trail
are OpenRegister's. What Larpinq does not use is a wiki app's editor and
storage.

## Consequences

- Per-page visibility and timed reveal work on every read path, with no
  Larpinq PHP.
- The editor is OpenRegister's markdown field, which is plainer than a wiki's.
- A world's lore is in the same register as its characters and events, so the
  campaign export carries it.
- If a wiki leaf later gains per-page rules and a reveal moment, this ADR is
  the one to revisit.

## Related

- **ADR-001**: integrate, don't build.
- **hydra ADR-022**: apps consume OpenRegister abstractions.
- `openspec/specs/world-lore/spec.md`.
