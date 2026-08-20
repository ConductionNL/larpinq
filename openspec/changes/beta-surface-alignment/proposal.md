---
kind: docs
---

# Proposal: Beta Cross-Surface Alignment (LarpingApp)

## Problem

LarpingApp's four public-facing surfaces — `appinfo/info.xml`, `src/manifest.json`, the conduction.nl product page, and `docs/` — disagreed with each other and, in the product page's case, with the shipped code:

1. **info.xml** had a single unlocalized `<summary>` ("Manage your live roleplaying setting"), an unlocalized `<name>`/`<description>`, an `agpl` `<licence>` (the actual `LICENSE` file is EUPL-1.2), a stale `codeberg.org/Conduction/LarpingNextApp` website/bugs/repository (the real remote is `codeberg.org/Conduction/larpingapp`), and no `<dependency><app>openregister</app>` entry even though the app hard-requires OpenRegister (repair step `InitializeRegister`, `manifest.json` declares `"dependencies": ["openregister"]`, and the description text itself says "Requires OpenRegister").
2. **manifest.json** nav/menu labels (Characters, Players, Abilities, Skills, Conditions, Effects, Items, Events, XP Awards, Settings) were already accurate against `lib/Controller/*` and `docs/FEATURES.md` — no code drift here, but the product page did not use this vocabulary.
3. **Product page** (`src/pages/apps/larpingapp.mdx` + NL translation) described a different, non-existent app: "Scenes", "NPCs", "factions", an "audit log per scene", a Discord bot, "Discord and Hilma sync" via OpenConnector, and Talk-channel-per-scene chat. None of these exist in the codebase — there is no Scene, Faction, or standalone NPC entity/schema/controller, and there is no Discord/Talk/Hilma integration anywhere in `lib/` or `src/`. The English and Dutch pages had also drifted structurally: the Dutch page used the older simple template (no `RotatingCards`/`WidgetShelf`/`Showcase`) while the English page had been upgraded, and its `<DetailHero>` `secondaryCta` pointed at `docs.conduction.nl/larpingapp` instead of the real `larpingapp.conduction.nl`.
4. **docs/** (`larpingapp.conduction.nl`) already carried the accurate canonical feature list (`docs/FEATURES.md`, `docs/features/*.md`, `docs/Schema/*.json` match the manifest and controllers 1:1), but `docs/readme.md` (the docs landing/about page) was placeholder junk ("asdasdas / a / asdasd") — never written.

## Canonical Feature List (verified against `lib/Controller/*`, `src/manifest.json`, `docs/FEATURES.md`, `docs/Schema/*.json` at commit `1aee7f1`)

1. **Settings (world/campaign) management** — optional scoping of characters/events/mechanics under a setting; archived (not deleted) when a campaign ends.
2. **Character management** — character sheets (stats, background story, portrait via the `photos` linkedType), a GM background-approval workflow, computed XP/gold stat rails.
3. **Players** — the real person behind a character, linked to a Nextcloud contact (`contacts` linkedType); thin schema (name + notes).
4. **Game mechanics as typed records** — abilities (numeric stats), skills (XP-purchased, prerequisite-gated), items, conditions, effects (the atomic stat modifier) — each with a detail page showing its place in the dependency graph.
5. **Dynamic stat-calculation engine** — `CharacterService` applies skill/item/condition/event effects to ability scores deterministically, with a full audit trail (old value → new value per effect) and server-enforced prerequisite/floor checks at the OpenRegister write boundary (overridable by a GM with a reason).
6. **Events** — participant roster, Nextcloud Calendar scheduling (`calendar` linkedType), location (`maps`) and sign-up (`forms`) as sidebar tabs, event pack via Files.
7. **XP Awards** — an append-only, audited ledger of per-event, per-character experience grants (amount, reason, awardedBy, awardedAt).
8. **PDF character sheet export** — via DocuDesk's `PdfService`/`TemplateService`, graceful 424 degradation when DocuDesk is absent.
9. **GM run-sheet / cast list export** — per-event PDF cast list (roster, stats, conditions, approval state), GM-group restricted, also via DocuDesk.
10. **OpenRegister-backed data** — every entity above is a typed OpenRegister object with audit trail and versioning.
11. **Group-restricted access** — app visibility gated to the `larpers` Nextcloud group; GM-only endpoints additionally require the `admin` group.
12. **Guided setup + walkthrough tour** — `manifest.json` `setup`/`walkthrough` blocks provision the register/schemas and walk a new GM through creating a first character and event.

**Explicitly not real** (removed from the product page, never existed in code): Scene/scenes, Faction/factions, a standalone NPC entity or schema, Discord integration, "Hilma sync", Nextcloud Talk integration.

## Reconciliation

### info.xml (`appinfo/info.xml`)
- Added `lang="en"`/`lang="nl"` variants for `<name>` and `<summary>`; real Dutch summary: "Karakter-, event- en regelbeheer voor live-action roleplay (LARP)-groepen" (not a machine copy of the English).
- Rewrote `<description>` (en + nl) to the canonical feature list above instead of the old 5-bullet emoji list; kept the OpenRegister requirement callout.
- Fixed `<licence>` from `agpl` to `EUPL-1.2` to match the actual `LICENSE` file.
- Fixed `<website>`/`<bugs>`/`<repository>` from the stale `codeberg.org/Conduction/LarpingNextApp` to the real remote `codeberg.org/Conduction/larpingapp` (verified via `git remote -v`).
- Added `<app>openregister</app>` to `<dependencies>` — the app cannot function without OpenRegister (repair step + manifest.json already declared this dependency; info.xml did not).

### manifest.json / manifest.d
- No changes required — nav/menu labels and page titles already match the canonical feature list and were used as its source.

### Product page (`conduction-website/src/pages/apps/larpingapp.mdx` + NL translation)
- Rewrote `<DetailHero>` `intro`/tagline to drop "Scenes, NPC stats" and describe the real feature set (abilities/skills/items/conditions/effects, XP awards, PDF exports).
- Rewrote the `FeatureList` (6 items) to the canonical feature list: approval-workflow character sheets, the configurable stat engine, setting-scoped campaigns, audited XP awards, PDF sheets/run-sheets, EUPL-1.2 licensing. Removed "Rules you write yourself" (implied a full custom rules DSL that doesn't exist) and "Scenes, NPCs, factions, in registers."
- Rewrote `RotatingCards` (Characters / Rules / Players instead of Characters / Scenes / Players).
- Rewrote `WidgetShelf` to the four widgets the dashboard page actually ships (`kpi-characters`, `kpi-events`, `kpi-items`, `kpi-players`, `recent-characters`, `recent-events`, `skill-usage`) instead of invented "My character" (faction allegiance), "Upcoming sessions" (faction filter), and "Recent scenes" widgets.
- Rewrote `Showcase` from "Calendar and Talk" / "Files and Photos" / "Discord and Hilma sync" to "Calendar, maps and sign-up" / "Files and Photos" / "Contacts and OpenRegister" — the three integrations that are real (`calendar`, `maps`, `forms`, `files`, `photos`, `contacts` linkedTypes).
- Fixed `PairCard` copy under "Pairs well with" to drop NPC/faction language.
- Brought the Dutch page up to structural and content parity with the English page (previously a stale, simpler template with the same fabricated Scene/Faction/Discord content and a dead `docs.conduction.nl/larpingapp` doc link).

### Docs (`larpingapp/docs/`)
- `docs/FEATURES.md`, `docs/features/*.md`, `docs/Schema/*.json`, `docs/README.md` were already accurate — used as the canonical source, no edits needed.
- Replaced placeholder junk in `docs/readme.md` ("asdasdas / a / asdasd") with a real one-paragraph "About Larping" summary pointing to `features.md`.

## Claims Verified vs Removed

| Claim | Verified? | Action |
|---|---|---|
| "Requires OpenRegister" | Yes — `Repair\InitializeRegister`, `manifest.json` `dependencies: ["openregister"]` | Kept; added missing `<dependency><app>` in info.xml |
| PDF character sheet export via DocuDesk | Yes — `lib/Service/DocuDeskPdfRenderer.php`, `CharactersController.downloadPdf()`, `docs/features/pdf-export.md` | Kept |
| GM run-sheet / cast list PDF | Yes — `EventsController` PDF route, `docs/FEATURES.md` | Kept |
| Calendar/Maps/Forms integration on events | Yes — `manifest.json` Event page slots (`calendar-leaf`, `maps-leaf`, `forms-leaf`) | Kept |
| Contacts integration on players | Yes — `manifest.json` Player page (`contacts-leaf`), `player.linkedType: contacts` | Kept |
| Photos integration on characters | Yes — `manifest.json` Character page (`photos-leaf`) | Kept |
| Audited XP awards with server-enforced prerequisites | Yes — `docs/features.md` "XP Tracking" section, `xpAward` schema, requirement-report endpoint | Kept |
| EUPL-1.2 license | Yes — `LICENSE` file header | Fixed info.xml `<licence>` (was `agpl`) |
| Scenes / Factions / NPC entities | **No** — no schema, controller, or manifest page for any of these | Removed from product page (en + nl) |
| Discord integration / "Hilma sync" | **No** — no reference anywhere in `lib/` or `src/` | Removed from product page (en + nl) |
| Nextcloud Talk integration | **No** — not a declared linkedType on any schema, no controller reference | Removed from product page (en + nl) |
| Repository at `codeberg.org/Conduction/LarpingNextApp` | **No** — `git remote -v` shows `codeberg.org/Conduction/larpingapp` | Fixed info.xml `<website>`/`<bugs>`/`<repository>` |

## Icon Status

`img/app.svg` is white-fill (`.cls-1 { fill: #fff; }`), `viewBox="0 0 24 24"` — matches the brand app-icon convention. No change needed. The product page hero uses a separate decorative inline SVG (sword-like glyph), which is the norm for other apps' hero illustrations and is not required to be pixel-identical to `app.svg`.

## Still Misaligned / Needs a Decision

- `docs/architecture.md` still lists "planned i18n / multi-tenancy wiring" as a roadmap item — left untouched (roadmap language, not a false claim about the current build).
- `docs/features.json` (the coverage-tracking manifest) mixes real feature slugs (character-management, events-players, dashboard, etc.) with process/testing metadata (`@e2e exclude`, "SPA mount fixed in #202"); this file is machine-consumed by the gate-19/coverage tooling, not human-facing product copy, so it was left as-is.
- No version bump was made — `info.xml` `<version>0.1.30</version>` is unchanged and remains the single source of truth; the product/docs pages already only ever showed a rounded `v0.1`.
