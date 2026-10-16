# Tasks: worlds-maps

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 6. -->

## 1. Data

- [ ] 1.1 `lib/Settings/register.d/worlds-maps.json`: `worldMap` and `mapPin` with rules (REQ-WMP-001, REQ-WMP-003). Verify: `npm run check:register`; `npm run check:schema-l10n`; Newman: a player gets no game-master map or pin.
- [ ] 1.2 Seed the Aldmoor map and pins of design.md. Verify: the seed imports cleanly.

## 2. Pages

- [ ] 2.1 `src/manifest.d/worlds-maps.json`: Maps index and detail with the pin list, menu entry; SettingDetail Maps list in `src/manifest.json` (REQ-WMP-001). Verify: `npm run check:manifest`.
- [ ] 2.2 `WorldMap` `type: "map"` page with the image layer and pin markers, after the nextcloud-vue release with the image layer (REQ-WMP-002). Verify: Playwright `tests/e2e/world-maps.spec.ts`: a player opens the valley map, clicks "Aldmoor city" and reaches its lore page.

## 3. Strings and docs

- [ ] 3.1 Dutch and English strings (REQ-WMP-001). Verify: `npm run test:l10n`.
- [ ] 3.2 `docs/features/world-maps.md` with a screenshot (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): the map page task starts only when `@conduction/nextcloud-vue` ships the image layer.
