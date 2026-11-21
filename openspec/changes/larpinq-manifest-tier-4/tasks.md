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

- [x] 2.1 Update `src/main.js` to mount `CnAppRoot` directly with
      `manifest` + `registry` props; drop the legacy `Vue.use(...)` +
      `CnPageRenderer` boilerplate.
  - **acceptance_criteria**: Boot path is `CnAppRoot.mount(...)`; no
    further bespoke setup remains.
  - Done with the library's one-call boot `mountCnApp(...)`
    (nextcloud-vue 2.181.0), which builds the router from the manifest
    and mounts CnAppRoot; App.vue stays the root wrapper for the
    `#sidebar` and `#user-settings` slots.

- [x] 2.2 Remove `src/customComponents.js`.
  - **acceptance_criteria**: `grep -rn customComponents src/` returns no
    hits.

## Phase 3 — Registry audit

- [x] 3.1 Audit every entry in `src/registry.js`. For each non-page
      entry, set `kind:` to one of `'widget'` / `'tab'` / `'header'` /
      `'actions'` / `'section'`. Page entries keep `kind:'page'`.
  - **acceptance_criteria**: Every entry has a `kind:` field; ADR-036
    resolver finds each by lookup.

- [x] 3.2 Drop any `Vue.component(...)` calls that
      `customComponents.js` previously made (these are now resolved
      from `registry.js` via the `kind:`-tagged entries).
  - **acceptance_criteria**: No leftover ad-hoc `Vue.component` calls
    for app components.

## Phase 4 — Verification

- [x] 4.1 `npm run check:manifest` clean.
- [ ] 4.2 `composer check:strict` clean.
  - 21 Nov, development after #1130, #1132 and #1134 (OpenRegister main beside the app): lint, phpcs (0 errors), psalm (no errors), phpstan (no errors) and test:all (1405 tests, OK) pass. phpmd keeps 9 findings: coupling in StaffInvitesController, CharacterRequirementListener, HelpMessageListener, RegistrationListener, RegisterObjectFetcher and StaffInviteService; class complexity in EventFeedbackService, RegistrationService and StaffInviteService. Each needs a collaborator extracted.
- [x] 4.3 Live-deploy regression: each manifest menu item, page widget
      slot, and detail tab renders against the dev container.
  - Passed live 17 Nov on a fresh rig: `index-pages.spec.ts`, `spa-ui.spec.ts`, `detail-forms-admin.spec.ts` and `app-chrome.spec.ts` render every menu entry, index, detail page and sidebar tab (selector drift fixed in the same PR).
- [x] 4.4 Visual regression / Playwright spot-check on the
      `Characters` index + `CharacterDetail` detail (CSAT surfaces).
  - Characters index and CharacterDetail pass in `index-pages.spec.ts` and `detail-forms-admin.spec.ts` on the 17 Nov rig.
