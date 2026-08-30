---
status: in-progress
---

# Portal Contribution — Delta

**Spec refs**: hydra ADR-046 (portaliq external portal) + contract v2.1 (subject scoped by a UUID row field; forward-compatible `scopeClaim`), ADR-005 (server-derived subject, never trust the client), ADR-022 (apps consume OR abstractions), ADR-032 (this change depends on `portal-identity`)
**Standards**: eIDAS assurance vocabulary for `minTrust` (`low` | `substantial` | `high`) — all surfaces here are `low`

## ADDED Requirements

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
