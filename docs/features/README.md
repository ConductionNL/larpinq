# Larpinq — Features

Larpinq is a worldbuilding and LARP (Live Action Role-Playing) campaign management application for Nextcloud. It provides a complete rule engine for managing characters, players, skills, items, conditions, and events, with a stat calculation engine that applies effects in a deterministic order. Data is stored in OpenRegister (using `_register.json` auto-import) and characters can be exported as PDF character sheets via the DocuDesk integration.

Larpinq has no direct GEMMA component mapping — it is a domain-specific application for LARP organizations and worldbuilding communities.

## Standards Compliance

Larpinq is a niche domain application with no applicable government or interoperability standards. It inherits platform-level compliance from Nextcloud and OpenRegister.

| Standard | Status | Description |
|----------|--------|-------------|
| WCAG 2.1 AA | Via platform | Accessibility via Nextcloud and NL Design app |
| GDPR / AVG | Via platform | Data subject rights via OpenRegister |
| NL Design System | Via platform | Government theming via nldesign app |
| Schema.org | Gedeeltelijk | Character → schema:Person; Ability → schema:PropertyValue; Skill → schema:Action |

## Features

| Feature | Description | Docs |
|---------|-------------|------|
| [Character Management](./character-management.md) | Full CRUD for characters (PCs, NPCs); stat calculation engine; currency system (gold/silver/copper); approval workflow | [character-management.md](./character-management.md) |
| [Character Stats](./character-stats.md) | The Stats tab on a character: each ability with its base, the modifiers that moved it and where they came from, and XP earned, spent and left | [character-stats.md](./character-stats.md) |
| [Import and export](./import-and-export.md) | Export any list to CSV or Excel, import characters and players, and move a whole campaign as one workbook | [import-and-export.md](./import-and-export.md) |
| [Lore pages](./lore-pages.md) | Lore per world that players read once it is revealed, with game master secrets kept hidden | [lore-pages.md](./lore-pages.md) |
| [Copy a world](./copy-world.md) | Start a new world from a copy of an existing world's rules, for game masters | [copy-world.md](./copy-world.md) |
| [Extra character fields](./character-custom-fields.md) | Game masters add their own fields to character sheets per world; players fill in the ones meant for them | [character-custom-fields.md](./character-custom-fields.md) |
| [Plots and writing](./plots-and-writing.md) | Plots that run through several characters with private and player texts, and the writing progress of plots and characters | [plots-and-writing.md](./plots-and-writing.md) |
| [Factions, groups and relationships](./factions-and-relationships.md) | Factions run by game masters, groups run by players with a leader who invites and removes members, and relationships between characters | [factions-and-relationships.md](./factions-and-relationships.md) |
| [Character builds](./character-builds.md) | Alternative skill sets for a character, checked against the rules and the XP without changing the sheet, and applied by a game master | [character-builds.md](./character-builds.md) |
| [RPG System / Game Mechanics](./rpg-system.md) | Skills (with prerequisites), Items, Conditions, Effects, Abilities — interconnected rule engine | [rpg-system.md](./rpg-system.md) |
| [Events & Players](./events-players.md) | Event management with date ranges, locations, and effect application to participating characters; player profiles | [events-players.md](./events-players.md) |
| [PDF Export](./pdf-export.md) | Character sheet PDF export via DocuDesk integration; Twig templates scoped to Larpinq | [pdf-export.md](./pdf-export.md) |
| [Admin Settings](./admin-settings.md) | Per-entity data source config: internal DB or OpenRegister register+schema per object type | [admin-settings.md](./admin-settings.md) |
| [Register Config Auto-Import](./register-config-json.md) | `larpinq_register.json` bootstraps all schemas and registers on install via repair step | [register-config-json.md](./register-config-json.md) |
| [Object Service](./object-service.md) | `RegisterObjectFetcher` resolves OpenRegister mappers per object type from IAppConfig | [object-service.md](./object-service.md) |
| [Dashboard](./dashboard.md) | App landing page with ApexCharts infrastructure for campaign analytics | [dashboard.md](./dashboard.md) |
| [Larping Skill Widget](./larping-skill-widget.md) | Nextcloud Dashboard Widget showing skill usage distribution as a pie chart via GraphQL faceting | [larping-skill-widget.md](./larping-skill-widget.md) |
| [Deep Link Registration](./deep-link-registration.md) | Registers URL templates for all entity types in OpenRegister's unified search provider | [deep-link-registration.md](./deep-link-registration.md) |
| [User Settings](./user-settings.md) | Personal preferences: event reminders, character update notifications, default view | [user-settings.md](./user-settings.md) |
| [Search Service](./search-service.md) | Utility methods for MongoDB/MySQL filter construction and query string parsing (legacy inherited code) | [search-service.md](./search-service.md) |

## Architecture

Larpinq uses a pure OpenRegister data model. All entity types (Character, Player, Ability, Skill, Item, Condition, Effect, Event) are stored as register objects, with schemas defined in `larpinq_register.json` (OpenAPI 3.0 format) and auto-imported on app install.

**Key architectural components:**

- **`RegisterObjectFetcher`** — Central data access service resolving OpenRegister mappers per type from `IAppConfig`
- **`CharacterService`** — Stat calculation engine: applies effects from skills, items, conditions, and events in deterministic order
- **DocuDesk integration** — PDF generation delegated to DocuDesk's `PdfService`; gracefully degrades when DocuDesk is not installed

## GEMMA Mapping

| GEMMA Component | Larpinq Role |
|-----------------|-----------------|
| N.v.t. | Domain-specific LARP / worldbuilding campaign management |
