# Tasks: communication-mail-participants

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 7. -->

## 1. Data and service

- [ ] 1.1 Fragment: `eventMessage` with its rule (REQ-CMP-001, REQ-CMP-004). Verify: `npm run check:register`; `npm run check:schema-l10n`; Newman: a player cannot read messages.
- [ ] 1.2 `EventMessageService::recipients()` with the audience filters, address resolution and de-duplication (REQ-CMP-002). Verify: PHPUnit per filter, a player without an address, and a player with two registrations.
- [ ] 1.3 `EventMessagesController` preview and send, and `SendEventMessageJob` in batches of 50 (REQ-CMP-002, REQ-CMP-003). Verify: PHPUnit with a fake mailer: one mail each, counts right, a second send refused; route-auth, no-admin-idor and semantic-auth gates pass.

## 2. Page

- [ ] 2.1 `src/modals/EmailParticipantsModal.vue` and the header action and Messages list on EventDetail in `src/manifest.json` (REQ-CMP-001 to REQ-CMP-004). Verify: `npm run check:manifest`; modal-isolation and nc-input-labels gates pass.
- [ ] 2.2 Playwright `tests/e2e/email-participants.spec.ts`: a game master writes to accepted participants of "Winter Court 2026", sees 3 recipients, sends, and finds the message in the list (REQ-CMP-001 to REQ-CMP-004). Verify: passes locally against the dev mail catcher.

## 3. Strings and docs

- [ ] 3.1 Dutch and English strings (REQ-CMP-001). Verify: `npm run test:l10n`.
- [ ] 3.2 `docs/features/email-participants.md` (ADR-010). Verify: the docs build renders it.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
