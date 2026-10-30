# world-maps Specification

## Purpose

A world has uploaded maps with pins that link to lore, readable by players
when meant for them. From larpinq matrix row `wld-maps`.

## ADDED Requirements

### Requirement: Game masters upload maps and place pins (REQ-WMP-001)

A game master SHALL be able to upload a map image for a world with a title and
a visibility, and place pins on it with a label, a position on the image, a
kind and an optional lore page. The world page MUST list its maps.

#### Scenario: A game master maps the valley

- GIVEN world Aldmoor
- WHEN a game master uploads "The valley of Aldmoor" and adds pin "Aldmoor city" at x 820, y 610 linked to lore page "The city of Aldmoor"
- THEN the Maps list of Aldmoor shows the map
- AND the map lists the pin

### Requirement: The map shows its pins (REQ-WMP-002)

The map page SHALL show the uploaded image with every pin the reader may see at
its position, and a pin MUST open its label and a link to its lore page.

#### Scenario: Anna finds the city

- GIVEN map "The valley of Aldmoor" is for players
- WHEN Anna opens it and clicks pin "Aldmoor city"
- THEN she sees "Aldmoor city" and a link that opens lore page "The city of Aldmoor"

### Requirement: Hidden maps and pins stay hidden (REQ-WMP-003)

A map or pin with visibility game masters MUST NOT be readable by players, on
the map page and through the API.

#### Scenario: The dangerous grove

- GIVEN pin "Ash grove" on the valley map has visibility game masters
- WHEN Anna opens the valley map
- THEN "Ash grove" is not shown
