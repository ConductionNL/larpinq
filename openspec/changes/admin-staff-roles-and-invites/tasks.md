# Tasks: admin-staff-roles-and-invites

<!-- HYDRA CAP: max 20 unindented `- [ ]` lines. This file uses 9. -->

## 1. Roles

- [x] 1.1 Role constants on `Application` next to `GM_GROUP`, the `CreateStaffRoleGroups` repair step, and the ADR-002 inventory (REQ-ASR-001). Verify: PHPUnit for the repair; the repair-step-registration gate passes.
  > `AppIdentity::ROLE_WRITERS|ROLE_RULES|ROLE_TREASURERS|ROLE_STEWARDS` (aliased on `Application`), `Repair\CreateStaffRoleGroups` in post-migration and install (logs and continues, adds nobody), ADR-002 inventory updated. Test: `tests/unit/Repair/CreateStaffRoleGroupsTest.php`.
- [x] 1.2 Fragment: the D2 rules on the schemas (REQ-ASR-002). Verify: `npm run check:register`; Newman per role: a writer creates a lore page and cannot edit a skill; a rules marshal edits a skill and cannot read plots; nobody but game masters deletes.
  > `lib/Settings/register.d/admin-staff-roles-and-invites.json`: writers read every character field and write `background`, `slNotesPublic`, `slNotesPrivate`, `writer`, `writingStep`, plots, plot parts and lore pages; rules marshals write abilities, skills, items, conditions and effects; treasurers read registrations and write `paymentState`; stewards read characters and registrations. Every other character and registration field carries an update rule without the role, so the row-level update reaches only those fields; no role deletes. Test: `StaffRolesFragmentTest`; pinned rule tests updated. The Newman runs per role are left: they need a live instance.
- [x] 1.3 Steward check-in: the guard in `EventsController` and the attendance authorization (REQ-ASR-003). Verify: PHPUnit: a steward records attendance, cannot download the run sheet; semantic-auth and no-admin-idor gates pass.
  > `CallerAccess::resolveCheckIn()` admits game masters, admins and `larpinq-stewards` to `recordAttendance()`; the run sheet stays `resolveGameMaster()`; the roster carries `canCheckIn`; stewards read, create and update `attendance`. Stewards now read `character` and `registration` rows through the schema rules (`admin-staff-roles-and-invites.json`), so the roster and the participation check see the cast; the private character fields keep their own read rules without the stewards, and stewards change no character. Tests: `EventsControllerStewardTest`, `StaffRolesFragmentTest`. The QR check-in route of `events-qr-checkin` does not exist on development yet.

## 2. Invites

- [x] 2.1 Fragment `staffInvite`; `StaffInvitesController` create and redeem with hashing, expiry, uses, revocation and rate limit (REQ-ASR-004, REQ-ASR-005). Verify: PHPUnit per refusal; route-auth and public-endpoint-throttling gates pass.
  > `staffInvite` (slug `larping_staff_invite`, game masters only) in `admin-staff-roles-and-invites.json`. `StaffInviteService` stores a SHA-256 hash of a 128-bit token, checks revoked, expired and used up in that order, adds the user to the role and `larpers` through `IGroupManager`, records the use, and takes no use from a member already in the role. `StaffInvitesController`: create, revoke, team and remove are game master only; inspect and redeem need a signed-in user and are rate limited per user (20 and 10 per 5 minutes). openapi.json documents the six routes. Tests: `StaffInviteServiceTest` (15), `StaffInvitesControllerTest` (12). The design's demo invite is not seeded: a token in the repository would be a working link on every demo install.
- [x] 2.2 The landing page `/staff-invite/{token}` with role and confirm (REQ-ASR-004). Verify: Playwright `tests/e2e/staff-invite.spec.ts`: a game master creates a steward invite, another user redeems it and can check someone in.
  > `src/views/StaffInviteLanding.vue` on the route `/staff-invite/:token` (`src/manifest.d/admin-staff-roles-and-invites.json`): names the role, joins on confirm, and says why an expired, used-up, revoked or unknown link adds nobody. Calls in `src/services/staffInvites.js`, tested by `tests/vitest/staffInvites.spec.js`. The Playwright run `tests/e2e/staff-invite.spec.ts` is left: it needs a live instance with two users.

## 3. Staff page

- [x] 3.1 `src/manifest.d/admin-staff-roles-and-invites.json`: Staff page with members, invites, revoke and remove (REQ-ASR-005). Verify: `npm run check:manifest`.
  > The Staff page `/staff` (`src/views/StaffPage.vue`, menu entry in the settings section): members per role with remove, invites with state and revoke, and the create form that shows the link once. Members come from Nextcloud groups, which no typed manifest page reads, so it is a custom page over `GET /api/staff`. `npm run check:manifest` reports only the four unknown page types it reports on development.

## 4. Strings and docs

- [x] 4.1 Dutch and English strings (REQ-ASR-001). Verify: `npm run test:l10n`.
  > English and the 36 other shipped locales for every new string; `npm run test:l10n` passes (it also gains the missing `Open sheet` key).
- [x] 4.2 `docs/features/staff-roles.md` with the rights table (ADR-010). Verify: the docs build renders it.
  > `docs/features/staff-roles.md`, listed in `docs/features/README.md`.
- [x] 4.3 Replace the two private `GM_GROUP` constants with `Application::GM_GROUP` in the files this change touches (ADR-002 follow-up). Verify: `grep -rn "private const GM_GROUP" lib/Controller/EventsController.php` finds nothing.
  > Already true on development: the GM group is `AppIdentity::GM_GROUP` (aliased as `Application::GM_GROUP`) and no `private const GM_GROUP` remains in `lib/`.

Quality reminders (not tracked as tasks): `composer check:strict` once before push.
