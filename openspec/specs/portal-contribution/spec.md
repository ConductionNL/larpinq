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

### Requirement: Dependency-Free Provider Discovery

Larpinq MUST expose exactly one portal contribution class at the convention FQCN `OCA\Larpinq\Portal\PortalContributionProvider`. The class MUST be plain and dependency-free: no portaliq imports, no `implements` clause, no info.xml dependency, no constructor dependencies — portaliq duck-types it via `method_exists()`, and without portaliq installed the class MUST be inert (Larpinq behaves exactly as before). It MUST implement both `getAudiences(): array` (contract v2) and `getAudience(): string` (contract v1 fallback returning the primary audience).

#### Scenario: Provider is discoverable and inert without portaliq

- **WHEN** the class `OCA\Larpinq\Portal\PortalContributionProvider` is constructed directly (no container, no portaliq)
- **THEN** construction MUST succeed without any portaliq class being loadable
- **AND** the class MUST declare no constructor parameters, extend nothing, implement nothing, and reference no portaliq symbol
- `@e2e exclude` discovery is portaliq-side; Larpinq-side inertness is pinned by direct-construction PHPUnit tests, there is no Larpinq UI for it

#### Scenario: Audiences advertised on both contract versions

- **WHEN** portaliq probes the provider
- **THEN** `getAudiences()` MUST return `['player']`
- **AND** `getAudience()` MUST return `'player'` for v1 registries
- `@e2e exclude` pure data contract with no UI in Larpinq; asserted by PHPUnit

### Requirement: Player Audience Contribution

For a subject with `audience = 'player'`, `getContribution()` MUST return a manifest whose collections are exactly `myCharacters` (schema `character`, `scopeField: ownerRef`, `scopeClaim: ownerRef`, field-projected), `events` (schema `event`, `scopeField: ''`), `skillCatalog`/`itemCatalog`/`conditionCatalog` (schemas `skill`/`item`/`condition`, `scopeField: ''`), and whose actions whitelist exactly one `create` action: `createCharacter` (schema `character`, `scopeField: ownerRef`) with fields `name`, `ocName`, `background`. Characters MUST be scoped by `ownerRef` (the uuid domain ref) and NEVER by `ownerUid` (a Nextcloud user id).

#### Scenario: Player sees own characters scoped by the domain ref

- **GIVEN** a resolved subject with `audience = 'player'`
- **WHEN** `getContribution($subject)` is called
- **THEN** the manifest MUST contain a `myCharacters` collection for schema `character` with `scopeField` `ownerRef`, `scopeClaim` `ownerRef`, register `larpingapp`, `listable: true`
- **AND** no collection may scope characters by `ownerUid`
- `@e2e exclude` portal rendering happens in portaliq, not Larpinq CI; the manifest shape + scoping key are pinned by PHPUnit against the register at HEAD

#### Scenario: Character reads drop every game-master-only column

- **GIVEN** the `myCharacters` collection
- **WHEN** its `fields` whitelist is inspected
- **THEN** it MUST NOT contain `approved`, `slNotesPrivate`, `notice`, `requirementOverrides`, `ownerUid`, or `ownerRef`
- **AND** it MUST contain the player's own non-secret columns (e.g. `name`, `ocName`, `description`, `slNotesPublic`)
- `@e2e exclude` field projection is declarative data enforced portaliq-side; the whitelist is pinned by PHPUnit, and read-projection availability is a documented portaliq dependency (design.md Risks)

#### Scenario: Public lists are explicitly unscoped and drop ownership

- **GIVEN** a resolved subject with `audience = 'player'`
- **WHEN** `getContribution($subject)` is called
- **THEN** the `events`, `skillCatalog`, `itemCatalog` and `conditionCatalog` collections MUST each declare `scopeField: ''` (explicit public list, not defaulted to `subjectRef`)
- **AND** `itemCatalog` and `conditionCatalog` MUST NOT project the `characters` ownership array, and `events` MUST NOT project `players` or `effects`
- `@e2e exclude` declarative manifest data; pinned by PHPUnit

#### Scenario: The only action is a conservative create-character whitelist

- **GIVEN** a resolved subject with `audience = 'player'`
- **WHEN** `getContribution($subject)` is called
- **THEN** the manifest actions MUST be exactly `create character` with fields `name`, `ocName`, `background` and `scopeField: ownerRef`
- **AND** no action field list may include `approved`, `slNotesPrivate`, `gold`, `silver`, `copper`, `ownerUid`, or any lifecycle property
- **AND** the manifest MUST declare `notifications: []` and MUST NOT contain a `kind: inbox` collection (Larpinq has no per-player message collection; event signup is delegated to Nextcloud Forms)
- `@e2e exclude` whitelist is declarative data enforced portaliq-side; pinned by PHPUnit

### Requirement: Fail-Closed Contribution

`getContribution()` MUST return `null` for any subject whose `audience` is not `player` (including a missing audience key), MUST branch only on server-derived subject data (`subjectRef`, `audience`, `organisation`, `trust`) — never on client-supplied input — and MUST declare no `endpoint` actions in this wave.

#### Scenario: Unknown or missing audience yields null

- **WHEN** `getContribution()` is called with `audience` `'client'`, `'supplier'`, an empty audience, or no audience key
- **THEN** it MUST return `null` in every case
- `@e2e exclude` negative-path data contract; asserted by PHPUnit

#### Scenario: No endpoint actions in this wave

- **WHEN** `getContribution()` is called for the `player` audience
- **THEN** every declared action MUST have `type = 'create'` (receiver-side assertion verification does not exist yet)
- `@e2e exclude` static manifest property; asserted by PHPUnit
