## 1. Verify the shared util's contract matches the inline copy

- [ ] 1.1 Read `nextcloud-vue/src/utils/buildManifest.js` in full and diff its `mergeMenuItems` /
      `applyMenuRelocations` / `applyMenuRemovals` / `applySettingsSection` behavior against
      larpingapp's `src/main.js:63-195` line by line; confirm semantics match (merge-by-id with
      first-definition-wins, relocation passes until stable, leaf-only removal, settings-foldout
      flatten-and-lift).
- [ ] 1.2 Confirm the installed `@conduction/nextcloud-vue` version in `package.json` /
      `package-lock.json` actually exports `buildManifest` (not just the source repo) — bump the
      dependency if the installed version predates the export.

## 2. Replace the inline pipeline in `src/main.js`

- [ ] 2.1 Add `buildManifest` to the existing `@conduction/nextcloud-vue` import at the top of
      `src/main.js`.
- [ ] 2.2 Delete `mergeMenuItems`, `applyMenuRelocations`, `applyMenuRemovals`,
      `applySettingsSection`, and `mergeManifestFragments` (`src/main.js:54-242`).
- [ ] 2.3 Keep the `require.context('./manifest.d', false, /\.json$/)` block and pass its resolved
      fragment list, `bundledManifest`, and `menuLayout` into `buildManifest(...)` to produce the
      `manifest` const the rest of the file already consumes.
- [ ] 2.4 Confirm `routesFromManifest(manifest)` and the `manifest` prop passed to `App.vue` are
      unaffected (same shape in, same shape out).

## 3. Verify no behavior change

- [ ] 3.1 Diff the merged manifest's `menu` array before/after (dump to JSON in a scratch script or
      via a temporary console.log) for the current `src/manifest.json` + `src/menu-layout.json` +
      `src/manifest.d/*` inputs; confirm byte-for-byte-equivalent structure (order, ids, children,
      settings-foldout membership).
- [ ] 3.2 Run the app's existing frontend unit/lint suite; fix any pre-existing lint/test warnings
      encountered in `src/main.js` while here (CLAUDE.md: always fix pre-existing issues touched).
- [ ] 3.3 Live-verify: load the app in the dev Nextcloud instance, confirm the main nav, the
      settings-foldout entries, and every route (including deep links) render identically to
      before the change.

## 4. Traceability

- [ ] 4.1 Run `openspec validate larpingapp-adopt-shared-menu-pipeline --strict` and resolve any
      errors.
