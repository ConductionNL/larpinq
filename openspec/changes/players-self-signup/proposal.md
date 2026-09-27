---
kind: code
depends_on: []
---

# Proposal: players-self-signup

## Summary

A new player hears about the campaign and wants to join. In larpinq a game
master has to create a Nextcloud account and a player record for them first.
The fleet already has a place where people without a Nextcloud account sign
up: the portaliq portal, which larpinq contributes to (hydra ADR-046). This
change adds the missing step there: a signed-up portal visitor creates their
own player profile in larpinq, larpinq links that profile to their portal
account, and from then on they see their characters and can create one.
Game masters see self-registered players flagged for review.

## Motivation

One players row of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26). The OpenSpec pass
of 2026-09-27 decided `build`: five competitors rate it yes.

**`ply-self-signup`**, "Let a new player create their own account and
profile." Larpinq rates it no. Matrix evidence: "grep -rniE
'self.?sign.?up|self-registration|registration form' lib src openspec: no hits.
Player/user provisioning is standard Nextcloud (admin-created accounts) plus
larpinq's own player records created by whoever has app access; no public
sign-up flow exists".

- LarpManager: "larpmanager/urls/user.py login, password_reset and profile routes, larpmanager/utils/auth/adapter.py social signup adapter, larpmanager/tests/unit/test_user_registration.py" (source read at main 36f23d3).
- MyLARP: "Live 'Create Account' form (email, first/last name, password) seen at https://cerroneth.mylarp.com/"
- LARP Portal: "Free 'Registered' tier lets anyone create a LARP Portal account. https://larportal.com"
- Kanka: "routes/auth.php:9,32-33 self registration (config auth.register_enabled); user profile routes/settings.php:37-39; campaign application routes/campaigns/campaign.php:51-53" (source read at tag 3.15).
- pretix: "src/pretix/presale/views/customer.py:208 RegistrationView at src/pretix/presale/urls.py:211 account/register lets a player create their own account; profiles are kept via src/pretix/base/models/customers.py:336 AttendeeProfile" (source read at tag v2026.7.0).

## Affected Projects

- [ ] Project: `larpinq`: a create-profile action and a my-profile collection in the portal contribution, a portal reference on the player, the claim that links the two, and a review flag for game masters.
- [ ] Project: `portaliq` (not specified here): the account itself (self-registration, activation) and the typed claim event, both portaliq's.

## Scope

### In Scope

- A `createPlayerProfile` action for the portal `player` audience: name, description (how others know you), and the portal subject stamped on the new player.
- A `myProfile` read collection scoped to the subject's own player.
- On creation, larpinq asks portaliq to record the claim `larpinq.ownerRef` = the new player's id on the subject's portal account, so the existing `myCharacters` collection and `createCharacter` action work for them.
- `player.selfRegistered` and `player.reviewedAt`: game masters see a "New players" list and mark each reviewed.
- One player profile per portal account: a second create is refused.

### Out of Scope

- The account, password, activation mail and login: portaliq's (`id-self-registration-form`, its ways-in screens).
- Nextcloud accounts for players: a player who wants the full larpinq pages is still given an account by an administrator.
- Care details on the profile (allergies, emergency contact): `players-care-details-and-erasure`.

## Approach

Extend `PortalContributionProvider` with the action and collection, following
the existing `createCharacter` action and `myCharacters` collection. A
post-write listener on self-registered players dispatches portaliq's claim
event (hydra ADR-041). Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Portal/PortalContributionProvider.php`: `createPlayerProfile`, `myProfile`.
- `lib/Settings/register.d/players-self-signup.json` (new): `player.portalSubjectRef`, `selfRegistered`, `reviewedAt`.
- `lib/Listener/PlayerProfileClaimListener.php` (new), registered in `lib/AppInfo/Application.php`.
- `src/manifest.d/players-self-signup.json` (new): the New players page.

## Cross-Project Dependencies

portaliq (ADR-046 contract, `openspec/specs/...` contract v2.1 on portaliq
development): create actions with a writer-stamped `scopeField`, `scopeClaim`
collections resolved from `portalAccount.claims`, and self-registration
(`POST /portal/api/identity/register`, change `identity-ways-in-screens`).
The claim write by an owning app through a typed event with a result slot is
in portaliq's open change `portal-identity-space`; this change dispatches that
event and waits for it to land.

## Risks

### Risk 1: Spam profiles
**Severity:** Medium. **Mitigation:** self-registered players cannot read anything but their own profile and characters (portal scoping), cannot register for events until a game master reviews them when the event requires approval, and the New players list makes review a routine step.

### Risk 2: The claim event is not there yet
**Severity:** Medium. **Mitigation:** without the claim, `myCharacters` stays empty (the contract's fail-closed rule); the profile still exists and a game master can link it by hand.

## Rollback Strategy

Remove the action and collection from the provider; profiles stay as players.

## Open Questions

None.
