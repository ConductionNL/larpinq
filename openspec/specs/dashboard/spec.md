---
status: implemented
---

# Dashboard Specification

## Purpose

The dashboard is the landing page of the LarpingApp, serving as the entry point when users navigate to the app. Currently it provides a basic welcome view. The dashboard has infrastructure in place for future analytics features using ApexCharts (which is already a project dependency). The `DashboardController` serves the main app template (a Vue SPA entry point), while the Vue `DashboardIndex.vue` component renders the actual dashboard content. The navigation sidebar provides quick access to all entity views and a quick-create button for characters.

**Key source files:**
- `lib/Controller/DashboardController.php` -- Serves the main app template
- `src/views/dashboard/DashboardIndex.vue` -- Dashboard view component
- `src/navigation/MainMenu.vue` -- Dashboard navigation item (Finance icon)
- `src/store/modules/navigation.js` -- Navigation state management

## Requirements

---

### Requirement: Dashboard Page Rendering

The system MUST serve the main app page via the DashboardController, which loads the Vue SPA.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-001 | DashboardController MUST serve a TemplateResponse for the root route `/` | MUST | Implemented |
| DASH-002 | The template MUST render the `index` template from the `larpingapp` app | MUST | Implemented |
| DASH-003 | The route MUST be accessible without admin rights (`@NoAdminRequired`) | MUST | Implemented |
| DASH-004 | The route MUST not require CSRF validation (`@NoCSRFRequired`) | MUST | Implemented |
| DASH-005 | The controller MUST inject `IAppConfig` via constructor for future configuration access | MUST | Implemented |
| DASH-006 | The route MUST be defined as `dashboard#page` with URL `/` and verb GET in routes.php | MUST | Implemented |

#### Scenario: User navigates to the app

- GIVEN a logged-in Nextcloud user with access to LarpingApp
- WHEN they navigate to `/apps/larpingapp/`
- THEN the DashboardController MUST return a TemplateResponse
- AND the Vue SPA MUST load and render the dashboard view
- AND the response MUST use the `index` template

#### Scenario: Non-admin user can access the dashboard

- GIVEN a regular (non-admin) Nextcloud user with the app enabled
- WHEN they navigate to `/apps/larpingapp/`
- THEN the dashboard MUST render successfully
- AND no admin check MUST block access

#### Scenario: App navigation entry point

- GIVEN a user clicks "Larping" in the Nextcloud top navigation bar
- WHEN the route resolves
- THEN the DashboardController `page()` method MUST handle the request
- AND the Vue SPA MUST bootstrap from the rendered template

---

### Requirement: Dashboard Navigation

The dashboard MUST be accessible as the default view from the app's navigation sidebar.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-010 | Dashboard MUST be the default selected navigation item (`selected: 'dashboard'`) | MUST | Implemented |
| DASH-011 | Dashboard MUST be represented in the sidebar with the Finance icon | MUST | Implemented |
| DASH-012 | Clicking "Dashboard" in the sidebar MUST set navigation state to `dashboard` | MUST | Implemented |
| DASH-013 | The navigation store MUST maintain `selected` state across view transitions | MUST | Implemented |
| DASH-014 | The sidebar MUST list all entity views: Dashboard, Characters, Events, Players, Items, Conditions, and settings-area items (Abilities, Skills, Effects) | MUST | Implemented |

#### Scenario: Dashboard is default view

- GIVEN a user opens LarpingApp for the first time in a session
- THEN the navigation store MUST have `selected` set to `dashboard`
- AND the dashboard view component MUST be rendered

#### Scenario: Navigate to dashboard from another view

- GIVEN the user is viewing the Characters list
- WHEN they click "Dashboard" in the navigation sidebar
- THEN the navigation store MUST set `selected` to `dashboard`
- AND the DashboardIndex component MUST render

#### Scenario: Navigation state persistence

- GIVEN the user navigates from Dashboard to Characters to Events
- WHEN they click Dashboard again
- THEN `selected` MUST update to `dashboard`
- AND the dashboard view MUST re-render

#### Scenario: Sidebar shows all entity views

- GIVEN the user is on any page within LarpingApp
- THEN the left sidebar MUST display navigation items for:
  - Dashboard (Finance icon)
  - Characters
  - Events
  - Players
  - Items
  - Conditions
  - Settings area: Abilities, Skills, Effects

---

### Requirement: Dashboard Content

The dashboard MUST display a welcome message as its current content.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-020 | Dashboard MUST display a page header with text "Dashboard" | MUST | Implemented |
| DASH-021 | Dashboard MUST display a welcome message | MUST | Implemented |
| DASH-022 | Dashboard MUST use NcAppContent as its container component | MUST | Implemented |
| DASH-023 | Dashboard content MUST be wrapped in `.dashboard-content` CSS class | MUST | Implemented |

#### Scenario: View dashboard content

- GIVEN the user has navigated to the dashboard
- THEN the page MUST show an `<h2>` element with text "Dashboard"
- AND the page MUST show a welcome message
- AND the content MUST be wrapped in an `NcAppContent` component

#### Scenario: Dashboard is minimal but functional

- GIVEN the user views the dashboard
- THEN no data widgets or charts MUST be displayed (current implementation)
- AND only the heading and welcome message MUST be visible

#### Scenario: Dashboard after app install

- GIVEN LarpingApp was just installed and no data exists
- WHEN the admin navigates to the app
- THEN the dashboard MUST display the welcome message
- AND the sidebar MUST be navigable to all entity views

---

### Requirement: Dashboard CSS Infrastructure

The dashboard MUST have CSS infrastructure in place for future analytics widgets including stat cards and chart containers.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-030 | Dashboard MUST define `.dashboard-content` CSS class with centered layout (max-width 1000px) | MUST | Implemented |
| DASH-031 | Dashboard MUST define `.most-searched-terms` grid layout for KPI cards (responsive: 1col default, 2col at 880px, 1col at 1024px, 2col at 1220px, 3col at 1590px) | MUST | Implemented |
| DASH-032 | Dashboard MUST define `.graphs` grid layout for chart containers (2 columns above 1800px, 1 column below 1800px) | MUST | Implemented |
| DASH-033 | Dashboard MUST support both light and dark theme for card backgrounds | MUST | Implemented |
| DASH-034 | Dashboard MUST support both `prefers-color-scheme` media queries (as default) AND Nextcloud's `body[data-theme-light]` / `body[data-theme-dark]` attribute selectors (as overrides) | MUST | Implemented |
| DASH-035 | Card background colors MUST use rgba with appropriate opacity for each theme | MUST | Implemented |

#### Scenario: Dashboard layout adapts to viewport

- GIVEN the dashboard page is loaded
- WHEN the viewport is wider than 1590px
- THEN the KPI card grid MUST display 3 columns
- WHEN the viewport is between 1220px and 1590px
- THEN the KPI card grid MUST display 2 columns
- WHEN the viewport is between 1024px and 1220px
- THEN the KPI card grid MUST display 1 column
- WHEN the viewport is between 880px and 1024px
- THEN the KPI card grid MUST display 2 columns
- WHEN the viewport is narrower than 880px
- THEN the KPI card grid MUST display 1 column

#### Scenario: Dashboard respects Nextcloud dark theme

- GIVEN the user has set Nextcloud to dark mode (`body[data-theme-dark]`)
- WHEN they view the dashboard
- THEN card backgrounds MUST use `rgba(255, 255, 255, 0.1)` for visibility

#### Scenario: Dashboard respects Nextcloud light theme

- GIVEN the user has set Nextcloud to light mode (`body[data-theme-light]`)
- WHEN they view the dashboard
- THEN card backgrounds MUST use `rgba(0, 0, 0, 0.07)` for visibility

#### Scenario: Dashboard respects OS color scheme preference

- GIVEN no Nextcloud theme override is set
- AND the OS prefers dark mode
- WHEN the dashboard renders
- THEN `prefers-color-scheme: dark` media query MUST apply dark card backgrounds

---

### Requirement: Navigation Quick Actions

The navigation sidebar MUST provide a quick-create button for new characters.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-040 | Navigation MUST include an NcAppNavigationNew button labeled "Karakter toevoegen" | MUST | Implemented |
| DASH-041 | Clicking the quick-create button MUST clear the current character item | MUST | Implemented |
| DASH-042 | Clicking the quick-create button MUST open the editCharacter modal | MUST | Implemented |
| DASH-043 | The quick-create button MUST be visible on all views (not just dashboard) | MUST | Implemented |

#### Scenario: Quick-create a character from dashboard

- GIVEN the user is on the dashboard
- WHEN they click the "Karakter toevoegen" button at the top of the navigation
- THEN the character store MUST clear the active character (set to null)
- AND the navigation store MUST set the modal to `editCharacter`
- AND the character creation modal MUST open

#### Scenario: Quick-create from characters view

- GIVEN the user is viewing a character detail page for "Sir Lancelot"
- WHEN they click "Karakter toevoegen" in the sidebar
- THEN the active character MUST be cleared (not "Sir Lancelot")
- AND the creation modal MUST open for a new character

#### Scenario: Quick-create from settings view

- GIVEN the user is viewing the Skills list in the settings area
- WHEN they click "Karakter toevoegen"
- THEN the modal MUST open for creating a new character
- AND the current view MUST remain unchanged until the modal closes

---

### Requirement: Application Bootstrap

The application MUST initialize correctly via Nextcloud's auto-wiring without explicit bootstrap registration.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-050 | The `Application` class MUST implement `IBootstrap` with empty `register()` and `boot()` methods | MUST | Implemented |
| DASH-051 | All services MUST be resolved via Nextcloud's auto-wiring DI container | MUST | Implemented |
| DASH-052 | `Application::APP_ID` MUST be defined as `'larpingapp'` | MUST | Implemented |
| DASH-053 | No explicit service registrations, event listeners, or middleware MUST be present in bootstrap | SHOULD | Implemented |

#### Scenario: App bootstrap

- GIVEN the LarpingApp is installed and enabled
- WHEN Nextcloud loads the app
- THEN `Application::register()` MUST be called but perform no operations
- AND `Application::boot()` MUST be called but perform no operations
- AND all services (CharacterService, RegisterObjectFetcher, SettingsService, etc.) MUST be resolved via auto-wiring

#### Scenario: Service resolution at runtime

- GIVEN the app has bootstrapped with empty register/boot
- WHEN a request hits the DashboardController
- THEN the controller MUST be instantiated by Nextcloud's DI container
- AND `IAppConfig` MUST be injected automatically

#### Scenario: No custom middleware or event listeners

- GIVEN the Application class has empty register() and boot()
- WHEN any request is processed
- THEN no custom middleware MUST intercept the request
- AND no event listeners MUST fire from LarpingApp bootstrap

---

### Requirement: Planned Analytics Dashboard

Future enhancement to add analytics widgets using the ApexCharts library.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-060 | Dashboard SHOULD display KPI cards with counts: total characters, total players, total events, total skills | SHOULD | Planned |
| DASH-061 | Dashboard SHOULD display a chart showing character type distribution (player vs NPC vs other) | SHOULD | Planned |
| DASH-062 | Dashboard SHOULD display a chart showing skill popularity across characters | SHOULD | Planned |
| DASH-063 | Dashboard SHOULD display a timeline chart of recent events | SHOULD | Planned |
| DASH-064 | Dashboard SHOULD display recent activity (latest object changes) | SHOULD | Planned |
| DASH-065 | KPI cards MUST use the `.most-searched-terms` CSS grid already defined | SHOULD | Planned |
| DASH-066 | Charts MUST use the `.graphs` CSS grid already defined | SHOULD | Planned |

#### Scenario: View character distribution chart (planned)

- GIVEN 15 player characters, 8 NPCs, and 2 "other" characters exist
- WHEN the user views the dashboard
- THEN a pie/donut chart MUST display the distribution: 60% player, 32% NPC, 8% other
- AND ApexCharts MUST be used for rendering

#### Scenario: View KPI cards (planned)

- GIVEN 25 characters, 12 players, 5 events, and 30 skills exist
- WHEN the user views the dashboard
- THEN KPI cards MUST display: Characters 25, Players 12, Events 5, Skills 30
- AND the cards MUST use the responsive grid layout

#### Scenario: View skill popularity chart (planned)

- GIVEN skills "Healing" is used by 10 characters, "Fireball" by 8, and "Stealth" by 3
- WHEN the user views the dashboard
- THEN a bar chart MUST show skill usage ranked by frequency

#### Scenario: Empty dashboard with no data (planned)

- GIVEN no entities exist in the system
- WHEN the user views the dashboard
- THEN KPI cards MUST display 0 for all counts
- AND charts MUST display empty state or "No data" indicators

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | Serve the main app template (DashboardController.page()) |

## Dependencies

- **DashboardController**: Serves TemplateResponse for the root route
- **IAppConfig**: Injected for future configuration access
- **Vue SPA**: `main.js` entry point bootstraps the Vue application
- **Pinia navigation store**: Manages sidebar navigation state (`selected`, `modal`)
- **NcAppContent**: Nextcloud Vue component wrapping dashboard content
- **ApexCharts** (future): Already a dependency, planned for analytics widgets
