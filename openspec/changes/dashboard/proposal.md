# Dashboard Specification

## Problem
The dashboard is the landing page of the LarpingApp, serving as the entry point when users navigate to the app. Currently it provides a basic welcome view. The dashboard has infrastructure in place for future analytics features using ApexCharts (which is already a project dependency). The `DashboardController` serves the main app template (a Vue SPA entry point), while the Vue `DashboardIndex.vue` component renders the actual dashboard content. The navigation sidebar provides quick access to all entity views and a quick-create button for characters.
**Key source files:**
- `lib/Controller/DashboardController.php` -- Serves the main app template
- `src/views/dashboard/DashboardIndex.vue` -- Dashboard view component
- `src/navigation/MainMenu.vue` -- Dashboard navigation item (Finance icon)
- `src/store/modules/navigation.js` -- Navigation state management

## Proposed Solution
Implement Dashboard Specification following the detailed specification. Key requirements include:
- Requirement: Dashboard Page Rendering
- Requirement: Dashboard Navigation
- Requirement: Dashboard Content
- Requirement: Dashboard CSS Infrastructure
- Requirement: Navigation Quick Actions

## Scope
This change covers all requirements defined in the dashboard specification.

## Success Criteria
- User navigates to the app
- Non-admin user can access the dashboard
- App navigation entry point
- Dashboard is default view
- Navigate to dashboard from another view
