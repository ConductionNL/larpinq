# RPG System

## Problem
Defines the core RPG mechanics of LarpingApp: Skills, Items, Conditions, Effects, and Abilities (stats). These entities form an interconnected system where Effects serve as the bridge between game elements and character stats. Skills, Items, Conditions, and Events each carry Effects that modify Abilities. Skills additionally support a prerequisite system requiring other skills, stats, conditions, effects, or a minimum score. The stat calculation is performed by `CharacterService` which applies effects in a deterministic order (skills -> items -> conditions -> events) with full audit trail. This spec focuses on the RPG system as a cohesive rule engine rather than individual entity CRUD.

## Proposed Solution
Implement RPG System following the detailed specification. Key requirements include:
- Requirement: Ability (Stat) Management
- Requirement: Effect System Core
- Requirement: Skill Management and Prerequisites
- Requirement: Item Management
- Requirement: Condition Management

## Scope
This change covers all requirements defined in the rpg-system specification.

## Success Criteria
- Create an ability and verify stat initialization
- Update ability base value propagates to characters
- Delete an ability referenced by effects
- Allowed_negative field not enforced
- Positive effect application
