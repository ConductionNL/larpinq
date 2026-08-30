# Events and Players

## Overview

Manages LARP events (game gatherings with date ranges, locations, and participant tracking) and player profiles (real-world people who play characters).

## Features

### Events
- **Create events** with name, description, start date, end date, and location
- **Assign effects** to events via `effects[]` UUID array
- **Assign players** to events via `players[]` UUID array
- **Event effects** are applied to characters during stat calculation
- **List events** with search and pagination

### Players
- **Create player profiles** with name and contact information
- **Link to characters** via the character's `ocName` field
- **List players** with search and pagination

## Navigation

- Events: CalendarMonthOutline icon in main navigation
- Players: AccountGroupOutline icon in main navigation

## Routes

- `/events`, `/events/:id` — Event views
- `/players`, `/players/:id` — Player views

## Screenshot

![Events and Players](/screenshots/events-players.png)

## Technical Details

- Both use the generic object store pattern
- Event effects applied via `CharacterService.applyEntityEffects()`
- Player-character link via `ocName` field on character objects
