# Tasks: capability-rows-missing-requirements

## 1. Built behaviour

- [ ] 1.1 Confirm the world page shows the description, the player page shows the account and the characters, the event page shows the Deck tab, and a player picks a choice per registration option (REQ-SMD-001, REQ-EPL-001, REQ-EPL-002, REQ-LFI-001, REQ-RTO-007). Verify: Playwright `tests/e2e/spec-coverage/` covers each scenario or carries an `@e2e exclude` reason.

## 2. World timeline

- [ ] 2.1 `lib/Settings/register.d/world-timeline.json`: a `historyEntry` schema with world, in-game date, title, text and visibility (REQ-SMD-002). Verify: `npm run check:register`.
- [ ] 2.2 SettingDetail timeline widget in `src/manifest.json` that merges history entries and the world's events in date order (REQ-SMD-002). Verify: `npm run check:manifest`; Playwright: the Aldmoor timeline lists the founding before the Summer Siege.

## 3. Buying items with in-game money

- [x] 3.1 `item.price` (gold, silver, copper) and a server-side purchase that adds the item and takes the money in one write, refused when the character cannot pay (REQ-RPG-001). Verify: PHPUnit for the purchase service, both scenarios.
- [ ] 3.2 A Buy action on the character page for items with a price (REQ-RPG-001). Verify: `npm run check:manifest`; Dutch and English strings with `npm run test:l10n`.

## 4. Run sheet download

- [ ] 4.1 A Download run-sheet action on the event page in `src/manifest.json` that opens the template picker and calls `GET /events/{id}/runsheet/{template}`, shown to game masters only (pdf-export RUN-020). Verify: Playwright `tests/e2e/spec-coverage/event-runsheet-export.spec.ts` downloads the run sheet from the event page.

## 5. Game master override from the character page

- [ ] 5.1 An override action on the character page in `src/manifest.json`, shown to the game master group only, that asks for a reason and sends the skill with a `requirementOverrides[]` entry in one write (REQ-SRE-001). Verify: `npm run check:manifest`; Playwright: a game master grants "Dragon Speech" with a reason and the audit trail shows it; a player gets no override action.

## 6. Rejecting a character

- [ ] 6.1 A `rejected` state with a required `rejectionReason` on the character's approval lifecycle, a Reject action for game masters next to Approve in `src/manifest.json`, the state and reason on the character page and in the Characters list, and a notification to the owner; a rejected character can be submitted again (REQ-CTA-001). Verify: `npm run check:register`; `npm run check:manifest`; PHPUnit for the transition and the notification; Dutch and English strings with `npm run test:l10n`.
