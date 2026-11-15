# admin-roadmap-page Specification

## Purpose

The Features and roadmap page: which larpinq features exist, which are coming next, and where a user votes or asks for a new one. The list comes from the app's public feature-request issues.

## ADDED Requirements

### Requirement: REQ-ARM-001 The roadmap lists the open feature requests by votes

The roadmap SHALL list the larpinq repository's issues labelled `enhancement` or `feature`, read through OpenRegister's GitHub issue proxy, with the most voted first. When the proxy has no token, is rate limited or cannot be reached, the page SHALL say which of these happened instead of showing an empty list.

#### Scenario: An organiser sees what is coming

- GIVEN open feature requests "Kaart van de wereld met pinnen" with 31 votes and "Spreukenlijsten los van vaardigheden" with 18
- WHEN organiser Anna opens Features and roadmap
- THEN the map request is listed above the spell lists request

#### Scenario: The proxy has no token

- GIVEN OpenRegister's GitHub issue proxy has no token configured
- WHEN Anna opens the roadmap
- THEN the page says the roadmap is not configured yet

### Requirement: REQ-ARM-002 A user votes or asks for a feature on the forge

Each card SHALL link to its issue, where a user votes with a reaction. A "Functie aanvragen" action SHALL open the repository's feature-request issue form. larpinq SHALL keep no vote or request store of its own.

#### Scenario: A player asks for a feature

- WHEN player Sem chooses Functie aanvragen
- THEN the larpinq feature-request form opens on the forge in a new tab

### Requirement: REQ-ARM-003 The roadmap shows planned, in progress and available

The roadmap SHALL group the cards in three columns, as LrRoadmap draws them: Gepland (open, not started), In ontwikkeling (open, labelled as in progress) and Beschikbaar (closed as completed). Each column SHALL show its count, and each card its area and expected moment when the issue carries them.

#### Scenario: A finished feature moves to available

- GIVEN the issue "Kaart van de wereld met pinnen" is closed as completed
- WHEN Anna opens the roadmap
- THEN the card shows under Beschikbaar
- AND the Beschikbaar count includes it
