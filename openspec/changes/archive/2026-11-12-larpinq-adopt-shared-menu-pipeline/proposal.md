---
kind: code
---

## Why

ADR-044 (`hydra/openspec/architecture/adr-044-menu-architecture.md`) Decision #1 is explicit:
"Apps MUST build their effective manifest via `@conduction/nextcloud-vue` `buildManifest(base,
fragments, menuLayout)`. No app may re-implement `mergeMenuItems` / `applyMenuRelocations` /
`applyMenuRemovals` / `applySettingsSection` inline." The ADR's "Consequences" section lists
larpinq among the apps it claims already shipped this ("Shipped 2026-06 to shillinq, pipelinq,
procest, openregister, decidesk, openconnector, opencatalogi, softwarecatalog, larpingapp,
doriath").

That claim does not match the code at HEAD. `src/main.js:54-242` carries a full, hand-rolled copy
of the exact pipeline the ADR forbids:

- `mergeMenuItems` (`src/main.js:63-82`)
- `applyMenuRelocations` (`src/main.js:97-136`)
- `applyMenuRemovals` (`src/main.js:146-156`)
- `applySettingsSection` (`src/main.js:173-195`)
- `mergeManifestFragments` (`src/main.js:211-242`), which calls the four functions above instead
  of `buildManifest`

Meanwhile `@conduction/nextcloud-vue` already exports the shared implementation —
`nextcloud-vue/src/index.js:292`: `export { buildManifest, applyMenuLayout, mergeMenuItems,
mergePages, applyMenuRelocations, applyMenuRemovals, applySettingsSection } from
'./utils/buildManifest.js'` — and `nextcloud-vue/src/utils/buildManifest.js:25` defines
`buildManifest(base, fragments = [], menuLayout = {})` with the identical contract larpinq's
inline copy re-derives (fragment merge → relocations → removals → settings-foldout lift).

This is precisely the drift ADR-044 was written to prevent: "The duplication drifted between apps
and made navigation-IA changes a per-app rewrite." larpinq's ~180-line inline copy is drift
that must be deleted, not a completed adoption.

## What Changes

- Replace `src/main.js`'s inline `mergeMenuItems` / `applyMenuRelocations` / `applyMenuRemovals` /
  `applySettingsSection` / `mergeManifestFragments` functions with a single call to
  `buildManifest(bundledManifest, fragments, menuLayout)` imported from `@conduction/nextcloud-vue`.
- Keep the existing `require.context('./manifest.d', false, /\.json$/)` fragment collection in
  `src/main.js` — ADR-044 §1 explicitly keeps fragment collection as "the ONLY app-local step";
  only the merge/relocate/remove/settings-foldout logic moves into the shared util.
- No change to `src/manifest.json`, `src/menu-layout.json`, or `src/manifest.d/*` — the data these
  functions operate on is unchanged; only the code computing the effective menu moves to the shared
  implementation.
- Not a BREAKING change: the resulting merged manifest/menu shape is unchanged (same fragment
  merge order, same relocation/removal/settings-foldout semantics) — this is a delete-duplicate-code
  refactor, not a behavior change.

## Impact

- `src/main.js` — remove ~180 lines of inline pipeline logic (lines 54-242), replace with an
  import + one `buildManifest(...)` call.
- No PHP, no route, no schema changes.
- Corrects the stale "Shipped" claim in `hydra/openspec/architecture/adr-044-menu-architecture.md`
  for larpinq specifically — that fleet-wide ADR file is out of scope for this change (owned by
  hydra, not larpinq) but the fix here makes the claim true.
