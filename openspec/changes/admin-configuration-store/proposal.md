---
kind: code
---

# Proposal: admin-configuration-store

## Summary

A game master can install a game configuration another LARP organisation has published: a skill tree, a set of conditions, a theme or sheet templates. The Store page exists (`src/manifest.json`, page `Store`, type `store`), and OpenRegister hosts the store plane, so larpinq writes no store code. No spec said what the page promises. This change writes it down as LrStore draws it, including the part the code does not do yet: choosing the world a bundle goes into.

It also covers the parked row rul-import-ruleset ("Import a ruleset from a file or another campaign"). A ruleset from another campaign is a bundle in the store. There is no separate import screen.

## Motivation

Row adm-app-store in `openspec/parity/capabilities.json` is built with `spec: none`. Read from code and nextcloud-vue on 30 October 2026:

- `src/manifest.json` page `Store` (`/store`, `config.app: larpinq`) renders nextcloud-vue `CnStorePage`.
- `CnStorePage` lists cards from the registry OpenRegister connects to, shows built-in items when no registry is connected, offers Install to administrators only, and calls `POST /apps/larpinq/api/store/items/{slug}/install`, which OpenRegister serves. It reports the parts that were not installed.
- Install has no target world: a bundle lands in the instance, not in a chosen world. LrStore draws "een bundel voegt vaardigheden, toestanden of een thema toe aan de wereld die u kiest", a "Geïnstalleerd in De Zuiderlanden" line per card and a "Past bij" filter on the active world.
- `lib/Settings/larpinq_register.json` marks the skill schema `x-openregister-shareable`, which is how a skill tree reaches the registry.

## Scope

### In scope

1. The store lists the shared bundles, with who published them, what they contain and their version.
2. An administrator installs a bundle and sees what was not installed.
3. The organiser chooses the world a bundle goes into, and each card says where it is installed (tasks 1.1 to 1.3).
4. A ruleset from another campaign arrives as a bundle (rul-import-ruleset).

### Out of scope

- Running a registry: OpenRegister and the registry operator own that.
- Importing a ruleset from a spreadsheet: `specs/data-portability` covers file import.

## Matrix rows

- adm-app-store: Install shared game-configuration bundles, such as a skill tree, a set of conditions or the flows behind an event, that other organisations have published.
- rul-import-ruleset (parked row, folded in): Import a ruleset from a file or another campaign.
