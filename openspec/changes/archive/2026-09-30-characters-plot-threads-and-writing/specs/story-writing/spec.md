# story-writing Specification

## Purpose

The story team writes plots that touch characters, keeps secrets from the
players they concern, and tracks who writes what and how far it is. From
larpinq matrix rows `chr-secrets-plots` and `chr-writing-progress`.

## ADDED Requirements

### Requirement: A plot links its characters with a text for each (REQ-CPW-001)

A game master SHALL be able to create a plot per world with a title, summary,
writer and writing step, and add plot parts that link it to characters, each
with a private text and a player text. The character page MUST list the plot
parts that touch the character.

#### Scenario: A game master writes the heir plot

- GIVEN world Aldmoor
- WHEN a game master creates plot "The heir of Aldmoor" and adds parts for "Mirela the Wanderer" and "Sir Bertram"
- THEN the plot page lists both characters with their texts
- AND the character page of "Mirela the Wanderer" lists "The heir of Aldmoor"

### Requirement: Private plot texts never reach players (REQ-CPW-002)

A plot part's private text MUST be readable by game masters only. A plot part's
player text SHALL be readable by game masters and the owner of its character.
A player MUST NOT read plot parts of other characters or the plot itself.

#### Scenario: Anna reads her dream, not her secret

- GIVEN the part of "The heir of Aldmoor" for "Mirela the Wanderer" has a private and a player text
- WHEN Anna, the owner of "Mirela the Wanderer", opens her character page
- THEN she sees "You dream of a crown you never wore"
- AND she does not see the private text or the part for "Sir Bertram"

### Requirement: Plots and characters have a writer and a writing step (REQ-CPW-003)

Plots and characters SHALL each carry a writer (a Nextcloud user) and a writing
step of draft, ready or approved, set by game masters. A plot's step MUST move
through the defined transitions (ready, approve, reopen).

#### Scenario: A plot is marked ready

- GIVEN plot "The heir of Aldmoor" is in draft with writer Joris
- WHEN Joris marks it ready on the plot page
- THEN its writing step is ready

### Requirement: Game masters see writing progress (REQ-CPW-004)

The Character roster report SHALL show how many characters are in each writing
step and list the characters not yet approved in writing, with their writer.

#### Scenario: Two weeks before the event

- GIVEN "Tomas" is in draft with writer Sanne and "Mirela the Wanderer" is approved
- WHEN a game master opens the Character roster report
- THEN the progress chart counts one draft and one approved
- AND the list shows "Tomas" with writer Sanne
