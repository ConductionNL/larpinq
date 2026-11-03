<?php

/**
 * CharacterService for Larpinq
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-64
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-65
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-66
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-67
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-68
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-69
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-70
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-71
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-72
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-73
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-74
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-75
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-76
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-77
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-78
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-79
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Service class for character-related operations.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-64
 */
class CharacterService {

	/**
	 * All skills indexed by ID.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $allSkills = [];

	/**
	 * All items indexed by ID.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $allItems = [];

	/**
	 * All conditions indexed by ID.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $allConditions = [];

	/**
	 * All events indexed by ID.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $allEvents = [];

	/**
	 * All effects indexed by ID.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $allEffects = [];

	/**
	 * All abilities indexed by ID.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $allAbilities = [];

	/**
	 * All XP awards grouped by the character they were granted to.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $xpAwardsByCharacter = [];

	/**
	 * The awards on the signed-in player's own characters, read with the app's
	 * authority, by character id (DECISIONS row 30).
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $ownXpAwards = [];

	/**
	 * The audit source name for each character relation the engine walks.
	 *
	 * @var array<string,string>
	 */
	private const STAGE_SOURCE = [
		'skills' => 'skill',
		'items' => 'item',
		'conditions' => 'condition',
		'events' => 'event',
	];

	/**
	 * Flag indicating whether entity collections have been loaded.
	 *
	 * @var boolean
	 */
	private bool $entitiesLoaded = false;

	/**
	 * Constructor for CharacterService.
	 *
	 * Entity collections are NOT loaded here. Loading is deferred until
	 * calculateCharacter() is first called, so DI resolution of this service
	 * does not issue 6 OR queries unless stat calculation is actually needed.
	 * Closes #217.
	 *
	 * @param RegisterObjectFetcher $objectFetcher The register object fetcher.
	 * @param LoggerInterface $logger The logger interface.
	 * @param EffectApplier $effectApplier The per-effect modifier arithmetic.
	 * @param CharacterConnectionGuard|null $connectionGuard Decides whether the signed-in user plays a character.
	 * @param IUserSession|null $userSession The signed-in user. Without it, or the guard, awards are read as the user only.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly LoggerInterface $logger,
		private readonly EffectApplier $effectApplier,
		private readonly ?CharacterConnectionGuard $connectionGuard = null,
		private readonly ?IUserSession $userSession = null,
	) {
	}//end __construct()

	/**
	 * Index an array of entities by their ID field.
	 *
	 * @param array $entities The entities to index.
	 *
	 * @return array<string, array<string, mixed>> Entities indexed by ID.
	 */
	private function indexById(array $entities): array {
		// @var array<string, array<string, mixed>> $indexed
		$indexed = [];
		// @psalm-suppress MixedAssignment Entity entries from object fetcher
		foreach ($entities as $entity) {
			$indexed[(string)$entity['id']] = $entity;
		}

		return $indexed;
	}//end indexById()

	/**
	 * Load all entities into memory and index them by ID.
	 *
	 * Guarded by $entitiesLoaded so the 6 OR queries are only issued once per
	 * service instance and only when a calculation is actually requested.
	 * Closes #217.
	 *
	 * @return void
	 */
	private function loadAllEntities(): void {
		if ($this->entitiesLoaded === true) {
			return;
		}

		$this->allSkills = $this->indexById(entities: $this->objectFetcher->getObjects('skill'));
		$this->allItems = $this->indexById(entities: $this->objectFetcher->getObjects('item'));
		$this->allConditions = $this->indexById(entities: $this->objectFetcher->getObjects('condition'));
		$this->allEvents = $this->indexById(entities: $this->objectFetcher->getObjects('event'));
		$this->allEffects = $this->indexById(entities: $this->objectFetcher->getObjects('effect'));
		$this->allAbilities = $this->indexById(entities: $this->objectFetcher->getObjects('ability'));
		$this->xpAwardsByCharacter = $this->loadXpAwards();
		$this->entitiesLoaded = true;
	}//end loadAllEntities()

	/**
	 * Load XP awards and group them by the character they were granted to.
	 *
	 * The xpAward schema is optional: older deployments without it (or without
	 * OpenRegister) simply yield no awards. Never throws — a missing schema
	 * degrades to "no awards" so stat calculation keeps working (CALC-006).
	 *
	 * @return array<string, array<int, array<string, mixed>>> Awards keyed by character id.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	private function loadXpAwards(): array {
		try {
			$awards = $this->objectFetcher->getObjects('xpAward');
		} catch (\Throwable $e) {
			$this->logger->debug(
				'Larpinq: xpAward schema unavailable; stat calculation proceeds with no awards.',
				['exception' => $e]
			);
			return [];
		}

		return $this->groupByCharacter(awards: $awards);
	}//end loadXpAwards()

	/**
	 * Group awards by the character they were granted to, skipping entries without one.
	 *
	 * @param array<int|string, mixed> $awards The awards as read.
	 *
	 * @return array<string, array<int, array<string, mixed>>> Awards keyed by character id.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	private function groupByCharacter(array $awards): array {
		$grouped = [];
		// @psalm-suppress MixedAssignment Award entries from the object fetcher.
		foreach ($awards as $award) {
			if (is_array($award) === false) {
				continue;
			}

			$characterId = (string)($award['character'] ?? '');
			if ($characterId !== '') {
				$grouped[$characterId][] = $award;
			}
		}

		return $grouped;
	}//end groupByCharacter()

	/**
	 * Resolve the XP ability id from the loaded abilities by name.
	 *
	 * No hardcoded UUIDs: matches an ability whose name is "xp" or contains
	 * "experience" (case-insensitive). Shared resolution rule with
	 * skill-requirement-enforcement.
	 *
	 * @return string|null The XP ability id, or null when none resolves.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	private function resolveXpAbilityId(): ?string {
		foreach ($this->allAbilities as $abilityId => $ability) {
			$name = strtolower((string)($ability['name'] ?? ''));
			if ($name === 'xp' || str_contains($name, 'experience') === true) {
				return (string)$abilityId;
			}
		}

		return null;
	}//end resolveXpAbilityId()

	/**
	 * Calculate stats for all characters.
	 *
	 * @return array Updated array of Character objects.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Public API for batch character stat calculation.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-64
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-65
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-66
	 */
	public function calculateAllCharacters(): array {
		// @var array<int, array<string, mixed>> $characters
		$characters = $this->objectFetcher->getObjects('character');
		$updatedCharacters = [];
		foreach ($characters as $character) {
			$updatedCharacters[] = $this->calculateCharacter(character: $character);
		}

		return $updatedCharacters;
	}//end calculateAllCharacters()

	/**
	 * Initialize ability scores from base ability values.
	 *
	 * @return array<string, array{name: string, base: int, value: int, audit: array}> Ability scores.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-71
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-72
	 */
	private function initializeAbilityScores(): array {
		// @var array<string, array{name: string, base: int, value: int, audit: array}> $abilityScores
		$abilityScores = [];
		foreach ($this->allAbilities as $ability) {
			$this->warnOnNonNumericBase(ability: $ability);
			$abilityScores[(string)$ability['id']] = [
				'name' => (string)($ability['name'] ?? ''),
				'base' => (int)($ability['base'] ?? 0),
				'value' => (int)($ability['base'] ?? 0),
				'audit' => [],
			];
		}

		return $abilityScores;
	}//end initializeAbilityScores()

	/**
	 * Log a warning for an ability whose base is set but not numeric; it counts as 0.
	 *
	 * @param array<string, mixed> $ability The ability.
	 *
	 * @return void
	 */
	private function warnOnNonNumericBase(array $ability): void {
		if (isset($ability['base']) === false || is_numeric($ability['base']) === true) {
			return;
		}

		$this->logger->warning(
			'Larpinq: ability has non-numeric base value; defaulting to 0',
			[
				'abilityId' => (string)($ability['id'] ?? 'unknown'),
				'abilityName' => (string)($ability['name'] ?? 'unknown'),
				'base' => $ability['base'],
			]
		);
	}//end warnOnNonNumericBase()

	/**
	 * The entities a character links under one stage property that carry effects, in link order.
	 *
	 * @param array  $character Character data array.
	 * @param string $property  Character property name (e.g. 'skills').
	 *
	 * @return array<int, array<string, mixed>> The entities; a link to an unknown id is skipped.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-69
	 */
	private function stageEntities(array $character, string $property): array {
		$lookup = $this->stageLookup(property: $property);
		$entities = [];
		// @psalm-suppress MixedAssignment Character array values are mixed
		foreach ($this->linkedIds(character: $character, property: $property) as $entityId) {
			$effects = ($lookup[(string)$entityId]['effects'] ?? null);
			if (empty($effects) === false) {
				$entities[] = $lookup[(string)$entityId];
			}
		}

		return $entities;
	}//end stageEntities()

	/**
	 * The ids a character links under a property, or none when the value is not a non-empty list.
	 *
	 * @param array  $character Character data array.
	 * @param string $property  Character property name.
	 *
	 * @return array<int|string, mixed> The linked ids.
	 */
	private function linkedIds(array $character, string $property): array {
		if (isset($character[$property]) === false || is_array($character[$property]) === false) {
			return [];
		}

		return $character[$property];
	}//end linkedIds()

	/**
	 * Apply one entity's effects to the run and name it on the audit entries they added.
	 *
	 * @param EffectRun            $run    The pass: the abilities as they change and the effects applied so far.
	 * @param array<string, mixed> $entity The carrying entity, with non-empty effects.
	 * @param string               $source 'skill', 'item', 'condition' or 'event'.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-69
	 */
	private function applyEntity(EffectRun $run, array $entity, string $source): void {
		$auditCounts = $run->auditCounts();
		// @var array|null $entityEffects
		$entityEffects = $entity['effects'];
		$this->effectApplier->applyEffects(
			abilities: $run->abilities,
			effects: $entityEffects,
			appliedEffects: $run->applied,
			effectLookup: $this->allEffects
		);
		$tag = ['source' => $source, 'sourceId' => (string)($entity['id'] ?? ''), 'sourceName' => (string)($entity['name'] ?? '')];
		$run->tagSince(auditCounts: $auditCounts, tag: $tag);
	}//end applyEntity()

	/**
	 * The loaded entities of one effect stage, indexed by ID.
	 *
	 * @param string $property Character property name: a key of STAGE_SOURCE.
	 *
	 * @return array<string, array<string, mixed>> The entity lookup table; empty for any other name.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-69
	 */
	private function stageLookup(string $property): array {
		return match ($property) {
			'skills' => $this->allSkills,
			'items' => $this->allItems,
			'conditions' => $this->allConditions,
			'events' => $this->allEvents,
			default => [],
		};
	}//end stageLookup()

	/**
	 * Calculate stats for a single character array.
	 *
	 * @param array $character Character data array.
	 *
	 * @return array Updated character data array with calculated stats.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-67
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-68
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-69
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-70
	 */
	public function calculateCharacter(array $character): array {
		// Load entity collections lazily on first call. Closes #217.
		$this->loadAllEntities();

		// Track which non-cumulative effect IDs have already been applied this pass.
		// Resets per calculateCharacter() call (not shared across characters).
		// Closes #208.
		$run = new EffectRun(abilities: $this->initializeAbilityScores(), applied: []);

		// Apply effects from each entity type the character has: skills,
		// items, conditions, then events.
		foreach (self::STAGE_SOURCE as $property => $source) {
			foreach ($this->stageEntities(character: $character, property: $property) as $entity) {
				$this->applyEntity(run: $run, entity: $entity, source: $source);
			}
		}

		// Fifth stage: per-participant XP awards (event-xp-award-workflow).
		// Applied after the four entity-effect stages so existing CALC ordering
		// and arithmetic stay byte-identical.
		$this->applyXpAwards(run: $run, character: $character);

		// Update character array with calculated stats.
		$character['stats'] = $run->abilities;

		return $character;
	}//end calculateCharacter()

	/**
	 * Apply the character's XP awards onto the XP ability (fifth stage).
	 *
	 * Each award sums its amount onto the resolved XP ability and appends an
	 * audit entry `{type: "xpAward", award, old, new}`. The XP ability is
	 * resolved by name (no hardcoded UUIDs). When no XP ability resolves, or
	 * the character has no awards, this is a no-op — never throws (CALC-006).
	 *
	 * @param EffectRun $run The pass, whose XP ability the awards add to.
	 * @param array<string, mixed> $character The character being calculated.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	private function applyXpAwards(EffectRun $run, array $character): void {
		$target = $this->xpTarget(abilities: $run->abilities, character: $character);
		if ($target === null) {
			return;
		}

		[$xpAbilityId, $awards] = $target;
		foreach ($awards as $award) {
			$amount = $award['amount'] ?? 0;
			if (is_numeric($amount) === true) {
				$run->addXpAward(abilityId: $xpAbilityId, award: $award);
			}
		}
	}//end applyXpAwards()

	/**
	 * The XP ability and the awards of a character, or null when there is nothing to apply.
	 *
	 * @param array<string, array<string, mixed>> $abilities The ability scores.
	 * @param array<string, mixed>                $character The character being calculated.
	 *
	 * @return array{0: string, 1: array<int, array<string, mixed>>}|null The [xpAbilityId, awards] pair.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	private function xpTarget(array $abilities, array $character): ?array {
		$characterId = (string)($character['id'] ?? '');
		if ($characterId === '') {
			return null;
		}

		// Without awards the stage changes nothing, so the XP ability is looked up either way.
		$awards = $this->awardsFor(characterId: $characterId);
		$xpAbilityId = $this->resolveXpAbilityId();
		if ($xpAbilityId === null || isset($abilities[$xpAbilityId]) === false) {
			return null;
		}

		return [$xpAbilityId, $awards];
	}//end xpTarget()



	/**
	 * The XP awards on one character.
	 *
	 * The xpAward schema is read by game masters and the award's owner only (DECISIONS
	 * row 30), and the owner of an award is the game master who granted it.
	 * So the awards read as the user cover game masters; for a player, the
	 * awards on a character they play are read with the app's authority, after
	 * larpinq's own check that the STORED character is theirs. Any other
	 * character keeps what the user may read. A failed read falls back to that
	 * too, never to more.
	 *
	 * @param string $characterId The character.
	 *
	 * @return array<int, array<string, mixed>> The awards.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	private function awardsFor(string $characterId): array {
		$asUser = ($this->xpAwardsByCharacter[$characterId] ?? []);
		if (isset($this->ownXpAwards[$characterId]) === true) {
			return $this->ownXpAwards[$characterId];
		}

		$userId = (string)$this->userSession?->getUser()?->getUID();
		if ($this->connectionGuard === null
			|| $this->connectionGuard->owns(objectType: 'character', id: $characterId, userId: $userId) === false
		) {
			return $asUser;
		}

		try {
			$own = $this->objectFetcher->getObjectsWithAppAuthority(objectType: 'xpAward', filters: ['character' => $characterId]);
		} catch (\Throwable $e) {
			$this->logger->debug('Larpinq: the awards on an own character could not be read; counting what the user may read.', ['exception' => $e]);
			return $asUser;
		}

		$this->ownXpAwards[$characterId] = array_values(array_filter($own, static fn (mixed $award): bool => is_array($award) === true));
		return $this->ownXpAwards[$characterId];
	}//end awardsFor()
}//end class
