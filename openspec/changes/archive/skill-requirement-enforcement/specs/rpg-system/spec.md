---
status: draft
---

# RPG System — delta for skill-requirement-enforcement

## MODIFIED Requirements

### Requirement: Skill Management and Prerequisites

Skills represent learnable abilities that characters acquire. They carry effects and can require prerequisites before a character can take them. Prerequisites and XP costs MUST be enforced server-side during character assignment (see the `skill-requirement-enforcement` capability for the enforcement, override, and report semantics).

| ID | Requirement | Priority | Status |
|----|------------|----------|--------|
| SKILL-001 | Create skills with name, description, effect text, and effects array | MUST | Implemented |
| SKILL-002 | Update existing skills including effect assignments | MUST | Implemented |
| SKILL-003 | Delete skills with confirmation dialog | MUST | Implemented |
| SKILL-004 | List skills with search and pagination | MUST | Implemented |
| SKILL-005 | View skill details with Effects, Characters (relations), and Logging tabs | MUST | Implemented |
| SKILL-006 | Assign one or more Effects to a skill via `effects[]` UUID array | MUST | Implemented |
| SKILL-007 | Skill detail MUST show associated effects with name, modification type, and modifier value | MUST | Implemented |
| SKILL-008 | View which characters use this skill via relations tab | MUST | Implemented |
| SKILL-009 | Skills can require other skills as prerequisites (`requiredSkills[]`) | MUST | Implemented |
| SKILL-010 | Skills can require minimum stat values (`requiredStats[]`) | MUST | Implemented |
| SKILL-011 | Skills can require specific conditions (`requiredConditions[]`) | MUST | Implemented |
| SKILL-012 | Skills can require specific effects (`requiredEffects[]`) | MUST | Implemented |
| SKILL-013 | Skills can require a minimum score threshold (`requiredScore`) | MUST | Implemented |
| SKILL-014 | Prerequisites and XP budget MUST be enforced server-side when skills are assigned to a character (vetoable OR pre-write hook; was data-only) | MUST | Planned |
| SKILL-015 | Enforcement MUST be overridable per assignment by a GM via an explicit, reasoned `requirementOverrides[]` entry, audited through the OR object audit trail | MUST | Planned |
| SKILL-016 | Removing an assigned prerequisite skill MUST flag dependent skills via a validation report and MUST NOT cascade-delete them | MUST | Planned |

#### Scenario: Create a skill with effects

- GIVEN effects "Mana +5" and "XP Cost -10" exist
- WHEN a game master creates skill "Basic Healing" with effects=["mana-5", "xp-cost-10"]
- THEN the skill MUST store both effect references
- AND when assigned to a character, both effects MUST apply during stat calculation

#### Scenario: Skill prerequisite chain

- GIVEN skill "Basic Swordplay" exists
- AND skill "Advanced Swordplay" is created with requiredSkills=["basic-swordplay"], requiredScore=5
- WHEN viewing the skill details
- THEN requiredSkills MUST list "Basic Swordplay"
- AND requiredScore MUST show 5

#### Scenario: Prerequisites enforced on character assignment

- GIVEN skill "Advanced Swordplay" with requiredSkills=["basic-swordplay"]
- AND character "Squire" who does not have "Basic Swordplay"
- WHEN "Advanced Swordplay" is assigned to "Squire" without a GM override
- THEN the character write MUST be rejected server-side
- AND the unmet prerequisite MUST be itemised in the error payload

#### Scenario: XP budget enforced on character assignment

- GIVEN character "Novice" whose computed XP ability value is 10
- AND skill "Master Smithing" carrying effect "XP Cost -15"
- WHEN the skill is assigned without a GM override
- THEN the write MUST be rejected because the engine-computed candidate XP would be negative

#### Scenario: Skill effects visible in detail

- GIVEN skill "Fireball" has effects "Arcane Mana +5" (positive, modifier 5) and "HP -1" (negative, modifier 1)
- WHEN the user views the skill detail Effects tab
- THEN both effects MUST be listed with their names, modification types, and modifier values

#### Scenario: Characters using a skill

- GIVEN skill "Healing" is assigned to characters "Cleric" and "Paladin"
- WHEN viewing the skill's Characters tab
- THEN both characters MUST be listed
