# Tasks: admin-configuration-store

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 3. -->

## 0. Record what is built

- [x] 0.1 `openspec/parity/capabilities.json`: adm-app-store cites this change, REQ-ACS-001 and REQ-ACS-002 (built: `src/manifest.json` page `Store`, nextcloud-vue `CnStorePage`, install through OpenRegister). rul-import-ruleset is folded into REQ-ACS-003.

## 1. Install into a world

- [ ] 1.1 nextcloud-vue `src/components/CnStorePage/CnStorePage.vue`: an optional `targets` prop (a list of `{ id, label }`) and a target picker before Install; the chosen id goes in the install body as `target` (REQ-ACS-003). Verify: its unit test sends `target` and keeps the old call when `targets` is empty.
- [ ] 1.2 ConductionNL/openregister store install endpoint: a `target` world id stamps the installed objects' world relation (`setting` for larpinq skills, conditions and effects), with an OpenSpec change there (REQ-ACS-003). Verify: its unit test installs a skill bundle into one world.
- [ ] 1.3 `src/manifest.json` page `Store`: `config.targets` from the larpinq worlds (`setting` schema), the "Past bij" filter on the active world, and "Geïnstalleerd in" per card (REQ-ACS-003). Verify: `npm run check:manifest`; Playwright `tests/e2e/store.spec.ts` installs a seeded bundle into one world.
