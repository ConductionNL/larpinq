# Capability: dashboard-widget-icons

## ADDED Requirements

### Requirement: KPI widget icon set (REQ-DWI-001)
The system SHALL resolve a KPI widget's leading icon from its configured `iconName` against a fixed, closed set of supported icon components, and MUST render no icon when the configured name is absent or not in the supported set. The supported KPI-widget icon names MUST be exactly `AccountGroup`, `AccountMultiple`, `CalendarStar`, and `Sword`.

#### Scenario: Configured icon name resolves to a supported icon
- **GIVEN** a KPI widget configured with `iconName: 'Sword'`
- **WHEN** the widget resolves its icon component
- **THEN** the `Sword` icon component MUST be returned

#### Scenario: Unknown or missing icon name renders no icon
- **GIVEN** a KPI widget configured with an `iconName` outside the supported set (or no `iconName`)
- **WHEN** the widget resolves its icon component
- **THEN** the result MUST be null and no icon MUST be rendered

### Requirement: Recent-items widget icon set (REQ-DWI-002)
The system SHALL resolve a recent-items widget's leading icon from its configured `iconName` against a fixed, closed set of supported icon components, and MUST render no icon when the configured name is absent or not in the supported set. The supported recent-items-widget icon names MUST be exactly `AccountGroup` and `CalendarStar`.

#### Scenario: Configured icon name resolves to a supported icon
- **GIVEN** a recent-items widget configured with `iconName: 'CalendarStar'`
- **WHEN** the widget resolves its icon component
- **THEN** the `CalendarStar` icon component MUST be returned

#### Scenario: Unknown or missing icon name renders no icon
- **GIVEN** a recent-items widget configured with an `iconName` outside the supported set (or no `iconName`)
- **WHEN** the widget resolves its icon component
- **THEN** the result MUST be null and no icon MUST be rendered
