# admin-configuration-store Specification

## Purpose

The Store page, where a game master installs game configurations other LARP organisations have published (a skill tree, conditions, a theme, sheet templates) into a world. OpenRegister hosts the store plane; larpinq declares the page.

## ADDED Requirements

### Requirement: REQ-ACS-001 The store lists shared game configurations

The Store page SHALL list the bundles the connected registry offers for larpinq. Each card SHALL show the title, the kind, who published it, what it contains and its version. With no registry connected, the page SHALL show the app's built-in items or say that no registry is connected, and SHALL make no call to a registry.

#### Scenario: A game master browses the store

- GIVEN a registry with the bundle "Fantasy basisregels (vaardighedenboom)" by Larpvereniging Zuiddrecht, version 2.1
- WHEN game master Anna opens the Store page
- THEN she sees its card with kind Spelregels, the publisher, "28 vaardigheden, 4 eigenschappen, 12 effecten" and version 2.1

#### Scenario: No registry is connected

- GIVEN no registry is configured in OpenRegister
- WHEN Anna opens the Store page
- THEN the page shows the built-in items or says no registry is connected
- AND no request goes to a registry

### Requirement: REQ-ACS-002 An administrator installs a bundle

Only an administrator SHALL see the Install action. Installing SHALL go through OpenRegister's store install endpoint, and larpinq SHALL keep no store controller of its own. When parts of a bundle could not be installed, the page SHALL name them.

#### Scenario: A player sees no install action

- GIVEN player Sem, who is not an administrator
- WHEN he opens the Store page
- THEN no card offers Install

#### Scenario: Part of a bundle is refused

- GIVEN a bundle with a skill set and a schema the instance refuses
- WHEN the administrator installs it
- THEN the skills are installed
- AND the page names the part that was not installed

### Requirement: REQ-ACS-003 A bundle goes into the world the organiser chooses

Installing a bundle SHALL ask which world it goes into, defaulting to the active world, or all worlds for a bundle without world content such as sheet templates. Each installed card SHALL say where it is installed, and a filter SHALL narrow the list to bundles that fit the active world, as LrStore draws it. A ruleset shared from another campaign SHALL arrive this way, with no separate import screen. (adm-app-store, rul-import-ruleset)

#### Scenario: A skill tree from another campaign goes into one world

- GIVEN worlds "De Zuiderlanden" and "Nevelkust"
- WHEN the administrator installs "Fantasy basisregels (vaardighedenboom)" and chooses De Zuiderlanden
- THEN its 28 skills belong to De Zuiderlanden only
- AND the card reads "Geïnstalleerd in De Zuiderlanden"
