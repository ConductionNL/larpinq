---
kind: code
depends_on: []
---

# Proposal: admin-staff-roles-and-invites

## Summary

A LARP team is more than game masters. Story writers write plots, a rules
marshal keeps the skills and items straight, a treasurer watches payments,
stewards check people in at the gate. Larpinq knows two kinds of people: game
masters and everyone else. This change adds four staff roles, each a
Nextcloud group with rights on the schemas its work needs, and an invite link
a game master can hand out so a new team member joins a role by opening it,
instead of an administrator adding them by hand.

## Motivation

Two admin rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build` for both.

**`adm-roles-permissions`**, "Give different people different rights, such as
story writer, rules marshal or treasurer." Larpinq rates it no. Matrix
evidence: "grep -rniE '\brole\b|rbac|permission' lib/Controller lib/Service
lib/Settings/larpinq_register.json: only OpenRegister's generic
object/schema-level RBAC flag (_rbac, e.g. lib/Service/RegisterObjectFetcher.php:402)
and the admin+larpers dual-group gate in appinfo/info.xml; no distinct in-app
roles". Five competitors rate it yes:

- LarpManager: "larpmanager/models/access.py:45-200 AssociationRole and EventRole built from 49 organisation and 91 event permissions (larpmanager/fixtures/association_permission.yaml, event_permission.yaml), larpmanager/urls/exe.py:358 exe_roles" (source read at main 36f23d3).
- MyLARP: "'User role assignment for staff' is explicit. https://mylarp.com"
- LARP Portal: "PC/NPC/Staff roles with granular staff permissions (marketing, event setup, approvals, housing, points, etc). https://larportal.com/how-it-works.php"
- Kanka: "custom campaign roles with per-module and per-entry permissions (routes/campaigns/campaign.php:89-91,130; app/Enums/Permission.php)" (source read at tag 3.15).
- pretix: "src/pretix/base/models/organizer.py:362-394 Team with all_events/limit_events and per-permission sets for events and organizer (src/pretix/base/permissions.py:56), so a treasurer team, a check-in team and admins get different rights" (source read at tag v2026.7.0).

**`adm-role-invite`**, "Invite someone to a staff role with a link instead of
adding them by hand." Larpinq rates it no. Matrix evidence: "GM rights come
from Nextcloud group membership (appinfo/info.xml:111-133, group 'larpers' plus
'admin' for GM endpoints); larpinq has no invite link". Three competitors rate
it yes:

- LarpManager: "larpmanager/models/access.py:197 RoleInvite token, larpmanager/urls/orga.py:1791 orga_roles_invite, larpmanager/urls/user.py:450 role_invite_redeem". Its changelog: https://github.com/LoSkana/larpmanager/commit/8a1a5d8563.
- Kanka: "app/Http/Controllers/Campaign/InviteController.php:76-81 creates an invite link with a role_id and validity; joined at routes/web-i18n.php:16 campaigns.join".
- pretix: "src/pretix/base/models/organizer.py:504 TeamInvite sends a link that src/pretix/control/urls.py:52 auth.invite (src/pretix/control/views/auth.py:228) turns into team membership".

## Affected Projects

- [ ] Project: `larpinq`: four staff role groups with schema rights, a steward check on check-in, a staff invite object and a redeem endpoint, and a Staff page.

## Scope

### In Scope

- Roles as Nextcloud groups, created on install if missing: story writers, rules marshals, treasurers, stewards. The game master tier stays the one group `gamemasters` (ADR-002).
- Rights, added to the schemas' authorization rules: story writers write plots, plot parts, lore pages and the story fields of characters; rules marshals write abilities, skills, items, conditions and effects; treasurers read registrations with their payment fields; stewards record check-in and read the roster.
- Staff invites: a game master creates a link for one role with an expiry and a number of uses; a signed-in Nextcloud user who opens it joins the role (and `larpers`); the invite records who joined.
- A Staff page for game masters: members per role, open invites, revoke.

### Out of Scope

- Roles defined by the group itself with free permission sets: four fixed roles cover the rows' examples.
- Inviting people without a Nextcloud account: an administrator or Nextcloud's guest features create the account first.
- Per-event roles (a steward for one event only).

## Approach

Group ids are declared once as constants on `Application` next to the GM group
(ADR-002 decision 1) and literally in the register's authorization rules
(decision 3, with ADR-002's inventory updated). The invite is a register
object; redeeming is a small controller that checks the token and adds the user
to the group. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/AppInfo/Application.php`: role group constants; `lib/Repair/CreateStaffRoleGroups.php` (new).
- `lib/Settings/register.d/admin-staff-roles-and-invites.json` (new): `staffInvite`, and role rules on the schemas.
- `lib/Controller/StaffInvitesController.php` (new), routes; `lib/Controller/EventsController.php`: stewards pass the check-in guard.
- `src/manifest.d/admin-staff-roles-and-invites.json` (new): the Staff page and the invite landing page.
- `openspec/architecture/adr-002-gm-authorization-single-group.md`: inventory of the new groups.

## Cross-Project Dependencies

None.

## Risks

### Risk 1: An invite link leaks
**Severity:** High. **Mitigation:** the token is 128 random bits stored hashed, invites expire (default 7 days) and have a use count (default 1), the landing page names the role and asks to confirm, and game masters see every join and can revoke the invite and remove the member.

### Risk 2: A role gets more than it needs
**Severity:** Medium. **Mitigation:** each role's rights are listed in design.md per schema and asserted by Newman; no role can delete characters, awards or registrations.

## Rollback Strategy

Remove the rules from the fragment and the invite routes. The groups stay in
Nextcloud, empty of rights.

## Open Questions

None.
