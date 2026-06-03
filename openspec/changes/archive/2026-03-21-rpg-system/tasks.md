---
status: done
---

# RPG System — Tasks

- [x] Implement CharacterService with entity preloading (loadAllEntities, indexById)
- [x] Implement initializeAbilityScores() from all abilities
- [x] Implement applyEntityEffects() with property-based entity lookup
- [x] Implement applyEffects() iterating effect IDs
- [x] Implement calculateEffect() with collectEffectAbilities()
- [x] Implement applyModifierToAbility() with positive/negative modification
- [x] Implement audit trail recording per ability modification
- [x] Implement calculateCharacter() orchestrating the full pipeline
- [x] Implement calculateAllCharacters() for batch processing
- [x] Unit tests: CharacterServiceTest covering full RPG pipeline (ADR-009)
- [x] Feature documentation: docs/features/rpg-system.md (ADR-010)
- [x] i18n: N/A (backend service, no user-facing strings) (ADR-005)
