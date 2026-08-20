# Tasks — adopt-live-updates-ui

## 1. Dependency bump

- [x] 1.1 Bump `@conduction/nextcloud-vue` to `^1.0.0-beta.212` and reinstall

## 2. Wire subscriptions

- [x] 2.1 Subscribe `SkillTree.vue` to the `skill` + `character` collections on mount (refetch-hint semantics, store-cache bridge watcher)
- [x] 2.2 Release subscriptions on destroy with epoch guard for in-flight subscribes

## 3. Verify

- [x] 3.1 `npm run lint` clean on touched files
- [x] 3.2 `npm run test:unit` green
- [x] 3.3 `npm run build` green
