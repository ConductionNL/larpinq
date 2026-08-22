# Tasks: larpinq-manifest-tier-4

## Phase 1 — Prerequisites (already met)

- [x] 1.1 ADR-036 kind-agnostic slot resolver shipped in nextcloud-vue
      (`nextcloud-vue#459`). Verified: `resolveCustomComponent` accepts
      any `kind` field; slot/actions/section lookups no longer require
      `kind:"page"`.
- [x] 1.2 `useAppStatus('openregister')` verified live on the dev
      container (W24 2026-06-12; `larpinq-adopt-or-abstractions §1.5`).
- [x] 1.3 `CnPageRenderer` mount path already in `src/main.js`; manifest
      is the declarative source of truth.

## Phase 2 — Replace mount

- [ ] 2.1 Update `src/main.js` to mount `CnAppRoot` directly with
      `manifest` + `registry` props; drop the legacy `Vue.use(...)` +
      `CnPageRenderer` boilerplate.
  - **acceptance_criteria**: Boot path is `CnAppRoot.mount(...)`; no
    further bespoke setup remains.

- [ ] 2.2 Remove `src/customComponents.js`.
  - **acceptance_criteria**: `grep -rn customComponents src/` returns no
    hits.

## Phase 3 — Registry audit

- [ ] 3.1 Audit every entry in `src/registry.js`. For each non-page
      entry, set `kind:` to one of `'widget'` / `'tab'` / `'header'` /
      `'actions'` / `'section'`. Page entries keep `kind:'page'`.
  - **acceptance_criteria**: Every entry has a `kind:` field; ADR-036
    resolver finds each by lookup.

- [ ] 3.2 Drop any `Vue.component(...)` calls that
      `customComponents.js` previously made (these are now resolved
      from `registry.js` via the `kind:`-tagged entries).
  - **acceptance_criteria**: No leftover ad-hoc `Vue.component` calls
    for app components.

## Phase 4 — Verification

- [ ] 4.1 `npm run check:manifest` clean.
- [ ] 4.2 `composer check:strict` clean.
- [ ] 4.3 Live-deploy regression: each manifest menu item, page widget
      slot, and detail tab renders against the dev container.
- [ ] 4.4 Visual regression / Playwright spot-check on the
      `Characters` index + `CharacterDetail` detail (CSAT surfaces).
