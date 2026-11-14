---
kind: code
---

# Proposal: setup-guided-tour

## Summary

A new user gets a short guided tour of larpinq. The tour exists: `src/manifest.json` declares the walkthrough `larpinq:getting-started` with seven steps, shown on first visit. LrInstellen draws it as the last setup step: "Na afloop start een korte rondleiding langs Karakters, Spelregels en Evenementen. U kunt die altijd overslaan." Only the flows step had a requirement (`specs/flows`). This change writes down the tour itself.

## Motivation

Row adm-walkthrough cited `archive/beta-surface-alignment`, which covers app metadata, not the tour. Read from code on 30 October 2026: `src/manifest.json` `walkthrough` (enabled, version 1, `completionConfigKey: walkthrough_completed_version`, trigger `first-visit`, steps welcome, go-characters, create-character, go-events, create-event, see-flows, done). `lib/Repair/MigrateUserPreferences.php` names the per-user completion preference.

## Scope

### In scope

1. The tour starts on a user's first visit, can be skipped, and does not come back once finished or skipped.
2. It walks through characters and events, and the user creates each record.

### Out of scope

- The flows step: `specs/flows` "The walkthrough MUST point new users at the flows" already covers it.

## Matrix rows

- adm-walkthrough: Take a guided tour of the app.
