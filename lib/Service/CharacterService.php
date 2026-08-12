<?php

/**
 * CharacterService for LarpingApp
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
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

namespace OCA\LarpingApp\Service;

use Psr\Log\LoggerInterface;

/**
 * Service class for character-related operations.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
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
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly LoggerInterface $logger,
		private readonly EffectApplier $effectApplier,
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
		$grouped = [];
		try {
			$awards = $this->objectFetcher->getObjects('xpAward');
		} catch (\Throwable $e) {
			$this->logger->debug(
				'LarpingApp: xpAward schema unavailable; stat calculation proceeds with no awards.',
				['exception' => $e]
			);
			return $grouped;
		}

		// @psalm-suppress MixedAssignment Award entries from the object fetcher.
		foreach ($awards as $award) {
			if (is_array($award) === false) {
				continue;
			}

			$characterId = (string)($award['character'] ?? '');
			if ($characterId === '') {
				continue;
			}

			if (isset($grouped[$characterId]) === false) {
				$grouped[$characterId] = [];
			}

			$grouped[$characterId][] = $award;
		}

		return $grouped;
	}//end loadXpAwards()

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
			if (isset($ability['base']) === true && is_numeric($ability['base']) === false) {
				$this->logger->warning(
					'LarpingApp: ability has non-numeric base value; defaulting to 0',
					[
						'abilityId' => (string)($ability['id'] ?? 'unknown'),
						'abilityName' => (string)($ability['name'] ?? 'unknown'),
						'base' => $ability['base'],
					]
				);
			}

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
	 * Apply effects from a character's linked entities of a given type.
	 *
	 * Looks up each entity ID in the provided lookup table,
	 * then applies any effects found on those entities.
	 *
	 * @param array<string, array<string, mixed>> $abilityScores Reference to ability scores.
	 * @param array $character Character data array.
	 * @param string $property Character property name (e.g. 'skills').
	 * @param array<string, array<string, mixed>> $lookup Entity lookup table indexed by ID.
	 * @param array<string, bool> $appliedEffects Tracks which non-cumulative effects have been applied.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-69
	 */
	private function applyEntityEffects(
		array &$abilityScores,
		array $character,
		string $property,
		array $lookup,
		array &$appliedEffects,
	): void {
		if (isset($character[$property]) === false
			|| is_array($character[$property]) === false
			|| empty($character[$property]) === true
		) {
			return;
		}

		// @psalm-suppress MixedAssignment Character array values are mixed
		foreach ($character[$property] as $entityId) {
			$entity = $lookup[(string)$entityId] ?? null;
			if ($entity === null) {
				continue;
			}

			if (isset($entity['effects']) === true && empty($entity['effects']) === false) {
				// @var array|null $entityEffects
				$entityEffects = $entity['effects'];
				$this->effectApplier->applyEffects(
					abilities: $abilityScores,
					effects: $entityEffects,
					appliedEffects: $appliedEffects,
					effectLookup: $this->allEffects
				);
			}
		}
	}//end applyEntityEffects()

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

		$abilityScores = $this->initializeAbilityScores();

		// Track which non-cumulative effect IDs have already been applied this pass.
		// Resets per calculateCharacter() call (not shared across characters).
		// Closes #208.
		$appliedEffects = [];

		// Apply effects from each entity type the character has.
		$this->applyEntityEffects(
			abilityScores: $abilityScores,
			character: $character,
			property: 'skills',
			lookup: $this->allSkills,
			appliedEffects: $appliedEffects
		);
		$this->applyEntityEffects(
			abilityScores: $abilityScores,
			character: $character,
			property: 'items',
			lookup: $this->allItems,
			appliedEffects: $appliedEffects
		);
		$this->applyEntityEffects(
			abilityScores: $abilityScores,
			character: $character,
			property: 'conditions',
			lookup: $this->allConditions,
			appliedEffects: $appliedEffects
		);
		$this->applyEntityEffects(
			abilityScores: $abilityScores,
			character: $character,
			property: 'events',
			lookup: $this->allEvents,
			appliedEffects: $appliedEffects
		);

		// Fifth stage: per-participant XP awards (event-xp-award-workflow).
		// Applied after the four entity-effect stages so existing CALC ordering
		// and arithmetic stay byte-identical.
		$this->applyXpAwards(abilityScores: $abilityScores, character: $character);

		// Update character array with calculated stats.
		$character['stats'] = $abilityScores;

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
	 * @param array<string, array<string, mixed>> $abilityScores Reference to the ability scores.
	 * @param array<string, mixed> $character The character being calculated.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	private function applyXpAwards(array &$abilityScores, array $character): void {
		$characterId = (string)($character['id'] ?? '');
		if ($characterId === '') {
			return;
		}

		$awards = $this->xpAwardsByCharacter[$characterId] ?? [];
		if (empty($awards) === true) {
			return;
		}

		$xpAbilityId = $this->resolveXpAbilityId();
		if ($xpAbilityId === null || isset($abilityScores[$xpAbilityId]) === false) {
			return;
		}

		foreach ($awards as $award) {
			$amount = $award['amount'] ?? 0;
			if (is_numeric($amount) === false) {
				continue;
			}

			$currentValue = (int)$abilityScores[$xpAbilityId]['value'];
			$newValue = ($currentValue + (int)$amount);

			$abilityScores[$xpAbilityId]['value'] = $newValue;
			$abilityScores[$xpAbilityId]['audit'][] = [
				'type' => 'xpAward',
				'award' => [
					'id' => (string)($award['id'] ?? ''),
					'event' => (string)($award['event'] ?? ''),
					'amount' => (int)$amount,
					'reason' => (string)($award['reason'] ?? ''),
				],
				'old' => $currentValue,
				'new' => $newValue,
			];
		}//end foreach
	}//end applyXpAwards()
}//end class
