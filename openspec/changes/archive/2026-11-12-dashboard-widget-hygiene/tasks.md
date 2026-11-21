# Tasks — dashboard-widget-hygiene

## 1. Dead widget removal

- [ ] 1.1 Delete `src/views/dashboard/DashboardKpi.vue`
- [ ] 1.2 Delete `src/views/dashboard/DashboardRecentList.vue`
- [ ] 1.3 `src/registry.js`: remove the `DashboardKpi` and `DashboardRecentList` imports and registry entries (lines 22-23 and 29-30); keep `DashboardSkillUsage`, `DashboardActions`, `GameSettingsSection`, `ObjectDetail`
- [ ] 1.4 Grep-verify no remaining references (`grep -rn 'DashboardKpi\|DashboardRecentList' src tests`) and that `npm run build` succeeds

## 2. Manifest color tokens

- [ ] 2.1 `src/manifest.json`: remove `"valueColor": "#0082c9"` from the `kpi-characters`, `kpi-events`, `kpi-items`, `kpi-players` stat widgets (lines 85-88) so the renderer's themed default applies
- [ ] 2.2 Run `node tests/validate-manifest.js` (gate-22) to confirm the manifest still validates

## 3. DashboardActions transport

- [ ] 3.1 `src/views/dashboard/DashboardActions.vue`: import `generateUrl` from `@nextcloud/router` and `getRequestToken` from `@nextcloud/auth`
- [ ] 3.2 Replace the literal `fetch('/index.php/apps/openregister/api/schemas/' + config.schema, …)` with `fetch(generateUrl('/apps/openregister/api/schemas/{id}', { id: config.schema }), …)` and `requesttoken: getRequestToken()` (drop the `OC.requestToken` global)
- [ ] 3.3 Vitest: unit test asserting `loadSchema` calls `generateUrl` with the schema id (mock fetch), covering the sub-path install regression

## 4. Spec sync + quality

- [ ] 4.1 On archive, fold the delta into `openspec/specs/dashboard-analytics-widgets/spec.md` (KPI/recent-list REQs re-anchored on declarative manifest widgets; transport REQ gains the router/auth-helper MUST)
- [ ] 4.2 `npm run lint` + `npm run test:unit` green; hydra gates green on the diff (gate-19 back-references for changed scenarios or `@e2e exclude` with reason)
- [ ] 4.3 Bump `appinfo/info.xml` version for cache-bust of the rebuilt bundle
