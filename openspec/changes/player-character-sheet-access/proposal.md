---
kind: code
---

# Players download their own character sheet (ownerUid-based access)

## Why

The pdf-export capability promises player access and the code denies it:

- `openspec/specs/pdf-export/spec.md:11` (Purpose): "Enables game masters
  **and players** to export character data as downloadable PDF files."
- PDF-062 + the "Non-admin user downloads PDF" scenario
  (`openspec/specs/pdf-export/spec.md:260-270`) require that a regular
  user's download succeeds with "no admin rights required".
- The implementation hard-blocks every non-admin:
  `lib/Controller/CharactersController.php:146-148` returns 403 for any
  user not in the NC admin group, regardless of whether the character is
  their own.

The block was introduced deliberately (closes #205, Character PDF IDOR)
with a documented follow-up: "Per-player access requires the character
schema to gain a `player` ownership field — tracked as a follow-up"
(`lib/Controller/CharactersController.php:93-98,142-145`).

**That follow-up is now unblocked and the comment is stale.** The character
schema already carries an ownership field: `ownerUid` ("Nextcloud uid of
the player who owns this character. Used for per-player notifications") in
`lib/Settings/larpingapp_register.json`. It was added for the
character-approved notification rules but is referenced by **zero** lines
of PHP or JS in this app (`grep -rn ownerUid lib src` → no code hits). The
data model is there; only the authorization check is missing.

Net effect today: a player cannot print their own character sheet — the
single most common player-facing export in any LARP — while the spec
declares the flow Implemented.

## What Changes

- **Ownership-based access in `downloadPdf`.** Replace the admin-only
  guard in `lib/Controller/CharactersController.php` with a three-way
  allow: NC admin, member of the GM group (`gamemasters`, matching
  `EventsController::GM_GROUP`), or `character.ownerUid === current uid`.
  The ownership comparison happens AFTER the character fetch, so
  unauthorized callers still get 403 without learning whether the id
  exists (fetch first, authorize second, return 403 — not 404 — for
  non-owners to keep the current contract of #205).
- **GM-private redaction for owner downloads.** When the caller is
  authorized by ownership only (not admin/GM), strip `slNotesPrivate`
  from the character context passed to DocuDesk before rendering, so a
  player-triggered render can never leak GM-private notes even through a
  GM-authored template. `slNotesPublic` stays (it is the player-facing SL
  note by schema design).
- **Remove the stale follow-up comment** at
  `lib/Controller/CharactersController.php:63-66,93-98,142-145` — the
  ownership field exists and is now used.
- **Spec sync.** `pdf-export` gains an explicit access-control
  requirement (admin / GM / owner matrix + redaction); the misleading
  "any non-admin user succeeds" scenario under PDF-062 is corrected to
  the owner-scoped contract.
- **Tests.** PHPUnit matrix on `CharactersControllerTest`: owner 200,
  owner response context has no `slNotesPrivate`, non-owner non-GM 403,
  GM-group member 200 with private notes, admin 200 (unchanged), empty
  `ownerUid` → owner path denied.

Not BREAKING: admins and GMs keep exactly the access they have today; the
change only widens access to the character's own player.
