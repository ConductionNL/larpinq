# Larping Skill Widget

## Problem
Provide a LarpingApp-specific dashboard widget that visualizes skill usage distribution across characters using data from OpenRegister's GraphQL faceting API. This widget is part of LarpingApp's dashboard experience, following the cross-app dashboard patterns defined in the `built-in-dashboards` spec and using the `CnDashboardPage` shared component from `@conduction/nextcloud-vue`.

## Proposed Solution
Implement Larping Skill Widget following the detailed specification. Key requirements include:
- Requirement: The dashboard MUST display a skill usage pie chart
- Requirement: The widget MUST fetch data via a single GraphQL faceting query
- Requirement: The widget MUST aggregate skill counts from facet buckets
- Requirement: The widget MUST integrate with the CnDashboardPage layout system
- Requirement: The widget MUST check OpenRegister configuration before querying

## Scope
This change covers all requirements defined in the larping-skill-widget specification.

## Success Criteria
- Skill usage chart with data
- Skill usage chart with many skills shows top 10
- Skill usage chart with no data
- Chart respects Nextcloud theme
- Chart respects system color scheme when no explicit theme
