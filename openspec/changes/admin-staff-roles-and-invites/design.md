# Design: admin-staff-roles-and-invites

## Context

Read at development `77c85f0`.

- App access is the group `larpers` (`appinfo/info.xml:111-133`); game master
  guards use `gamemasters` or admin: `EventsController::GM_GROUP`
  (`:51`), `CharacterRequirementListener::GM_GROUP` (`:60`), OpenRegister
  authorization on `xpAward` and `larping_attendance`.
- `openspec/architecture/adr-002-gm-authorization-single-group.md`: one GM
  group declared once as `Application::GM_GROUP`; declarative occurrences use
  the literal and are listed in the ADR's inventory; a second GM group is
  forbidden.
- Check-in (`EventsController::recordAttendance()`) is game master only, and
  `larping_attendance` writes are restricted to `gamemasters`.
- Schemas added in this pass that roles touch: `plot`, `plotPart`
  (`characters-plot-threads-and-writing`), `lorePage` (`worlds-lore-pages`),
  `registration` (`registration-intake-and-capacity`).

## Goals / Non-Goals

**Goals**: four roles with the rights their work needs; joining by link.

**Non-Goals**: custom roles, account creation, per-event roles.

## Decisions

### D1. Groups

`Application::ROLE_WRITERS = 'larpinq-writers'`, `ROLE_RULES =
'larpinq-rules'`, `ROLE_TREASURERS = 'larpinq-treasurers'`, `ROLE_STEWARDS =
'larpinq-stewards'`, next to `GM_GROUP`. A repair step creates missing groups.
Names are conventions, like `gamemasters`; ADR-002's inventory lists them.

### D2. Rights per role

| Role | Read | Create and update | Delete |
|---|---|---|---|
| writers | characters (all fields), plots, plot parts, lore pages | plots, plot parts, lore pages; character `background`, `slNotesPublic`, `slNotesPrivate`, `writer`, `writingStep` | none |
| rules | abilities, skills, items, conditions, effects | the same five | none |
| treasurers | registrations with payment fields, events | registration `paymentState` by hand when shillinq is absent | none |
| stewards | the roster and registrations of events | attendance (check-in) | none |

Game masters keep everything. The rules go into the schemas' `authorization`
blocks and property rules through the fragment.

### D3. Stewards at the gate

`EventsController`'s guard for `roster()`, `recordAttendance()` and the
check-in by code of `events-qr-checkin` accepts `gamemasters`, admins and
`larpinq-stewards`; `larping_attendance` authorization adds the stewards group.
The run sheet stays game master only.

### D4. Invites

`staffInvite` (slug `larping_staff_invite`): `role` (one of the four, or
`gamemasters`), `tokenHash`, `expiresAt`, `maxUses`, `uses[]` (uid and time),
`createdBy`, `revokedAt`. Game masters only. `POST /api/staff-invites`
returns the link once with the plain token. `GET /staff-invite/{token}`
(logged in) shows the role and a confirm button; `POST
/api/staff-invites/redeem` checks the hash, expiry, uses and revocation, adds
the user to the role group and to `larpers` through `IGroupManager`, and
records the use. Rate limited per user (hydra ADR-082).

### D5. Staff page

`Staff` (fragment, game masters): members per role (read from the groups),
invites with state, create, revoke, and remove a member from a role.

## Declarative-vs-imperative decision

| Behaviour | Path | Rationale |
|---|---|---|
| Rights per role | Declarative, schema authorization rules | OpenRegister RBAC. |
| Invite records | Declarative, register fragment | A schema. |
| Redeeming an invite | Imperative, a controller | Adds a user to a Nextcloud group, which no declarative extension does. |
| Steward check-in | Imperative, the existing guard | The guard already lives in the controller. |

## Seed data

Groups created empty; a demo invite for stewards, 3 uses, expiring in 7 days.

## Risks / Trade-offs

- [Group names] A group with the same name already used by something else is
  reused; the Staff page shows its members so a game master notices.

## Migration

The repair step creates the groups on upgrade; nobody is added.

## Open Questions

None.
