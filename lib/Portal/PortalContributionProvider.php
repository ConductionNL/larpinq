<?php

/**
 * Larpinq Portal Contribution Provider
 *
 * Larpinq's contribution to the shared portaliq external portal (hydra
 * ADR-046 + contract v2.1). Portaliq — the ONE shared portal for people
 * WITHOUT Nextcloud accounts — discovers this class by convention FQCN
 * (`OCA\{Namespace}\Portal\PortalContributionProvider`) and duck-types it via
 * method_exists(), never instanceof. This class is therefore deliberately
 * PLAIN: no portaliq imports, no `implements` clause, no info.xml dependency,
 * no constructor dependencies. Without portaliq installed it is inert and
 * larpinq behaves exactly as before.
 *
 * It declares — for the single `player` audience — the OpenRegister
 * collections a portal subject may read and the whitelisted create-action they
 * may perform. The subject is scoped EXCLUSIVELY by `character.ownerRef` (the
 * owning player's `player` object UUID, added by the sibling `portal-identity`
 * config change), NEVER by `character.ownerUid` (a Nextcloud uid, which an
 * account-less external subject does not have — the ADR-005 / A4 rule). See
 * openspec/changes/portal-contribution/design.md.
 *
 * @category Portal
 * @package  OCA\Larpinq\Portal
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Portal;

/**
 * Declares what an external portal subject may see and do in larpinq.
 *
 * The contribution is a declarative manifest (pure data — no I/O, no
 * callbacks). All subject identity (subjectRef, audience, organisation, trust)
 * is derived server-side by portaliq's auth edge and MUST never be trusted
 * from the client (ADR-005). Scoping uses a UUID domain ref (`ownerRef` = the
 * player object UUID) — never a Nextcloud user id, because externals have no
 * Nextcloud account by premise.
 *
 * Read collections that expose owner-scoped or catalog rows ship an explicit
 * `fields` whitelist (default-deny projection): the player's own `character`
 * rows drop every game-master-only column, and the public catalogs drop
 * per-object ownership arrays. Field projection is enforced portaliq-side
 * (contract v2.1 read projection); the whitelist is the declarative contract.
 *
 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
 */
class PortalContributionProvider {
	/**
	 * The OpenRegister register slug every collection below lives in.
	 *
	 * ⚠️ FROZEN ON THE OLD APP ID, DELIBERATELY. This is the OpenRegister
	 * REGISTER SLUG, not the app id — it did not move with the
	 * `larpingapp` -> `larpinq` rename. Renaming it here would point every
	 * portal collection at a register that does not exist, and the portal
	 * surface would go silently empty rather than error. Leave it.
	 *
	 * @var string
	 */
	private const REGISTER = 'larpinq';

	/**
	 * The portal menu group the larpinq pages sit in (board LrPortaal).
	 *
	 * @var string
	 */
	private const GROUP_CLUB = 'Verenigingen';

	/**
	 * The audiences this provider contributes to (contract v2, preferred).
	 *
	 * The registry probes for this method first. Larpinq serves exactly one
	 * external audience: `player` (a real-world LARP player without a Nextcloud
	 * account).
	 *
	 * @return array<int, string> The audience identifiers.
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function getAudiences(): array {
		return ['player'];
	}//end getAudiences()

	/**
	 * The primary audience this provider contributes to (contract v1 fallback).
	 *
	 * Kept alongside getAudiences() so the provider also works against a v1
	 * registry that predates multi-audience support.
	 *
	 * @return string The primary audience identifier.
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function getAudience(): string {
		return 'player';
	}//end getAudience()

	/**
	 * Build the declarative portal manifest for one resolved subject.
	 *
	 * The subject array is server-derived by portaliq (subjectRef UUID,
	 * audience, organisation, trust level low|substantial|high). Returns null
	 * for any audience larpinq does not serve (fail-closed; the registry
	 * already filters by audience, but a provider must not rely on that). This
	 * wave declares one create-action only — no endpoint actions (receiver-side
	 * assertion verification does not exist yet).
	 *
	 * @param array<string, mixed> $subject The resolved portal subject.
	 *
	 * @return array<string, mixed>|null The manifest, or null when not contributing.
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	public function getContribution(array $subject): ?array {
		$audience = ($subject['audience'] ?? '');

		if ($audience === 'player') {
			return $this->playerContribution();
		}

		return null;
	}//end getContribution()

	/**
	 * Manifest for the `player` audience (a LARP player without a NC account).
	 *
	 * Read surfaces:
	 * - `myCharacters` — the subject's own characters, scoped by `ownerRef`
	 *   (`scopeClaim: ownerRef`, the player object UUID = the subject ref).
	 *   Field-projected: every game-master-only column (`approved`,
	 *   `slNotesPrivate`, `notice`, `requirementOverrides`) and the internal
	 *   identity fields (`ownerUid`, `ownerRef`) are dropped.
	 * - `events` — a public event list (`scopeField: ''` = no per-subject
	 *   scoping), projected to public columns (drops the participant roster
	 *   `players` and the post-event `effects` mechanics).
	 * - `skillCatalog` / `itemCatalog` / `conditionCatalog` — public game
	 *   reference data so a player can resolve the UUID refs on their own
	 *   character. The item/condition catalogs drop the per-object `characters`
	 *   ownership array.
	 *
	 * Create surface: `createPlayerProfile` (a new player's own profile) and
	 * `createCharacter` (the writer stamps `ownerRef` with the player uuid from
	 * the account's claim, so the record is owned by the player). Only intake
	 * fields are accepted; every game-master / economy / lifecycle field stays
	 * server-authoritative. See playerActions().
	 *
	 * Event signup is delegated to Nextcloud Forms today
	 * (`register.d/event-signup-to-forms-leaf.json`, `linkedTypes: ["forms"]`)
	 * and is deliberately NOT duplicated here.
	 *
	 * XP awards are intentionally NOT exposed — see design.md (the shipped
	 * portaliq reader is single-hop `scopeField == subjectRef`, and `xpAward`
	 * carries no field equal to the player ref; scoping it would need a
	 * denormalised owner ref + backfill, deferred on ConductionNL/larpinq#51).
	 *
	 * @return array<string, mixed> The player manifest.
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	private function playerContribution(): array {
		return [
			'label' => 'Larpinq',
			'collections' => $this->playerCollections(),
			'actions' => $this->playerActions(),
			'pages' => [$this->myLarpPage(), $this->rulebookPage()],
			'notifications' => [],
		];
	}//end playerContribution()

	/**
	 * The player's own page in the portal (board LrPortaal): their
	 * characters, a new character, and their registrations with the payment
	 * state.
	 *
	 * Contribution-manifest-v3 page composition: every block names a
	 * collection or an action of this contribution.
	 *
	 * @return array<string, mixed> The page.
	 *
	 * @spec openspec/changes/portal-my-larp-pages/specs/portal-contribution/spec.md#requirement-the-player-has-a-my-larp-page-in-the-portal
	 */
	private function myLarpPage(): array {
		return [
			'id' => 'myLarp',
			'label' => 'Mijn larp',
			'group' => self::GROUP_CLUB,
			'icon' => 'Sword',
			'blocks' => [
				[
					'type' => 'richText',
					'markdown' => 'Hier staan uw karakters en uw aanmeldingen voor evenementen.',
				],
				['type' => 'collection', 'collection' => 'myCharacters'],
				['type' => 'detail', 'collection' => 'myCharacters'],
				['type' => 'action', 'action' => 'createCharacter'],
				['type' => 'collection', 'collection' => 'myRegistrations'],
				['type' => 'detail', 'collection' => 'myRegistrations'],
			],
		];
	}//end myLarpPage()

	/**
	 * The rulebook in the portal (board LrPortaalRegelboek): the rules
	 * chapters, then the skills with their costs and requirements.
	 *
	 * @return array<string, mixed> The page.
	 *
	 * @spec openspec/changes/portal-my-larp-pages/specs/portal-contribution/spec.md#requirement-the-player-reads-the-rulebook-in-the-portal
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md
	 */
	private function rulebookPage(): array {
		return [
			'id' => 'rulebook',
			'label' => 'Regelboek',
			'group' => self::GROUP_CLUB,
			'icon' => 'BookOpenVariant',
			'blocks' => [
				[
					'type' => 'richText',
					'markdown' => 'De spelregels, gepubliceerd door de spelleiding. Wat hier staat, geldt op elk evenement.',
				],
				['type' => 'collection', 'collection' => 'rulebookChapters'],
				['type' => 'detail', 'collection' => 'rulebookChapters'],
				['type' => 'collection', 'collection' => 'skillCatalog'],
			],
		];
	}//end rulebookPage()

	/**
	 * The read collections the `player` audience may list.
	 *
	 * `myCharacters` is scoped by `ownerRef` and field-projected; the remaining
	 * four are public lists (`scopeField: ''`) projected to non-sensitive
	 * reference columns. See the class docblock and design.md for the full
	 * include/exclude rationale.
	 *
	 * @return array<int, array<string, mixed>> The declarative collections.
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	private function playerCollections(): array {
		return array_merge(
			[$this->characterCollection()],
			$this->catalogCollections(),
			[$this->profileCollection(), $this->registrationCollection(), $this->rulebookCollection()]
		);
	}//end playerCollections()

	/**
	 * The player's own registrations, scoped by the `player` reference.
	 *
	 * `registration.player` holds the player object uuid, the same value the
	 * account's `ownerRef` claim carries, so the scoping matches
	 * `myCharacters`. Projected to what the player needs to see and pay: the
	 * event, the character they bring, the status and the payment fields.
	 * The game master's decision stamps and the transfer bookkeeping stay
	 * behind.
	 *
	 * @return array<string, mixed> The owner-scoped registration collection.
	 *
	 * @spec openspec/changes/portal-my-larp-pages/specs/portal-contribution/spec.md#requirement-the-player-has-a-my-larp-page-in-the-portal
	 */
	private function registrationCollection(): array {
		return [
			'id' => 'myRegistrations',
			'register' => self::REGISTER,
			'schema' => 'larping_registration',
			'scopeField' => 'player',
			'scopeClaim' => 'ownerRef',
			'label' => 'Mijn aanmeldingen',
			'listable' => true,
			'fields' => [
				'event',
				'character',
				'status',
				'ticketType',
				'paymentState',
				'payBy',
				'paymentReference',
				'paymentLink',
				'submittedAt',
			],
			'columns' => [
				['field' => 'event', 'label' => 'Evenement'],
				['field' => 'status', 'label' => 'Status', 'render' => 'badge'],
				['field' => 'paymentState', 'label' => 'Betaling', 'render' => 'badge'],
				['field' => 'payBy', 'label' => 'Betalen voor', 'render' => 'date'],
			],
			'detail' => [
				'layout' => 'card',
				'fields' => ['event', 'character', 'status', 'ticketType', 'paymentState', 'payBy', 'paymentReference', 'paymentLink'],
			],
			'defaultSort' => ['field' => 'submittedAt', 'direction' => 'desc'],
		];
	}//end registrationCollection()

	/**
	 * The rules chapters: lore pages of category `rules` that players may
	 * read.
	 *
	 * A public list (`scopeField: ''`), narrowed to `visibility: players`.
	 * A page with a reveal moment is left out (`revealFrom` must be empty):
	 * the portal filter cannot compare it with the current time, so a chapter
	 * the game masters hold back until a date stays out of the portal rather
	 * than appearing early.
	 *
	 * @return array<string, mixed> The rulebook chapter collection.
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md
	 */
	private function rulebookCollection(): array {
		return [
			'id' => 'rulebookChapters',
			'register' => self::REGISTER,
			'schema' => 'larping_lore_page',
			'scopeField' => '',
			'filter' => [
				'category' => 'rules',
				'visibility' => 'players',
				'revealFrom' => 'IS NULL',
			],
			'label' => 'Regelboek',
			'listable' => true,
			'fields' => [
				'title',
				'body',
				'setting',
				'parent',
				'order',
			],
			'columns' => [
				['field' => 'title', 'label' => 'Hoofdstuk'],
			],
			'detail' => ['layout' => 'card', 'fields' => ['title', 'body']],
			'defaultSort' => ['field' => 'order', 'direction' => 'asc'],
		];
	}//end rulebookCollection()

	/**
	 * The subject's own player profile, scoped by the portal subject itself.
	 *
	 * `portalSubjectRef` is stamped by portaliq's writer on
	 * `createPlayerProfile`, so no claim is needed to find it: the profile is
	 * how the claim comes to exist. Projected to what the player typed.
	 *
	 * @return array<string, mixed> The subject-scoped profile collection.
	 *
	 * @spec openspec/specs/portal-contribution/spec.md
	 */
	private function profileCollection(): array {
		return [
			'id' => 'myProfile',
			'register' => self::REGISTER,
			'schema' => 'player',
			'scopeField' => 'portalSubjectRef',
			'label' => 'My profile',
			'listable' => true,
			'fields' => [
				'name',
				'description',
			],
		];
	}//end profileCollection()

	/**
	 * The subject's own characters — scoped by `ownerRef`, field-projected.
	 *
	 * Scoped EXCLUSIVELY by the `ownerRef` uuid domain ref (never `ownerUid`).
	 * The `fields` whitelist is default-deny: every game-master-only column
	 * (`approved`, `slNotesPrivate`, `notice`, `requirementOverrides`) and the
	 * internal identity fields (`ownerUid`, `ownerRef`) are dropped.
	 *
	 * @return array<string, mixed> The owner-scoped character collection.
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	private function characterCollection(): array {
		return [
			'id' => 'myCharacters',
			'register' => self::REGISTER,
			'schema' => 'character',
			'scopeField' => 'ownerRef',
			'scopeClaim' => 'ownerRef',
			'label' => 'My characters',
			'listable' => true,
			'fields' => [
				'name',
				'ocName',
				'description',
				'type',
				'faith',
				'gold',
				'silver',
				'copper',
				'card',
				'itemsAndMoney',
				'slNotesPublic',
				'background',
				'skills',
				'items',
				'conditions',
				'events',
				'setting',
			],
		];
	}//end characterCollection()

	/**
	 * The public read lists — events plus the skill/item/condition catalogs.
	 *
	 * Each declares an explicit empty `scopeField` (the controller otherwise
	 * defaults to `subjectRef` and would match nothing). `events` drops the
	 * participant roster (`players`) and post-event `effects`; the item and
	 * condition catalogs drop the per-object `characters` ownership array.
	 *
	 * @return array<int, array<string, mixed>> The public collections.
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 */
	private function catalogCollections(): array {
		return [
			[
				'id' => 'events',
				'register' => self::REGISTER,
				'schema' => 'event',
				'scopeField' => '',
				'label' => 'Events',
				'listable' => true,
				'fields' => [
					'name',
					'description',
					'startDate',
					'endDate',
					'location',
					'setting',
				],
			],
			[
				'id' => 'skillCatalog',
				'register' => self::REGISTER,
				'schema' => 'skill',
				'scopeField' => '',
				'label' => 'Skills',
				'listable' => true,
				'fields' => [
					'name',
					'description',
					'effect',
					'effects',
					'requiredSkills',
					'requiredStats',
					'requiredConditions',
					'requiredEffects',
					'requiredScore',
					'setting',
				],
			],
			[
				'id' => 'itemCatalog',
				'register' => self::REGISTER,
				'schema' => 'item',
				'scopeField' => '',
				'label' => 'Items',
				'listable' => true,
				'fields' => [
					'name',
					'description',
					'effect',
					'effects',
					'unique',
					'setting',
				],
			],
			[
				'id' => 'conditionCatalog',
				'register' => self::REGISTER,
				'schema' => 'condition',
				'scopeField' => '',
				'label' => 'Conditions',
				'listable' => true,
				'fields' => [
					'name',
					'description',
					'effect',
					'effects',
					'unique',
					'setting',
				],
			],
		];
	}//end catalogCollections()

	/**
	 * The whitelisted create-action the `player` audience may perform.
	 *
	 * `createCharacter`: the writer stamps `ownerRef` with the account's
	 * `ownerRef` claim (the player uuid larpinq records when the profile is
	 * made), never with the subject reference, which is not a uuid and which
	 * `character.ownerRef` refuses. Without the claim the create is refused
	 * (fail closed). `ocName` is not accepted: PortalProfileListener sets it to
	 * the owner, so the character is played by the player who made it.
	 *
	 * `createPlayerProfile`: a signed-up visitor makes their own player, stamped
	 * with their portal subject; PortalProfileListener refuses a second one and
	 * asks portaliq for the claim.
	 *
	 * Only intake fields are accepted: no game-master, economy, lifecycle or
	 * review property. Event signup is delegated to Nextcloud Forms and is not
	 * duplicated here; there are no `endpoint` actions in this wave.
	 *
	 * @return array<int, array<string, mixed>> The declarative create actions.
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 * @spec openspec/specs/portal-contribution/spec.md
	 */
	private function playerActions(): array {
		return [
			[
				'id' => 'createCharacter',
				'type' => 'create',
				'label' => 'Create a character',
				'register' => self::REGISTER,
				'schema' => 'character',
				'scopeField' => 'ownerRef',
				'scopeClaim' => 'ownerRef',
				'fields' => [
					'name',
					'background',
				],
			],
			[
				'id' => 'createPlayerProfile',
				'type' => 'create',
				'label' => 'Create your player profile',
				'register' => self::REGISTER,
				'schema' => 'player',
				'scopeField' => 'portalSubjectRef',
				'fields' => [
					'name',
					'description',
				],
			],
		];
	}//end playerActions()
}//end class
