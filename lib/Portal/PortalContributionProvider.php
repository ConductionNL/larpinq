<?php

/**
 * Larpinq Portal Contribution Provider
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
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
	 * The instance-local receiver of the `createCharacter` endpoint action.
	 *
	 * @var string
	 */
	public const CREATE_CHARACTER_ENDPOINT = '/index.php/apps/larpinq/api/portal/characters';

	/**
	 * The portal menu group the larpinq pages sit in (board LrPortaal).
	 *
	 * @var string
	 */
	private const GROUP_CLUB = 'Verenigingen';

	/**
	 * The columns of the player's own character rows the portal may show:
	 * default-deny, so every game-master-only and identity column stays out.
	 *
	 * @var array<int, string>
	 */
	private const CHARACTER_FIELDS = [
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
	];

	/**
	 * The columns of the player's own registrations the portal may show:
	 * what the player needs to see and pay.
	 *
	 * @var array<int, string>
	 */
	private const REGISTRATION_FIELDS = [
		'event',
		'character',
		'status',
		'ticketType',
		'paymentState',
		'payBy',
		'paymentReference',
		'paymentLink',
		'submittedAt',
	];

	/**
	 * The columns of the player's own help questions the portal may show:
	 * what they asked and where it stands. The assignee and the internal
	 * identity fields stay behind (communication-help-desk REQ-CHD-004).
	 *
	 * @var array<int, string>
	 */
	private const QUESTION_FIELDS = [
		'subject',
		'body',
		'event',
		'character',
		'status',
		'lastMessageAt',
	];

	/**
	 * The columns of the messages under the player's own questions the portal
	 * may show: the answers and the player's own replies.
	 *
	 * @var array<int, string>
	 */
	private const QUESTION_MESSAGE_FIELDS = [
		'question',
		'fromOrganiser',
		'body',
		'createdAt',
	];

	/**
	 * The player's help page in the portal: their questions with the answers,
	 * and a new question (communication-help-desk REQ-CHD-004).
	 *
	 * @var array<string, mixed>
	 */
	private const HELP_PAGE = [
		'id' => 'help',
		'label' => 'Vragen aan de spelleiding',
		'group' => self::GROUP_CLUB,
		'icon' => 'HelpCircleOutline',
		'blocks' => [
			[
				'type' => 'richText',
				'markdown' => 'Stel hier uw vraag aan de spelleiding. U ziet uw vragen, de antwoorden en of een vraag nog open staat.',
			],
			['type' => 'collection', 'collection' => 'myQuestions'],
			['type' => 'detail', 'collection' => 'myQuestions'],
			['type' => 'collection', 'collection' => 'myQuestionMessages'],
			['type' => 'action', 'action' => 'askQuestion'],
		],
	];

	/**
	 * The player's own help questions, scoped by the `ownerRef` claim and
	 * projected to the question and its state (REQ-CHD-004).
	 *
	 * @var array<string, mixed>
	 */
	private const QUESTION_COLLECTION = [
		'id' => 'myQuestions',
		'register' => self::REGISTER,
		'schema' => 'larping_help_question',
		'scopeField' => 'ownerRef',
		'scopeClaim' => 'ownerRef',
		'label' => 'Mijn vragen',
		'listable' => true,
		'fields' => self::QUESTION_FIELDS,
		'columns' => [
			['field' => 'subject', 'label' => 'Onderwerp'],
			['field' => 'status', 'label' => 'Status', 'render' => 'badge'],
			['field' => 'lastMessageAt', 'label' => 'Laatste bericht', 'render' => 'date'],
		],
		'detail' => [
			'layout' => 'card',
			'fields' => ['subject', 'body', 'event', 'character', 'status', 'lastMessageAt'],
		],
		'defaultSort' => ['field' => 'lastMessageAt', 'direction' => 'desc'],
	];

	/**
	 * The messages under the player's own questions, scoped by the same
	 * `ownerRef` claim (REQ-CHD-004).
	 *
	 * @var array<string, mixed>
	 */
	private const QUESTION_MESSAGE_COLLECTION = [
		'id' => 'myQuestionMessages',
		'register' => self::REGISTER,
		'schema' => 'larping_help_message',
		'scopeField' => 'ownerRef',
		'scopeClaim' => 'ownerRef',
		'label' => 'Antwoorden',
		'listable' => true,
		'fields' => self::QUESTION_MESSAGE_FIELDS,
		'columns' => [
			['field' => 'body', 'label' => 'Bericht'],
			['field' => 'fromOrganiser', 'label' => 'Van de spelleiding', 'render' => 'badge'],
			['field' => 'createdAt', 'label' => 'Geschreven op', 'render' => 'date'],
		],
		'defaultSort' => ['field' => 'createdAt', 'direction' => 'asc'],
	];

	/**
	 * The portal notifications of the `player` audience: a player hears when
	 * the state of one of their questions changes (REQ-CHD-004).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const PLAYER_NOTIFICATIONS = [
		[
			'ruleKey' => 'larpinq.question.answered',
			'collection' => 'myQuestions',
			'on' => ['field' => 'status', 'operator' => 'changed'],
			'recipients' => ['field' => 'ownerRef', 'claim' => 'ownerRef'],
			'titleField' => 'subject',
		],
	];

	/**
	 * The portal's `askQuestion` action: a question to the game masters,
	 * stamped with the linked player's uuid through the `ownerRef` claim
	 * (REQ-CHD-004).
	 *
	 * @var array<string, mixed>
	 */
	private const ASK_QUESTION_ACTION = [
		'id' => 'askQuestion',
		'type' => 'create',
		'label' => 'Ask a question',
		'register' => self::REGISTER,
		'schema' => 'larping_help_question',
		'scopeField' => 'ownerRef',
		'scopeClaim' => 'ownerRef',
		'fields' => [
			'subject',
			'body',
			'event',
		],
	];

	/**
	 * The columns of a skill the portal may show, in the public skill
	 * catalogue and in the rulebook alike.
	 *
	 * @var array<int, string>
	 */
	private const SKILL_FIELDS = [
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
		'list',
	];

	/**
	 * The public read lists: events plus the skill, item and condition
	 * catalogs, each projected to its non-sensitive reference columns.
	 *
	 * @var array<int, array{id: string, schema: string, label: string, fields: array<int, string>}>
	 */
	private const PUBLIC_CATALOGS = [
		[
			'id' => 'events',
			'schema' => 'event',
			'label' => 'Events',
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
			'schema' => 'skill',
			'label' => 'Skills',
			'fields' => self::SKILL_FIELDS,
		],
		[
			'id' => 'itemCatalog',
			'schema' => 'item',
			'label' => 'Items',
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
			'schema' => 'condition',
			'label' => 'Conditions',
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
	 * `createCharacter` (forwarded to larpinq, which writes `ownerRef` and
	 * `ocName` with the player uuid from the account's claim, so the record is
	 * owned and played by the player). Only intake
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
			'pages' => [$this->myLarpPage(), $this->rulebookPage(), $this->helpPage()],
			'notifications' => $this->playerNotifications(),
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
	 * @spec openspec/specs/portal-contribution/spec.md#requirement-the-player-has-a-mijn-larp-page-in-the-portal
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
	 * @spec openspec/specs/portal-contribution/spec.md#requirement-the-player-reads-the-rulebook-in-the-portal
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
				['type' => 'collection', 'collection' => 'skillLists'],
				['type' => 'collection', 'collection' => 'rulebookSkills'],
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
			[
				$this->profileCollection(),
				$this->registrationCollection(),
				$this->rulebookCollection(),
				$this->skillListCollection(),
				$this->rulebookSkillCollection(),
			],
			[$this->questionCollection(), $this->questionMessageCollection()]
		);
	}//end playerCollections()

	/**
	 * The player's help page in the portal: their questions with the answers,
	 * and a new question (communication-help-desk REQ-CHD-004).
	 *
	 * @return array<string, mixed> The page.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-portal-players-use-the-help-desk-too-req-chd-004
	 */
	private function helpPage(): array {
		return self::HELP_PAGE;
	}//end helpPage()

	/**
	 * The player's own help questions, scoped by the `ownerRef` claim.
	 *
	 * `helpQuestion.ownerRef` holds the asking player's uuid, the value the
	 * account's `ownerRef` claim carries, so the scoping matches
	 * `myCharacters`. Projected to the question and its state.
	 *
	 * @return array<string, mixed> The owner-scoped question collection.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-portal-players-use-the-help-desk-too-req-chd-004
	 */
	private function questionCollection(): array {
		return self::QUESTION_COLLECTION;
	}//end questionCollection()

	/**
	 * The messages under the player's own questions: the answers of the game
	 * masters and the player's replies, scoped by the same `ownerRef` claim
	 * (HelpMessageListener copies the question's `ownerRef` onto each message).
	 *
	 * @return array<string, mixed> The owner-scoped message collection.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-portal-players-use-the-help-desk-too-req-chd-004
	 */
	private function questionMessageCollection(): array {
		return self::QUESTION_MESSAGE_COLLECTION;
	}//end questionMessageCollection()

	/**
	 * The portal notifications of the `player` audience: a portal player hears
	 * when the state of one of their questions changes, for instance when a
	 * game master answers it (REQ-CHD-004).
	 *
	 * `myQuestions` is read through the `ownerRef` claim, so the rule names
	 * its recipients by that claim: the accounts whose `ownerRef` claim equals
	 * the question's `ownerRef`.
	 *
	 * @return array<int, array<string, mixed>> The declared notification rules.
	 *
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-portal-players-use-the-help-desk-too-req-chd-004
	 */
	private function playerNotifications(): array {
		return self::PLAYER_NOTIFICATIONS;
	}//end playerNotifications()

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
	 * @spec openspec/specs/portal-contribution/spec.md#requirement-the-player-has-a-mijn-larp-page-in-the-portal
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
			'fields' => self::REGISTRATION_FIELDS,
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
	 * The skill lists of the rulebook: skills, spells and powers with the pool they are paid from.
	 *
	 * Public like the skill catalogue, whose `list` field says which list a
	 * skill is in. A list hidden from the rulebook is left out by the filter;
	 * the flag defaults to false on every list, which is a schema of this change.
	 *
	 * @return array<string, mixed> The skill list collection.
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-the-rulebook-is-in-the-portal-req-rrs-005
	 */
	private function skillListCollection(): array {
		return [
			'id' => 'skillLists',
			'register' => self::REGISTER,
			'schema' => 'larping_skill_list',
			'scopeField' => '',
			'filter' => ['hiddenFromRulebook' => false],
			'label' => 'Vaardighedenlijsten',
			'listable' => true,
			'fields' => [
				'name',
				'kind',
				'description',
				'costAbility',
				'order',
				'setting',
			],
			'columns' => [
				['field' => 'name', 'label' => 'Lijst'],
				['field' => 'kind', 'label' => 'Soort'],
			],
			'defaultSort' => ['field' => 'order', 'direction' => 'asc'],
		];
	}//end skillListCollection()

	/**
	 * The skills of the rulebook, without the ones hidden from it (REQ-RRS-004).
	 *
	 * The rulebook page lists these instead of the public skill catalogue,
	 * which stays unfiltered because it resolves the skill references on a
	 * player's own characters. The flag defaults to false on every skill.
	 *
	 * @return array<string, mixed> The rulebook skill collection.
	 *
	 * @spec openspec/changes/rules-rulebook-and-spell-lists/specs/rulebook/spec.md#requirement-secret-rules-stay-out-of-the-rulebook-req-rrs-004
	 */
	private function rulebookSkillCollection(): array {
		return [
			'id' => 'rulebookSkills',
			'register' => self::REGISTER,
			'schema' => 'larping_skill',
			'scopeField' => '',
			'filter' => ['hiddenFromRulebook' => false],
			'label' => 'Vaardigheden',
			'listable' => true,
			'fields' => self::SKILL_FIELDS,
			'columns' => [
				['field' => 'name', 'label' => 'Vaardigheid'],
				['field' => 'list', 'label' => 'Lijst'],
			],
			'defaultSort' => ['field' => 'name', 'direction' => 'asc'],
		];
	}//end rulebookSkillCollection()

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
			'fields' => self::CHARACTER_FIELDS,
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
		return array_map($this->publicList(...), self::PUBLIC_CATALOGS);
	}//end catalogCollections()

	/**
	 * One public, unscoped read list in this register.
	 *
	 * @param array{id: string, schema: string, label: string, fields: array<int, string>} $catalog One PUBLIC_CATALOGS entry.
	 *
	 * @return array<string, mixed> The declarative collection.
	 */
	private function publicList(array $catalog): array {
		return [
			'id' => $catalog['id'],
			'register' => self::REGISTER,
			'schema' => $catalog['schema'],
			'scopeField' => '',
			'label' => $catalog['label'],
			'listable' => true,
			'fields' => $catalog['fields'],
		];
	}//end publicList()

	/**
	 * The whitelisted create-action the `player` audience may perform.
	 *
	 * `createCharacter`: an endpoint action. portaliq resolves the account's
	 * `ownerRef` claim (the player uuid larpinq records when the profile is
	 * made) and forwards the form to PortalCharacterController with a signed
	 * assertion carrying that claim; without the claim nothing is forwarded
	 * (fail closed). The receiver writes the player uuid into BOTH `ownerRef`
	 * and the required `ocName`, so the character is played by the player who
	 * made it. A declarative create could stamp `ownerRef` only, and
	 * OpenRegister refuses the missing `ocName` before PortalProfileListener
	 * can fill it; that listener stays as a fallback. The client sends only
	 * `name` and `background`.
	 *
	 * `createPlayerProfile`: a signed-up visitor makes their own player, stamped
	 * with their portal subject; PortalProfileListener refuses a second one and
	 * asks portaliq for the claim.
	 *
	 * `askQuestion`: a question to the game masters, stamped with the linked
	 * player's uuid through the `ownerRef` claim; HelpMessageListener names that
	 * player on the question (communication-help-desk REQ-CHD-004). Status,
	 * assignee and the asker's account stay server-authoritative.
	 *
	 * Only intake fields are accepted: no game-master, economy, lifecycle or
	 * review property. Event signup is delegated to Nextcloud Forms and is not
	 * duplicated here; there are no `endpoint` actions in this wave.
	 *
	 * @return array<int, array<string, mixed>> The declarative create actions.
	 *
	 * @spec openspec/changes/portal-contribution/specs/portal-contribution/spec.md
	 * @spec openspec/specs/portal-contribution/spec.md
	 * @spec openspec/changes/communication-help-desk/specs/help-desk/spec.md#requirement-portal-players-use-the-help-desk-too-req-chd-004
	 */
	private function playerActions(): array {
		return [
			[
				'id' => 'createCharacter',
				'label' => 'Create a character',
				'endpoint' => self::CREATE_CHARACTER_ENDPOINT,
				'method' => 'POST',
				'scopeClaim' => 'ownerRef',
				'fields' => [
					'name',
					'background',
				],
				'fieldConfigs' => [
					'name' => ['label' => 'Character name', 'required' => true],
					'background' => ['label' => 'Background story', 'size' => 'large'],
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
			self::ASK_QUESTION_ACTION,
		];
	}//end playerActions()
}//end class
