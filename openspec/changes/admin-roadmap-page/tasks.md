# Tasks: admin-roadmap-page

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 2. -->

## 0. Record what is built

- [x] 0.1 `openspec/parity/capabilities.json`: adm-roadmap cites this change, REQ-ARM-001 and REQ-ARM-002 (built: `src/manifest.json` page `FeaturesRoadmap`, nextcloud-vue `CnFeaturesAndRoadmapView` and `CnRoadmapTab`).

## 1. Three columns

- [ ] 1.1 nextcloud-vue `src/components/CnRoadmapTab/CnRoadmapTab.vue`: a `columns` mode that groups items by state and an in-progress label into Gepland, In ontwikkeling and Beschikbaar with counts; the proxy call adds closed issues with `state_reason: completed` (REQ-ARM-003). Verify: its unit test groups three fixture issues into the three columns.
- [ ] 1.2 `src/manifest.json` page `FeaturesRoadmap`: `config.layout: "columns"` once 1.1 is released (REQ-ARM-003). Verify: `npm run check:manifest`.
