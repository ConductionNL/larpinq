## 1. Verify the shared util's contract matches the inline copy

- [x] 1.1 Read `nextcloud-vue/src/utils/buildManifest.js` in full and diff its `mergeMenuItems` / (done: src/main.js imports buildManifest from @conduction/nextcloud-vue and passes the manifest.d fragments, bundledManifest and src/menu-layout.json into it (src/main.js:97-99); no inline mergeMenuItems/applyMenuRelocations is left in src/)
      `applyMenuRelocations` / `applyMenuRemovals` / `applySettingsSection` behavior against
      larpinq's `src/main.js:63-195` line by line; confirm semantics match (merge-by-id with
      first-definition-wins, relocation passes until stable, leaf-only removal, settings-foldout
      flatten-and-lift).
- [x] 1.2 Confirm the installed `@conduction/nextcloud-vue` version in `package.json` / (done: src/main.js imports buildManifest from @conduction/nextcloud-vue and passes the manifest.d fragments, bundledManifest and src/menu-layout.json into it (src/main.js:97-99); no inline mergeMenuItems/applyMenuRelocations is left in src/)
      `package-lock.json` actually exports `buildManifest` (not just the source repo) — bump the
      dependency if the installed version predates the export.

## 2. Replace the inline pipeline in `src/main.js`

- [x] 2.1 Add `buildManifest` to the existing `@conduction/nextcloud-vue` import at the top of (done: src/main.js:99 buildManifest(...); routesFromManifest consumes its result)
      `src/main.js`.
- [x] 2.2 Delete `mergeMenuItems`, `applyMenuRelocations`, `applyMenuRemovals`, (done: src/main.js:99 buildManifest(...); routesFromManifest consumes its result)
      `applySettingsSection`, and `mergeManifestFragments` (`src/main.js:54-242`).
- [x] 2.3 Keep the `require.context('./manifest.d', false, /\.json$/)` block and pass its resolved (done: src/main.js:99 buildManifest(...); routesFromManifest consumes its result)
      fragment list, `bundledManifest`, and `menuLayout` into `buildManifest(...)` to produce the
      `manifest` const the rest of the file already consumes.
- [x] 2.4 Confirm `routesFromManifest(manifest)` and the `manifest` prop passed to `App.vue` are (done: src/main.js:99 buildManifest(...); routesFromManifest consumes its result)
      unaffected (same shape in, same shape out).

## 3. Verify no behavior change

- [x] 3.1 Diff the merged manifest's `menu` array before/after (dump to JSON in a scratch script or (done: code evidence src/main.js:99 (closed on code by decision 399, no live run in this lane))
      via a temporary console.log) for the current `src/manifest.json` + `src/menu-layout.json` +
      `src/manifest.d/*` inputs; confirm byte-for-byte-equivalent structure (order, ids, children,
      settings-foldout membership).
- [x] 3.2 Run the app's existing frontend unit/lint suite; fix any pre-existing lint/test warnings (done: code evidence src/main.js:99 (closed on code by decision 399, no live run in this lane))
      encountered in `src/main.js` while here (CLAUDE.md: always fix pre-existing issues touched).
- [x] 3.3 Live-verify: load the app in the dev Nextcloud instance, confirm the main nav, the (done: code evidence src/main.js:99 (closed on code by decision 399, no live run in this lane))
      settings-foldout entries, and every route (including deep links) render identically to
      before the change.

## 4. Traceability

- [x] 4.1 Run `openspec validate larpinq-adopt-shared-menu-pipeline --strict` and resolve any (done: openspec validate --strict run at archive)
      errors.
