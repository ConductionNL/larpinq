# portal-contribution Specification

**Status**: implemented (self-signup); the rest of larpinq's portal contribution is in the open change `portal-contribution`
**Scope**: larpinq
**OpenSpec changes**:
- `players-self-signup` (archived 2026-09-30)

## Purpose
A new player signs up in the portal and creates their own player profile,
which larpinq links to their portal account. From larpinq matrix row
`ply-self-signup`.

The action and collection are in `lib/Portal/PortalContributionProvider.php`
(pinned by `tests/unit/Portal/PortalContributionProviderTest.php`); the fields
in `lib/Settings/register.d/players-self-signup.json`
(`tests/unit/Settings/PlayersSelfSignupFragmentTest.php`); the one-profile rule,
the flags, the claim and the portal character's player in
`lib/Listener/PortalProfileListener.php`
(`tests/unit/Listener/PortalProfileListenerTest.php`, with the real OpenRegister
and portaliq event classes); the review stamp in
`lib/Listener/PlayerReviewListener.php`
(`tests/unit/Listener/PlayerReviewListenerTest.php`); the New players page in
`src/manifest.d/players-self-signup.json` (`tests/vitest/playerReview.spec.js`).
The browser and API proof is
`tests/e2e/workflows/player-self-signup.workflow.spec.ts`.

## Requirements


### Requirement: A portal visitor creates their own player profile (REQ-PSS-001)

The larpinq contribution to the portal SHALL offer the `player` audience an
action to create a player profile with a name and a description, stamped with
the visitor's portal subject, and a collection that shows only that profile.

#### Scenario: Lotte joins the campaign

- GIVEN Lotte has signed up and logged in to the portal, with no player profile yet
- WHEN Lotte creates her player profile "Lotte Bakker" through the portal
- THEN a player "Lotte Bakker" exists in larpinq with her portal subject
- AND the portal's "My profile" shows it

### Requirement: The profile is linked to the portal account (REQ-PSS-002)

After a profile is created through the portal, larpinq SHALL ask portaliq to
record the profile's id as the account's `ownerRef` claim for larpinq, so the
player's characters and the create-character action scope to it.

#### Scenario: Lotte creates her first character

- GIVEN Lotte's profile is linked to her portal account
- WHEN Lotte creates character "Wren" through the portal
- THEN "Wren" belongs to "Lotte Bakker"
- AND "Wren" appears in her portal list of characters

### Requirement: One profile per portal account (REQ-PSS-003)

A portal account MUST NOT create a second player profile; the second attempt
SHALL be refused with a message.

#### Scenario: A double click

- GIVEN Lotte already has a profile
- WHEN a second create arrives from her portal account
- THEN it is refused and no second player exists

### Requirement: Game masters review new players (REQ-PSS-004)

Game masters SHALL see a list of self-registered players not yet reviewed and
MUST be able to mark each as reviewed.

#### Scenario: A game master welcomes Lotte

- GIVEN "Lotte Bakker" registered herself
- WHEN a game master opens New players and marks her reviewed
- THEN she leaves the list and her player shows who reviewed her and when
