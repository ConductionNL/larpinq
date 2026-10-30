## ADDED Requirements

### Requirement: A new user gets a guided tour (REQ-FTS-010)

The app SHALL offer the guided tour `larpinq:getting-started` on a user's first visit. It SHALL lead the user to the characters and the events, and the user SHALL create a character and an event themselves. The user SHALL be able to skip it at any step. A finished or skipped tour SHALL NOT start again for that user until the tour's version goes up.

#### Scenario: A new game master takes the tour

- GIVEN game master Anna opens larpinq for the first time
- WHEN the tour starts
- THEN it points her at Characters, then at New to save a character
- AND after she saves one, it moves on to Events

#### Scenario: A skipped tour stays away

- GIVEN Anna skipped the tour at its second step
- WHEN she opens larpinq again the next day
- THEN the tour does not start
