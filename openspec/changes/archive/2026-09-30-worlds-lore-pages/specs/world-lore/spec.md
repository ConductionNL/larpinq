# world-lore Specification

## Purpose

Game masters write lore pages per world; players read the pages meant for
them, from the moment they are revealed. From larpinq matrix rows
`wld-lore-wiki`, `wld-share-with-players` and `wld-scheduled-reveal`.

## ADDED Requirements

### Requirement: Game masters write lore pages per world (REQ-WLP-001)

A game master SHALL be able to create, edit and delete lore pages with a
title, a markdown body, a category (place, faction, history, rules, other), a
world, a visibility (game masters or players), a reveal moment and an optional
parent page. The world page MUST list its lore pages.

#### Scenario: A game master describes the city

- GIVEN world Aldmoor
- WHEN a game master creates lore page "The city of Aldmoor" in category place for players on the Lore page
- THEN the page appears in the Lore index and in the Lore list of Aldmoor

### Requirement: Players read only pages meant for them (REQ-WLP-002)

A player MUST be able to read a lore page only when its visibility is players
and its reveal moment has passed, on every read path including search and
exports. Game masters SHALL read every page.

#### Scenario: A secret faction page stays hidden

- GIVEN lore page "The Ash Circle" has visibility game masters
- WHEN player Anna searches the Lore index for "Ash"
- THEN no result is returned

### Requirement: A page opens at its reveal moment (REQ-WLP-003)

A lore page for players with a reveal moment in the future MUST NOT be
readable by players before that moment and SHALL be readable from it without
any action by a game master.

#### Scenario: The north gate falls on the evening of the event

- GIVEN lore page "The fall of the north gate" is for players with reveal moment 2026-10-10 18:00
- WHEN Anna opens the Lore index at 17:59 that day
- THEN the page is not listed
- WHEN Anna opens the Lore index at 18:01
- THEN the page is listed and she can read it

### Requirement: Players browse lore as articles (REQ-WLP-004)

A lore page SHALL open as a readable article with its body rendered from
markdown and a sidebar tree of the pages of its world that the reader may see.

#### Scenario: Anna reads about the war

- GIVEN "The war of the two queens" is revealed to players
- WHEN Anna opens it from the Lore index
- THEN she reads the rendered article
- AND the sidebar lists the other Aldmoor pages she may read, and none she may not
