# LarpingApp — LARP Management for Nextcloud

## Overview

LarpingApp is a Nextcloud app for managing live-action role-playing (LARP) game settings. It provides game masters and players with tools to manage characters, skills, items, conditions, effects, events, players, abilities, and templates. The app features a stat calculation engine that automatically computes character ability scores based on assigned skills, items, conditions, and events, plus PDF character sheet export via customizable Twig templates.

## Architecture

- **Type**: Nextcloud App (PHP backend + Vue 2 frontend)
- **Data layer**: Dual-mode -- native Nextcloud DB (Entity/Mapper) OR OpenRegister (JSON object storage with schema validation), configurable per object type
- **Pattern**: Generic ObjectService -- a single `ObjectService` dispatches CRUD operations to the correct mapper (internal or OpenRegister) based on per-type configuration
- **License**: EUPL-1.2

## Standards

| Layer | Standard | Purpose |
|-------|----------|---------|
| **Schema** | CommonGateway Entity schema | JSON schema definitions for all entities |
| **Validation** | Zod (frontend) | Client-side entity validation |
| **PDF** | mPDF + Twig | Character sheet PDF generation |
| **API** | Nextcloud App Framework | RESTful JSON API via controllers |
| **Nextcloud** | IAppConfig, Entity/Mapper | App settings and data persistence |

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.1+, Nextcloud App Framework |
| Frontend | Vue 2.7, Pinia, TypeScript entities, @nextcloud/vue 8 |
| Data | Native Nextcloud DB (Entity/Mapper) or OpenRegister |
| PDF | mPDF 8.2, Twig 3.18 |
| Charts | ApexCharts + vue-apexcharts |
| UI Components | Bootstrap Vue, vue-material-design-icons |
| Validation | Zod 3.x |
| Build | Webpack 5, @nextcloud/webpack-vue-config |
| Testing | Jest, @vue/test-utils, @pinia/testing |

## Features

### Implemented

| Feature | Description | Status |
|---------|-------------|--------|
| Character Management | CRUD for player characters and NPCs with name, description, background, faith, notes, currency (gold/silver/copper), approval status | Done |
| Stat Calculation Engine | Automatic ability score computation based on effects from skills, items, conditions, and events; audit trail per ability | Done |
| PDF Character Sheet Export | Generate PDF character sheets using mPDF with customizable Twig templates | Done |
| Skill Management | CRUD for skills with effects, prerequisites (required skills, stats, conditions, effects), and experience cost | Done |
| Item Management | CRUD for items (unique/non-unique) with effects, character ownership tracking | Done |
| Condition Management | CRUD for conditions (positive/negative) with effects, character tracking | Done |
| Effect System | CRUD for effects with modifier, modification type (positive/negative), cumulative/non-cumulative, targeting one or more abilities | Done |
| Ability/Stat Management | CRUD for abilities (stats) with base values; used as targets for effects | Done |
| Event Management | CRUD for events with date range, location, player participation, post-event effects | Done |
| Player Management | CRUD for player profiles | Done |
| Template Management | CRUD for Twig-based PDF templates | Done |
| Admin Settings | Per-object-type data source configuration (internal vs OpenRegister), register/schema selection | Done |
| Audit Trail | Per-object audit trail viewing via OpenRegister integration | Done |
| Object Relations | View relations and uses for any object via OpenRegister | Done |
| Object Locking | Lock/unlock objects to prevent concurrent modification | Done |
| Object Revert | Revert objects to a previous state via audit trail | Done |
| Search | Debounced text search across all object types with faceted results | Done |
| Dashboard | Basic welcome/landing page | Done |

### Planned

| Feature | Description | Priority |
|---------|-------------|----------|
| Dashboard Analytics | Charts and KPIs (character counts, event stats, skill distribution) using ApexCharts | SHOULD |
| Character Approval Workflow | Game master approval flow with status transitions | SHOULD |
| XP Tracking | Experience point calculation and spending per character | SHOULD |
| Event Registration | Player sign-up and character assignment for events | SHOULD |
| Bulk Operations | Bulk assign skills/items/conditions to multiple characters | COULD |

### Shared with OpenRegister

These features are provided at the OpenRegister level when OpenRegister is configured as the data source:

| Feature | Description |
|---------|-------------|
| Audit Trail | Comprehensive change logging with revert capability |
| Object Locking | Pessimistic locking to prevent concurrent edits |
| Schema Validation | JSON schema validation on all object writes |
| Relations & Uses | Cross-object relationship discovery |
| Faceted Search | Aggregated facets for search results |

## Data Model

| Object | Description | Key Properties |
|--------|-------------|----------------|
| Character | Player or NPC within a setting | name, ocName, description, background, faith, type (player/npc/other), approved (no/approved), skills[], items[], conditions[], events[], stats (computed), gold/silver/copper |
| Ability (Stat) | Numeric value on which characters score (XP, mana, DEX, etc.) | name, description, base (starting value) |
| Skill | Learnable action with effects and prerequisites | name, description, effect (text), effects[], requiredSkills[], requiredStats[], requiredConditions[], requiredEffects[], requiredScore |
| Item | Object a character owns with effects | name, description, effect (text), effects[], unique (boolean), characters[] |
| Condition | Positive/negative status affecting a character | name, description, effect (text), effects[], unique (boolean), characters[] |
| Effect | Numeric modifier to one or more abilities | name, description, modifier (int), modification (positive/negative), cumulative (cumulative/non-cumulative), abilities[] |
| Event | Game gathering with date, location, participants | name, description, startDate, endDate, location, players[], effects[] |
| Player | Real-world player profile | name, description |
| Template | Twig HTML template for PDF generation | name, description, template (HTML string) |
| Setting | App configuration key-value pair | name, value |

## Key Directories

```
larpingapp/
├── appinfo/              # App manifest (info.xml) and routes
├── lib/                  # PHP backend
│   ├── Controller/       # ObjectsController, CharactersController, SettingsController, DashboardController
│   ├── Db/               # Entity classes and Mappers (10 entities)
│   ├── Service/          # CharacterService (stat calc + PDF), ObjectService (generic CRUD), SearchService
│   ├── Settings/         # Admin settings section (IIconSection)
│   ├── Sections/         # Admin section registration
│   └── Migration/        # Database migrations
├── src/                  # Vue frontend source
│   ├── entities/         # TypeScript entity classes with Zod validation
│   ├── store/modules/    # Pinia stores per entity type
│   ├── views/            # Page components (dashboard, characters, skills, items, etc.)
│   ├── modals/           # CRUD modals per entity type
│   ├── sidebars/         # Sidebar panels
│   └── navigation/       # MainMenu with navigation items
├── docs/                 # Documentation
│   └── Schema/           # JSON schema definitions per entity
├── templates/            # PHP page templates + Twig PDF templates
├── openspec/             # OpenSpec specifications
└── css/                  # Stylesheets
```

## Navigation Structure

The app's left sidebar organizes navigation into two sections:

**Main Navigation:**
- Dashboard (Finance icon)
- Karakters / Characters (BriefcaseAccount icon)
- Spelers / Players (AccountGroup icon)
- Items (Sword icon)
- Events (Calendar icon)
- Conditions (EmoticonSick icon)
- Zoeken / Search (Magnify icon)

**Settings Area (bottom):**
- Abilities (ShieldSword icon)
- Skills (SwordCross icon)
- Effects (MagicStaff icon)
- Templates (FileDocument icon)

## API Endpoints

All object CRUD goes through a generic pattern:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/objects/{objectType}` | List objects (with search, pagination, facets) |
| POST | `/api/objects/{objectType}` | Create object |
| GET | `/api/objects/{objectType}/{id}` | Get single object |
| PUT | `/api/objects/{objectType}/{id}` | Update object |
| DELETE | `/api/objects/{objectType}/{id}` | Delete object |
| POST | `/api/objects/{objectType}/{id}/lock` | Lock object |
| POST | `/api/objects/{objectType}/{id}/unlock` | Unlock object |
| POST | `/api/objects/{objectType}/{id}/revert` | Revert to previous state |
| GET | `/api/objects/{objectType}/{id}/audit` | Get audit trail |
| GET | `/api/objects/{objectType}/{id}/relations` | Get relations |
| GET | `/api/objects/{objectType}/{id}/uses` | Get uses |
| GET | `/characters/{id}/download/{template}` | Download character PDF |
| GET | `/api/settings` | Get app settings |
| POST | `/api/settings` | Update app settings |

## Development

- **Local URL**: http://localhost:8080/apps/larpingapp/
- **Requires**: Nextcloud 28-30, PHP 8.1+
- **Optional**: OpenRegister app for advanced data storage features
- **Docker**: Part of openregister/docker-compose.yml
- **Build**: `npm run dev` (development), `npm run build` (production)
- **Tests**: `npm run test` (Jest), `composer test:unit` (PHPUnit)
