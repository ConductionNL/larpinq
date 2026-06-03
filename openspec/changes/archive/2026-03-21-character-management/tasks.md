---
status: done
---

# Character Management — Tasks

- [x] Implement Character CRUD via generic object store (ObjectList.vue, ObjectDetail.vue)
- [x] Implement CharacterService.calculateCharacter() stat engine
- [x] Implement CharacterService.initializeAbilityScores() with base values
- [x] Implement applyEntityEffects() for skills, items, conditions, events
- [x] Implement applyModifierToAbility() with positive/negative modification types
- [x] Implement audit trail recording in stat calculation
- [x] Add character routes in router/index.js (/characters, /characters/:id)
- [x] Add Characters navigation item in MainMenu.vue
- [x] Unit tests: CharacterServiceTest covering calculateCharacter, initializeAbilityScores, applyEffects (ADR-009)
- [x] Feature documentation: docs/features/character-management.md (ADR-010)
- [x] Screenshot: docs/screenshots/character-management.png (ADR-010)
- [x] i18n: Verify all character-related strings use t() function (ADR-005)
