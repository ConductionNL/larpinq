---
kind: config
depends_on: []
---

# Proposal: worlds-lore-pages

## Summary

Game masters write pages about their world: the city of Aldmoor, the Ash
Circle, the war of the two queens. Players read what they are meant to know,
and some pages open only on the day the story reaches them. Larpinq has none
of this; lore lives in shared documents outside the app. This change adds lore
pages per world with a category, a visibility (game masters, or players too)
and an optional reveal moment, and a Lore section where players browse the
pages they may read.

## Motivation

Three worlds rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). Worlds is a core
area; the OpenSpec pass of 2026-09-27 decided `build` for all three.

**`wld-lore-wiki`**, "Write lore pages for places, factions and history that
players can browse." Larpinq rates it no. Matrix evidence: "grep -rniE
'lore|wiki' lib src openspec/specs openspec/changes: only a scenario name
('Relic Lore') and free-text description fields, no lore/wiki page type,
entity or controller." Two competitors rate it yes, two partial:

- MyLARP (yes): "'Lore & Storytelling: record and keep a game's story as well as player character biographies in one place.' https://mylarp.com"
- Kanka (yes): "routes/campaigns/entities.php:314-342 CRUD for locations, organisations, journals, notes, races, families, timelines with rich-text entries and mentions" (source read at tag 3.15).
- LarpManager (partial): "larpmanager/models/writing.py:848-880 Handout texts with public external link (larpmanager/urls/event.py:342-344), faction pages ...; no free lore page type for places or history".
- LARP Portal (partial): "Character module lets players record 'places... meaningful to their character' but there is no GM-authored world/faction/history wiki documented. https://larportal.com"

**`wld-share-with-players`**, "Publish selected world information to players
while keeping GM secrets hidden." Larpinq rates it no. Matrix evidence:
"grep -rn 'x-property-rbac' lib/: no hits anywhere in the register. No
per-field, per-role visibility exists for any schema". Three competitors rate
it yes:

- LarpManager: "larpmanager/models/writing.py:113-126 Writing.teaser visible to all vs text visible only to the assigned player, larpmanager/models/writing.py:590-595 secret factions, hide flag".
- LARP Portal: "'Campaign Module...shares game rules, events, and information with players, while staff uses expanded features to configure rules and manage logistics' implying a staff-only tier of information. https://larportal.com"
- Kanka: "app/Enums/Visibility.php (all/admin/self/members) on posts, abilities, inventory; per-entity privacy routes/campaigns/entities.php:229-230 and role permissions 414-415; public campaigns routes/campaigns/campaign.php:215-216".

**`wld-scheduled-reveal`**, "Schedule when a piece of world or story content
becomes visible to players." Larpinq rates it no. Matrix evidence: "no schema
carries a publish-from or visible-from date (lib/Settings/larpinq_register.json:39-960);
visibility is the GM notes split slNotesPublic/slNotesPrivate (:141-154), not a
schedule". Demand: a Kanka feature request, https://app.kanka.io/roadmap/202.
No competitor rates it yes; LarpManager is partial ("per-event show_<element>
toggles ... switched by hand; no date-based release of content") and Kanka no
("visibility is immediate").

One page type carries all three: the page, who may read it, and from when.

## Affected Projects

- [ ] Project: `larpinq`: a lore page schema with read rules, reading and managing pages, and an ADR that records why lore pages are register objects.

## Scope

### In Scope

- `lorePage` per world: title, markdown body, category (place, faction, history, rules, other), visibility (game masters, players), reveal moment, and an optional parent page for a tree.
- Players read a page only when it is for players and its reveal moment has passed; game masters read every page.
- A Lore index per world and a read view with the page tree in a sidebar.
- Game masters create and edit pages through the standard create and edit form with a markdown editor.
- `openspec/architecture/adr-003-lore-pages-are-register-objects.md`, the ADR-022 exception that larpinq ADR-001 asks for.

### Out of Scope

- In-text mentions that link characters and pages automatically.
- A notification when a page is revealed (the notification rule can follow once the reveal is proven).
- Per-player visibility (a page for one character's owner). Plot parts in `characters-plot-threads-and-writing` carry per-character texts.

## Approach

Declarative: a register fragment adds the schema and its read rules (visibility
and reveal time evaluated by OpenRegister with `$now`); a manifest fragment adds
a Lore index and a `type: "wiki"` read page. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/worlds-lore-pages.json` (new).
- `src/manifest.d/worlds-lore-pages.json` (new): Lore index, lore read page, menu entry under World.
- `src/manifest.json`: SettingDetail gains a "Lore" object-list (existing page, edited in place).
- `openspec/architecture/adr-003-lore-pages-are-register-objects.md` (new).

## Cross-Project Dependencies

OpenRegister row level security with `$now` (spec `row-field-level-security`),
and `@conduction/nextcloud-vue` `type: "wiki"` pages (`CnWikiPage`) and
`CnMarkdownEditor`.

## Risks

### Risk 1: A page shows before its moment
**Severity:** High. **Mitigation:** the reveal is a read rule evaluated in the database query, not a filter in the page. Newman asserts a player gets nothing before the moment on the index, the detail and search.

### Risk 2: Integrate-don't-build
**Severity:** Medium. **Mitigation:** larpinq ADR-001 prefers leaves (the archived setting-management change named an xwiki leaf for lore). Collectives and XWiki grant access per collective or space and have no reveal moment, so they cannot carry these rows. The change writes ADR-003 to record the exception, as ADR-001 requires.

## Rollback Strategy

Remove the fragments and the SettingDetail list. Pages stay as data.

## Open Questions

None.
