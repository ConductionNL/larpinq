---
kind: code
---

# Proposal: admin-roadmap-page

## Summary

Players and organisers can see from inside the app which features are coming next, vote on them and ask for a new one. The Features and roadmap page exists (`src/manifest.json`, page `FeaturesRoadmap`, type `roadmap`). No spec said where its list comes from. This change writes it down as LrRoadmap draws it.

## Motivation

Row adm-roadmap in `openspec/parity/capabilities.json` is built with `spec: none`. Read from code and nextcloud-vue on 30 October 2026:

- `src/manifest.json` page `FeaturesRoadmap` (`/features-roadmap`) sets only `documentationUrl`; the footer menu links to it.
- nextcloud-vue `CnFeaturesAndRoadmapView` switches between the features list and the roadmap, and links "Suggest a feature" to the forge's feature-request issue form.
- `CnRoadmapTab` reads the app's GitHub issues labelled `enhancement` or `feature` through OpenRegister's `github-issue-proxy`, sorted by reactions, and names its degraded states (no token configured, rate limited, network error, empty).
- LrRoadmap draws the roadmap in three columns: Gepland, In ontwikkeling and Beschikbaar, with an area and an expected moment per card. The tab shows one list.

## Scope

### In scope

1. The list comes from the app's public feature-request issues, sorted by votes.
2. A user votes and asks for a feature on the forge.
3. The roadmap shows three columns as LrRoadmap draws them (task 1.1).

### Out of scope

- A curated roadmap file in the app: the issues are the source.

## Matrix rows

- adm-roadmap: See which features are coming next from inside the app.
