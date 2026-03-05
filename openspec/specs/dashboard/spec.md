---
status: reviewed
---

# Dashboard Specification

## Purpose

The dashboard is the landing page of the LarpingApp, serving as the entry point when users navigate to the app. Currently it provides a basic welcome view. The dashboard has infrastructure in place for future analytics features using ApexCharts (which is already a project dependency). This specification documents the current minimal implementation and the planned analytics features.

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

#### Scenario: User navigates to the app

- GIVEN a logged-in Nextcloud user with access to LarpingApp
- WHEN they navigate to `/apps/larpingapp/`
- THEN the DashboardController MUST return a TemplateResponse
- AND the Vue SPA MUST load and render the dashboard view

---

### Requirement: Dashboard Navigation

The dashboard MUST be accessible as the default view from the app's navigation sidebar.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-010 | Dashboard MUST be the default selected navigation item (`selected: 'dashboard'`) | MUST | Implemented |
| DASH-011 | Dashboard MUST be represented in the sidebar with the Finance icon | MUST | Implemented |
| DASH-012 | Clicking "Dashboard" in the sidebar MUST set navigation state to `dashboard` | MUST | Implemented |

#### Scenario: Dashboard is default view

- GIVEN a user opens LarpingApp for the first time in a session
- THEN the navigation store MUST have `selected` set to `dashboard`
- AND the dashboard view component MUST be rendered

#### Scenario: Navigate to dashboard from another view

- GIVEN the user is viewing the Characters list
- WHEN they click "Dashboard" in the navigation sidebar
- THEN the navigation store MUST set `selected` to `dashboard`
- AND the DashboardIndex component MUST render

---

### Requirement: Dashboard Content

The dashboard MUST display a welcome message as its current content.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-020 | Dashboard MUST display a page header with text "Dashboard" | MUST | Implemented |
| DASH-021 | Dashboard MUST display a welcome message | MUST | Implemented |
| DASH-022 | Dashboard MUST use NcAppContent as its container component | MUST | Implemented |

#### Scenario: View dashboard content

- GIVEN the user has navigated to the dashboard
- THEN the page MUST show an `<h2>` element with text "Dashboard"
- AND the page MUST show a welcome message
- AND the content MUST be wrapped in an `NcAppContent` component

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

#### Scenario: Dashboard layout adapts to viewport

- GIVEN the dashboard page is loaded
- WHEN the viewport is wider than 1590px
- THEN the KPI card grid MUST display 3 columns
- WHEN the viewport is between 1220px and 1590px
- THEN the KPI card grid MUST display 2 columns
- WHEN the viewport is between 1024px and 1220px
- THEN the KPI card grid MUST display 1 column (collapses back from 2)
- WHEN the viewport is between 880px and 1024px
- THEN the KPI card grid MUST display 2 columns
- WHEN the viewport is narrower than 880px
- THEN the KPI card grid MUST display 1 column

#### Scenario: Dashboard respects Nextcloud theme

- GIVEN the user has set Nextcloud to dark mode
- WHEN they view the dashboard
- THEN card backgrounds MUST use `rgba(255, 255, 255, 0.1)` for visibility
- GIVEN the user has set Nextcloud to light mode
- WHEN they view the dashboard
- THEN card backgrounds MUST use `rgba(0, 0, 0, 0.07)` for visibility

---

### Requirement: Navigation Quick Actions

The navigation sidebar MUST provide a quick-create button for new characters.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-040 | Navigation MUST include an NcAppNavigationNew button labeled "Karakter toevoegen" | MUST | Implemented |
| DASH-041 | Clicking the quick-create button MUST clear the current character item | MUST | Implemented |
| DASH-042 | Clicking the quick-create button MUST open the editCharacter modal | MUST | Implemented |

#### Scenario: Quick-create a character from any view

- GIVEN the user is on any page within LarpingApp
- WHEN they click the "Karakter toevoegen" button at the top of the navigation
- THEN the character store MUST clear the active character (set to null)
- AND the navigation store MUST set the modal to `editCharacter`
- AND the character creation modal MUST open

---

### Requirement: Bootstrap File

The application's bootstrap infrastructure.

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-050 | There is NO `src/bootstrap.js` or `src/bootstrap.ts` file in the project -- the bootstrap file referenced in some Nextcloud app conventions does not exist | SHOULD | Implemented |
| DASH-051 | The `Application` class (`lib/AppInfo/Application.php`) implements `IBootstrap` but has empty `register()` and `boot()` methods -- no services, listeners, or middleware are registered during app bootstrap | SHOULD | Implemented |
| DASH-052 | App initialization happens entirely through Nextcloud's auto-wiring and the Vue SPA entry point, not through explicit bootstrap registration | SHOULD | Implemented |

#### Scenario: App bootstrap

- GIVEN the LarpingApp is installed and enabled
- WHEN Nextcloud loads the app
- THEN `Application::register()` is called but performs no operations
- AND `Application::boot()` is called but performs no operations
- AND all services are resolved via Nextcloud's auto-wiring DI container

---

### Planned: Analytics Dashboard

Future enhancement to add analytics widgets using the ApexCharts library (already included as a dependency).

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| DASH-060 | Dashboard SHOULD display KPI cards with counts: total characters, total players, total events, total skills | SHOULD | Planned |
| DASH-061 | Dashboard SHOULD display a chart showing character type distribution (player vs NPC vs other) | SHOULD | Planned |
| DASH-062 | Dashboard SHOULD display a chart showing skill popularity across characters | SHOULD | Planned |
| DASH-063 | Dashboard SHOULD display a timeline chart of recent events | SHOULD | Planned |
| DASH-064 | Dashboard SHOULD display recent activity (latest object changes) | SHOULD | Planned |

#### Scenario: View character distribution chart (planned)

- GIVEN 15 player characters, 8 NPCs, and 2 "other" characters exist
- WHEN the user views the dashboard
- THEN a pie/donut chart MUST display the distribution: 60% player, 32% NPC, 8% other

#### Scenario: View KPI cards (planned)

- GIVEN 25 characters, 12 players, 5 events, and 30 skills exist
- WHEN the user views the dashboard
- THEN KPI cards MUST display: Characters 25, Players 12, Events 5, Skills 30
