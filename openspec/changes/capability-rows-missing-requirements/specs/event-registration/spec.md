# event-registration delta

## ADDED Requirements

### Requirement: A player chooses the event's options on the registration (REQ-RTO-007)

An event SHALL offer registration options, such as a meal plan, each with its choices. A player MUST be able to pick a choice per option on their registration, and the registration page MUST show what was picked.

#### Scenario: Anna eats vegetarian

- GIVEN "Winter Court 2026" offers the option "Meal" with the choices vegetarian, meat and vegan
- WHEN Anna picks vegetarian on her registration
- THEN her registration shows "Meal: vegetarian"
- AND the event's count of vegetarian meals goes up by one
