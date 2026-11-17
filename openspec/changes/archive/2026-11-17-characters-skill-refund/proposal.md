---
kind: code
---

# Proposal: characters-skill-refund

## Summary

A player who unlearns a skill gets its XP back. larpinq already does this: the stat engine recomputes a character from scratch, so a removed skill's cost stops counting and XP left goes up. No requirement said so, and the XP history does not show the refund. LrKarakterStatistieken draws a line "Kruidenkunde verwijderd: 6 XP teruggegeven". This change writes the refund down and plans that line.

## Motivation

Row prg-refund-skill cites `specs/character-management`, whose "Character Associations" requirement only links skills. Read from code on 30 October 2026: `lib/Service/CharacterService.php` `calculateCharacter()` applies only the skills linked now, and `lib/Service/CharacterStatsPresenter.php` derives XP earned, spent and left from that run (REQ-CSP-003). `lib/Service/SkillRequirementService.php` `analyseRemovals()` warns when a remaining skill loses its prerequisite. Nothing records the refund itself.

## Scope

### In scope

1. Removing a learned skill returns its XP cost to XP left (built).
2. The stats page lists the refund in the XP history, as LrKarakterStatistieken draws it (tasks 1.1 and 1.2).

### Out of scope

- A refund rule per world (for example half the XP back): no matrix row asks for it.

## Matrix rows

- prg-refund-skill: Unlearn a skill and get its XP back.
