# Tasks: admin-staff-roles-and-invites

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 9. -->

## 1. Roles

- [x] 1.1 Role constants on `Application` next to `GM_GROUP`, the `CreateStaffRoleGroups` repair step, and the ADR-002 inventory (REQ-ASR-001). Verify: PHPUnit for the repair; the repair-step-registration gate passes.
  > `AppIdentity::ROLE_WRITERS|ROLE_RULES|ROLE_TREASURERS|ROLE_STEWARDS` (aliased on `Application`), `Repair\CreateStaffRoleGroups` in post-migration and install (logs and continues, adds nobody), ADR-002 inventory updated. Test: `tests/unit/Repair/CreateStaffRoleGroupsTest.php`.
- [ ] 1.2 Fragment: the D2 rules on the schemas (REQ-ASR-002). Verify: `npm run check:register`; Newman per role: a writer creates a lore page and cannot edit a skill; a rules marshal edits a skill and cannot read plots; nobody but game masters deletes.
- [ ] 1.3 Steward check-in: the guard in `EventsController` and the attendance authorization (REQ-ASR-003). Verify: PHPUnit: a steward records attendance, cannot download the run sheet; semantic-auth and no-admin-idor gates pass.
  > Done: `CallerAccess::resolveCheckIn()` admits game masters, admins and `larpinq-stewards` to `recordAttendance()`; the run sheet stays `resolveGameMaster()`; the roster carries `canCheckIn`; stewards read, create and update `attendance` (`event-checkin-roster.json`, `xp-and-attendance-readers.json`). Tests: `EventsControllerStewardTest`. Open: the roster and the participation check read characters under OpenRegister RBAC, which admits game masters and owners only, so a steward sees no other player's character. Letting stewards read characters (or reading them with app authority behind the check-in guard) is a privacy decision the design does not take; it needs one before this box is ticked. The QR check-in route of `events-qr-checkin` does not exist on development yet.

## 2. Invites

- [ ] 2.1 Fragment `staffInvite`; `StaffInvitesController` create and redeem with hashing, expiry, uses, revocation and rate limit (REQ-ASR-004, REQ-ASR-005). Verify: PHPUnit per refusal; route-auth and public-endpoint-throttling gates pass.
- [ ] 2.2 The landing page `/staff-invite/{token}` with role and confirm (REQ-ASR-004). Verify: Playwright `tests/e2e/staff-invite.spec.ts`: a game master creates a steward invite, another user redeems it and can check someone in.

## 3. Staff page

- [ ] 3.1 `src/manifest.d/admin-staff-roles-and-invites.json`: Staff page with members, invites, revoke and remove (REQ-ASR-005). Verify: `npm run check:manifest`.

## 4. Strings and docs

- [ ] 4.1 Dutch and English strings (REQ-ASR-001). Verify: `npm run test:l10n`.
- [ ] 4.2 `docs/features/staff-roles.md` with the rights table (ADR-010). Verify: the docs build renders it.
- [x] 4.3 Replace the two private `GM_GROUP` constants with `Application::GM_GROUP` in the files this change touches (ADR-002 follow-up). Verify: `grep -rn "private const GM_GROUP" lib/Controller/EventsController.php` finds nothing.
  > Already true on development: the GM group is `AppIdentity::GM_GROUP` (aliased as `Application::GM_GROUP`) and no `private const GM_GROUP` remains in `lib/`.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
