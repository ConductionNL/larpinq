# pdf-export — delta for player-character-sheet-access

## ADDED Requirements

### Requirement: Character Sheet Access Matrix

The character PDF download endpoint MUST authorize exactly three caller
classes: Nextcloud administrators, members of the GM group
(`gamemasters`), and the character's owning player (the authenticated
user whose uid equals the character's `ownerUid`). Every other
authenticated caller MUST receive 403, and the 403 MUST NOT reveal
whether the character id exists.

#### Scenario: Owner downloads their own character sheet

- GIVEN character "Sir Lancelot" with `ownerUid` "alice"
- AND user "alice" is authenticated and is neither admin nor in `gamemasters`
- AND DocuDesk is installed
- WHEN "alice" requests `GET /characters/{id}/download/{template}` for "Sir Lancelot"
- THEN the PDF MUST be generated and returned as a `DataDownloadResponse`

#### Scenario: Non-owner player is denied without id disclosure

- GIVEN character "Sir Lancelot" with `ownerUid` "alice"
- AND user "bob" is authenticated and is neither admin nor in `gamemasters`
- WHEN "bob" requests the character sheet download for "Sir Lancelot"
- THEN the response MUST be 403 `{"error": "Access denied"}`
- AND the response MUST be identical for existing and non-existing character ids

#### Scenario: GM-group member downloads any character sheet

- GIVEN user "gm-carol" is a member of the `gamemasters` group and is not an NC admin
- WHEN "gm-carol" requests the character sheet download for any character
- THEN the PDF MUST be generated and returned

#### Scenario: Character without ownerUid grants no player access

- GIVEN a character whose `ownerUid` is empty or absent
- AND an authenticated non-admin, non-GM user
- WHEN the user requests the character sheet download
- THEN the response MUST be 403 (an empty owner never matches any caller)

### Requirement: GM-Private Redaction for Owner Downloads

When the caller is authorized by ownership only (not admin and not in the GM group), the render context passed to DocuDesk MUST NOT contain the `slNotesPrivate` field, so no template — including GM-authored templates — can leak GM-private notes into a player-triggered render.
`slNotesPublic` MUST remain available to owner renders.

#### Scenario: Owner render context excludes GM-private notes

- GIVEN character "Sir Lancelot" owned by "alice" with non-empty `slNotesPrivate` and `slNotesPublic`
- WHEN "alice" (owner, non-admin, non-GM) downloads the character sheet
- THEN the context handed to `PdfService::renderPdf()` MUST NOT contain `slNotesPrivate`
- AND the context MUST contain `slNotesPublic`

#### Scenario: GM render context keeps GM-private notes

- GIVEN the same character
- WHEN an admin or `gamemasters` member downloads the character sheet
- THEN the render context MUST contain `slNotesPrivate` unchanged

## MODIFIED Requirements

### Requirement: Route and Security Configuration

The PDF download route MUST be properly configured with appropriate
access controls. The endpoint MUST carry `@NoAdminRequired` (players are
non-admins) and `@NoCSRFRequired` (direct URL access in a new tab), and
the in-body guard MUST enforce the Character Sheet Access Matrix — the
route being reachable by non-admins MUST NOT mean every non-admin may
download every character.

#### Scenario: Non-admin owner download succeeds

- GIVEN a regular user who owns the character (`ownerUid` matches)
- AND DocuDesk is installed
- WHEN the user navigates to the character PDF download URL
- THEN the PDF MUST be generated and returned
- AND no admin rights MUST be required

#### Scenario: CSRF not required for download

- GIVEN the download URL is opened in a new browser tab
- WHEN the request is made without a CSRF token
- THEN the request MUST succeed because `@NoCSRFRequired` is set

#### Scenario: Unauthenticated user denied

- GIVEN no user is logged in
- WHEN someone navigates to a character PDF download URL
- THEN Nextcloud's authentication MUST redirect to the login page
