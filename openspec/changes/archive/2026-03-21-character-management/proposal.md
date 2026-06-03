# Character Management

## Problem
Provides full CRUD lifecycle management for LARP characters, including player characters, NPCs, and other character types. Characters serve as the central entity in the application, linking to skills, items, conditions, and events. The system includes a stat calculation engine (`CharacterService.calculateCharacter()`) that automatically computes ability scores based on associated effects, a currency system (gold/silver/copper), approval workflow, and background/notes management. Character data is fetched via `RegisterObjectFetcher` which resolves OpenRegister mappers from per-type configuration.

## Proposed Solution
Implement Character Management following the detailed specification. Key requirements include:
- Requirement: Character CRUD Operations
- Requirement: Character Types and Approval Workflow
- Requirement: Currency System
- Requirement: Character Associations
- Requirement: Stat Calculation Engine

## Scope
This change covers all requirements defined in the character-management specification.

## Success Criteria
- Create a new character
- Update an existing character
- Delete a character
- Search characters by name
- Create character with required name validation
