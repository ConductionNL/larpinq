# Staff roles and invites

A LARP team is more than its game masters. Story writers write the plots, a rules marshal keeps the skills and items straight, a treasurer watches the payments and stewards check people in at the gate. Larpinq gives each of them the rights of their work, and a game master invites a new team member with a link instead of asking an administrator to add them.

## The roles

Each role is a Nextcloud group. Larpinq creates the groups on install when they are missing, and adds nobody to them. The game masters stay the one group `gamemasters`.

| Role | Group | Reads | Creates and changes | Deletes |
|---|---|---|---|---|
| Game master | `gamemasters` | everything | everything | everything |
| Story writer | `larpinq-writers` | characters (every field), plots, plot parts, lore pages | plots, plot parts, lore pages; the story fields of a character: background, public and private notes, writer and writing step | nothing |
| Rules marshal | `larpinq-rules` | abilities, skills, items, conditions, effects | abilities, skills, items, conditions, effects | nothing |
| Treasurer | `larpinq-treasurers` | registrations, events | the payment state of a registration | nothing |
| Steward | `larpinq-stewards` | characters, registrations, the event roster | check-in (attendance) | nothing |

A role adds rights. It takes none away: a steward who plays a character still edits their own character as a player. No role can delete characters, XP awards or registrations. A steward cannot download the run sheet, and changes no character.

## Invite someone

1. Open **Staff** in the settings section of the navigation.
2. Under **Invite someone**, choose the role, for how many days the link is valid (1 to 90, 7 by default) and how many people may join with it (1 to 100, 1 by default).
3. Choose **Create invite link** and copy the link. It is shown only once: larpinq keeps a hash of it, not the link itself.
4. Send the link to the new team member.

The new team member opens the link while signed in to Nextcloud. The page names the role. When they choose **Join as …**, they join the role, and the app's user group `larpers` when they were not in it yet. The invite records who joined and when.

An expired, used-up or revoked link adds nobody and says why. Someone who already has the role takes no use of the link. Opening and redeeming links is rate limited per user.

Inviting someone who has no Nextcloud account yet is not part of larpinq: an administrator, or Nextcloud's guest features, creates the account first.

## Manage the team

The **Staff** page shows the members of every role and every invite with its state: open, expired, used up or revoked.

- **Revoke** an open invite and it adds nobody from then on.
- **Remove** a member from a role and they lose its rights at once. A game master cannot remove themself from the game masters.

Only game masters and administrators see the Staff page's data and may call `GET /api/staff`, `POST /api/staff-invites`, `POST /api/staff-invites/{id}/revoke` and `DELETE /api/staff/{role}/members/{uid}`. Opening (`POST /api/staff-invites/inspect`) and redeeming (`POST /api/staff-invites/redeem`) a link needs only a signed-in user.
