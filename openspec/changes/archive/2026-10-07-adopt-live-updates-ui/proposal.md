# Adopt live updates in app-local UI (nc-vue beta.212)

## Why

`@conduction/nextcloud-vue` 1.0.0-beta.212 installs `liveUpdatesPlugin` by
default on every `createObjectStore` store (lazy — inert until the first
`subscribe()` call) and fixes the first-subscription transport. OpenRegister
already pushes `or-collection-*` / `or-object-*` events for every
OpenRegister-backed object, so adopting live updates is a frontend-only change:
views subscribe while mounted and re-render from the store's refetched cache.

## What Changes

- Bump `@conduction/nextcloud-vue` to `^1.0.0-beta.212`.
- Wire live collection subscriptions (`skill`, `character`) into the app-local
  `SkillTree.vue` view: subscribe on mount, bridge the refetched store cache
  into the view's local copies, release on destroy with epoch-guarded in-flight
  handling (openregister reference pattern).
- Add the `realtime-updates` adoption spec.

## Out of Scope (documented skips)

- Manifest-driven index/detail pages (Characters, Events, Players, …) are
  rendered by the shared library (`CnPageRenderer` → `CnIndexPage` /
  `CnDetailPage`). `CnIndexPage` has no subscription support and
  `CnPageRenderer` does not pass an `objectStore` instance to `CnDetailPage`
  (whose auto-subscribe requires it), so live updates for manifest pages must
  land in `nextcloud-vue`, not per-app.
- `EventRoster.vue` fetches through a bespoke app endpoint
  (`/api/events/{id}/roster`), not an OpenRegister object store — no store
  cache to subscribe against.
- `ObjectDetail.vue` is a leaf marker host without data fetching.

## Impact

- Affected specs: `realtime-updates` (new)
- Affected code: `package.json`, `src/views/SkillTree.vue`
