# skill-requirement-enforcement delta

## ADDED Requirements

### Requirement: A game master records an override on the character page (REQ-SRE-001)

The character page SHALL let a member of the game master group add a requirement override for a skill the character does not qualify for, with a reason. The page SHALL save it as a `requirementOverrides[]` entry in the same write as the skill, so the existing server check and audit trail apply. A player SHALL NOT see the action.

#### Scenario: A game master grants a skill as a plot reward

- GIVEN "Chosen One" lacks the prerequisites for skill "Dragon Speech"
- WHEN game master Anna adds "Dragon Speech" on the character page and gives the reason "Plot reward from Summer LARP finale"
- THEN "Chosen One" has "Dragon Speech"
- AND the character's audit trail shows Anna's override with that reason

#### Scenario: A player has no override action

- GIVEN player Sem opens his own character "Chosen One"
- WHEN he adds a skill he does not qualify for
- THEN the page offers no override
- AND the save is refused with the missing prerequisite named
