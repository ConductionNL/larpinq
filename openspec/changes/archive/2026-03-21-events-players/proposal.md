# Events and Players

## Problem
Manages LARP events (game gatherings with date ranges, locations, and participant tracking) and player profiles (real-world people who play characters). Events can carry Effects that are applied to participating characters during stat calculation via `CharacterService.applyEntityEffects()`. Players serve as the link between real-world people and their in-game characters via the character's `ocName` field. Both entity types are managed through the generic object store pattern and support OpenRegister features (audit trails, relations, locking).

## Proposed Solution
Implement Events and Players following the detailed specification. Key requirements include:
- Requirement: Event CRUD Operations
- Requirement: Event Effect Application to Characters
- Requirement: Player Profile Management
- Requirement: Shared OpenRegister Features
- Requirement: Internal vs OpenRegister Storage

## Scope
This change covers all requirements defined in the events-players specification.

## Success Criteria
- Create an event with effects
- Update an event
- Delete an event
- List events with search
- View event participants via relations tab
