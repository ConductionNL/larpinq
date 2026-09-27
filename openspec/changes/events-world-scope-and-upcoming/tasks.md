# Tasks: events-world-scope-and-upcoming

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Event world and upcoming

- [ ] 1.1 Fragment making `event.setting` visible; EventDetail and Events index show it (REQ-EWU-001). Verify: `npm run check:register`; `npm run check:manifest`.
- [ ] 1.2 Events index default filter and sort with the past toggle; the dashboard "Upcoming events" widget, in `src/manifest.json` (REQ-EWU-002). Verify: `npm run check:manifest`; vitest or Playwright asserts the list query carries `startDate` greater than or equal to today.

## 2. Active world

- [ ] 2.1 `useActiveWorld()` over the preferences API with the archived fall-back (setting-management requirement "A per-user active setting MUST filter lists server-side"). Verify: vitest for load, change, and fall-back.
- [ ] 2.2 `WorldSwitcher.vue` as a registry `header` component on the Dashboard and the world-scoped index pages, adding the world filter to list queries (same requirement, REQ-EWU-003). Verify: Playwright `tests/e2e/active-world.spec.ts`: with Aldmoor active the Events index lists only Aldmoor and shared events, and the request carries the filter; nc-input-labels gate passes.
- [ ] 2.3 Replace the `@e2e exclude` on the active-setting requirement in `openspec/specs/setting-management/spec.md` with the new test. Verify: the e2e-coverage gate passes on the diff.

## 3. Strings and docs

- [ ] 3.1 Dutch and English strings (REQ-EWU-001). Verify: `npm run test:l10n`.
- [ ] 3.2 `docs/features/worlds-and-upcoming-events.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
