# Dashboard

## Overview

The dashboard is the landing page of LarpingApp, serving as the entry point when users navigate to the app. It provides KPI cards, recent entity lists, and a skill usage chart.

## Features

- **KPI cards**: Characters, Events, Items, Players counts
- **Recent Characters**: Top 5 most recent characters with click-to-navigate
- **Recent Events**: Top 5 most recent events with click-to-navigate
- **Skill Usage Chart**: Donut chart showing skill distribution across characters
- **Quick-create buttons**: New Character, New Item, New Condition
- **Refresh button**: Reload all dashboard data

## Navigation

Dashboard is the default route (`/`) and has a ViewDashboard icon in the navigation.

## Screenshot

![Dashboard](/screenshots/dashboard.png)

## Technical Details

- Controller: `DashboardController.page()` serves TemplateResponse
- View: `DashboardIndex.vue` with `CnDashboardPage` layout
- Widget system: Grid-based layout with drag-and-drop support
- Annotations: `@NoAdminRequired`, `@NoCSRFRequired`
