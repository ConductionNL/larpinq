# menu-architecture Specification

## Purpose
How Larpinq builds its effective navigation menu: the bundled manifest, its `src/manifest.d` fragments and `src/menu-layout.json` are merged by the shared nc-vue `buildManifest()` util, not by app-local code.

## Requirements

### Requirement: Effective Menu Built via Shared buildManifest Util

Larpinq MUST compute its effective manifest (merged fragments, relocations, removals,
settings-foldout membership) by calling `buildManifest(base, fragments, menuLayout)` from
`@conduction/nextcloud-vue`. Larpinq MUST NOT re-implement `mergeMenuItems`,
`applyMenuRelocations`, `applyMenuRemovals`, or `applySettingsSection` as local functions in
`src/main.js` or anywhere else in the app. Collecting `src/manifest.d/*.json` fragments via
`require.context` remains the only app-local step per ADR-044 §1.

#### Scenario: main.js delegates menu computation to the shared util

- GIVEN `src/main.js` imports `buildManifest` from `@conduction/nextcloud-vue`
- WHEN the app bootstraps
- THEN the effective manifest MUST be produced by a single `buildManifest(bundledManifest,
  fragments, menuLayout)` call
- AND `src/main.js` MUST NOT contain a local re-implementation of the merge/relocate/remove/
  settings-foldout pipeline

#### Scenario: No functionality loss versus the retired inline pipeline

- GIVEN the current `src/manifest.json`, `src/menu-layout.json`, and `src/manifest.d/*` fragments
- WHEN the shared `buildManifest` util computes the effective menu
- THEN every menu entry and route reachable before the change MUST remain reachable (main nav,
  settings foldout, or a card-grid landing page) per the ADR-044 no-functionality-loss invariant
