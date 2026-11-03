<?php

/**
 * Unit tests for CharacterService.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\EffectApplier;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the CharacterService stat calculation engine.
 */
class CharacterServiceTest extends TestCase {

	private CharacterService $service;
	private RegisterObjectFetcher $objectFetcher;
	private LoggerInterface $logger;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->objectFetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);

		// Return empty arrays for all entity types by default.
		$this->objectFetcher->method('getObjects')
			->willReturnCallback(function (string $type): array {
				return [];
			});

		$this->service = new CharacterService(objectFetcher: $this->objectFetcher, logger: $this->logger, effectApplier: new EffectApplier());
	}

	/**
	 * Helper to create a CharacterService with specific entity data.
	 */
	private function createServiceWithData(
		array $abilities = [],
		array $effects = [],
		array $skills = [],
		array $items = [],
		array $conditions = [],
		array $events = [],
	): CharacterService {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')
			->willReturnCallback(function (string $type) use ($abilities, $effects, $skills, $items, $conditions, $events): array {
				return match ($type) {
					'ability' => $abilities,
					'effect' => $effects,
					'skill' => $skills,
					'item' => $items,
					'condition' => $conditions,
					'event' => $events,
					default => [],
				};
			});

		return new CharacterService(
			objectFetcher: $fetcher,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			effectApplier: new EffectApplier()
		);
	}

	/**
	 * Calculate character with no abilities.
	 *
	 * @return void
	 */
	public function testCalculateCharacterWithNoAbilities(): void {
		$character = ['id' => 'char-1', 'name' => 'Test'];
		$result = $this->service->calculateCharacter($character);

		self::assertArrayHasKey(key: 'stats', array: $result);
		self::assertEmpty(actual: $result['stats']);
	}

	/**
	 * Calculate character initializes ability scores.
	 *
	 * @return void
	 */
	public function testCalculateCharacterInitializesAbilityScores(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
			['id' => 'abil-2', 'name' => 'Mana', 'base' => 5],
		];

		$service = $this->createServiceWithData(abilities: $abilities);
		$character = ['id' => 'char-1', 'name' => 'Fighter'];
		$result = $service->calculateCharacter($character);

		self::assertArrayHasKey(key: 'abil-1', array: $result['stats']);
		self::assertSame(expected: 'Strength', actual: $result['stats']['abil-1']['name']);
		self::assertSame(expected: 10, actual: $result['stats']['abil-1']['base']);
		self::assertSame(expected: 10, actual: $result['stats']['abil-1']['value']);
		self::assertEmpty(actual: $result['stats']['abil-1']['audit']);

		self::assertArrayHasKey(key: 'abil-2', array: $result['stats']);
		self::assertSame(expected: 5, actual: $result['stats']['abil-2']['base']);
		self::assertSame(expected: 5, actual: $result['stats']['abil-2']['value']);
	}

	/**
	 * Calculate character applies positive skill effect.
	 *
	 * @return void
	 */
	public function testCalculateCharacterAppliesPositiveSkillEffect(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$effects = [
			['id' => 'eff-1', 'name' => 'Str Bonus', 'modifier' => 5, 'modification' => 'positive', 'abilities' => ['abil-1']],
		];
		$skills = [
			['id' => 'skill-1', 'name' => 'Swordsmanship', 'effects' => ['eff-1']],
		];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
		$character = ['id' => 'char-1', 'name' => 'Fighter', 'skills' => ['skill-1']];
		$result = $service->calculateCharacter($character);

		self::assertSame(expected: 15, actual: $result['stats']['abil-1']['value']);
		self::assertCount(expectedCount: 1, haystack: $result['stats']['abil-1']['audit']);
		self::assertSame(expected: 10, actual: $result['stats']['abil-1']['audit'][0]['old']);
		self::assertSame(expected: 15, actual: $result['stats']['abil-1']['audit'][0]['new']);
		// Audit stores id/name, not the full effect object.
		self::assertSame(expected: 'eff-1', actual: $result['stats']['abil-1']['audit'][0]['effectId']);
		self::assertSame(expected: 'Str Bonus', actual: $result['stats']['abil-1']['audit'][0]['effectName']);
		self::assertArrayNotHasKey(key: 'effect', array: $result['stats']['abil-1']['audit'][0]);
	}

	/**
	 * Calculate character applies negative effect.
	 *
	 * @return void
	 */
	public function testCalculateCharacterAppliesNegativeEffect(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'HP', 'base' => 20],
		];
		$effects = [
			['id' => 'eff-1', 'name' => 'Poison', 'modifier' => 3, 'modification' => 'negative', 'abilities' => ['abil-1']],
		];
		$conditions = [
			['id' => 'cond-1', 'name' => 'Poisoned', 'effects' => ['eff-1']],
		];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, conditions: $conditions);
		$character = ['id' => 'char-1', 'name' => 'Victim', 'conditions' => ['cond-1']];
		$result = $service->calculateCharacter($character);

		self::assertSame(expected: 17, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Calculate character applies effects from multiple entity types.
	 *
	 * @return void
	 */
	public function testCalculateCharacterAppliesEffectsFromMultipleEntityTypes(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$effects = [
			['id' => 'eff-1', 'modifier' => 2, 'modification' => 'positive', 'abilities' => ['abil-1']],
			['id' => 'eff-2', 'modifier' => 3, 'modification' => 'positive', 'abilities' => ['abil-1']],
			['id' => 'eff-3', 'modifier' => 1, 'modification' => 'negative', 'abilities' => ['abil-1']],
		];
		$skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];
		$items = [['id' => 'item-1', 'effects' => ['eff-2']]];
		$conditions = [['id' => 'cond-1', 'effects' => ['eff-3']]];

		$service = $this->createServiceWithData(
			abilities: $abilities,
			effects: $effects,
			skills: $skills,
			items: $items,
			conditions: $conditions
		);

		$character = [
			'id' => 'char-1',
			'skills' => ['skill-1'],
			'items' => ['item-1'],
			'conditions' => ['cond-1'],
		];
		$result = $service->calculateCharacter($character);

		// 10 + 2 (skill) + 3 (item) - 1 (condition) = 14
		self::assertSame(expected: 14, actual: $result['stats']['abil-1']['value']);
		self::assertCount(expectedCount: 3, haystack: $result['stats']['abil-1']['audit']);
	}

	/**
	 * Calculate character skips orphaned entity references.
	 *
	 * @return void
	 */
	public function testCalculateCharacterSkipsOrphanedEntityReferences(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];

		$service = $this->createServiceWithData(abilities: $abilities);
		$character = [
			'id' => 'char-1',
			'skills' => ['nonexistent-skill'],
			'items' => ['nonexistent-item'],
		];
		$result = $service->calculateCharacter($character);

		self::assertSame(expected: 10, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Calculate character skips orphaned effect references.
	 *
	 * @return void
	 */
	public function testCalculateCharacterSkipsOrphanedEffectReferences(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$skills = [
			['id' => 'skill-1', 'effects' => ['nonexistent-effect']],
		];

		$service = $this->createServiceWithData(abilities: $abilities, skills: $skills);
		$character = ['id' => 'char-1', 'skills' => ['skill-1']];
		$result = $service->calculateCharacter($character);

		self::assertSame(expected: 10, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Calculate character handles empty skills array.
	 *
	 * @return void
	 */
	public function testCalculateCharacterHandlesEmptySkillsArray(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];

		$service = $this->createServiceWithData(abilities: $abilities);
		$character = ['id' => 'char-1', 'skills' => []];
		$result = $service->calculateCharacter($character);

		self::assertSame(expected: 10, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Calculate character handles null effects on entity.
	 *
	 * @return void
	 */
	public function testCalculateCharacterHandlesNullEffectsOnEntity(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$skills = [
			['id' => 'skill-1', 'effects' => null],
		];

		$service = $this->createServiceWithData(abilities: $abilities, skills: $skills);
		$character = ['id' => 'char-1', 'skills' => ['skill-1']];
		$result = $service->calculateCharacter($character);

		self::assertSame(expected: 10, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Calculate character uses stat id as fallback.
	 *
	 * @return void
	 */
	public function testCalculateCharacterUsesStatIdAsFallback(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$effects = [
			['id' => 'eff-1', 'modifier' => 7, 'modification' => 'positive', 'stat_id' => 'abil-1'],
		];
		$skills = [
			['id' => 'skill-1', 'effects' => ['eff-1']],
		];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
		$character = ['id' => 'char-1', 'skills' => ['skill-1']];
		$result = $service->calculateCharacter($character);

		self::assertSame(expected: 17, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Calculate character default base is zero.
	 *
	 * @return void
	 */
	public function testCalculateCharacterDefaultBaseIsZero(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Luck'],
		];

		$service = $this->createServiceWithData(abilities: $abilities);
		$character = ['id' => 'char-1'];
		$result = $service->calculateCharacter($character);

		self::assertSame(expected: 0, actual: $result['stats']['abil-1']['base']);
		self::assertSame(expected: 0, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Calculate character applies event effects.
	 *
	 * @return void
	 */
	public function testCalculateCharacterAppliesEventEffects(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'XP', 'base' => 0],
		];
		$effects = [
			['id' => 'eff-1', 'modifier' => 100, 'modification' => 'positive', 'abilities' => ['abil-1']],
		];
		$events = [
			['id' => 'evt-1', 'name' => 'Summer LARP', 'effects' => ['eff-1']],
		];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, events: $events);
		$character = ['id' => 'char-1', 'events' => ['evt-1']];
		$result = $service->calculateCharacter($character);

		self::assertSame(expected: 100, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Calculate all characters.
	 *
	 * @return void
	 */
	public function testCalculateAllCharacters(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];

		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')
			->willReturnCallback(function (string $type) use ($abilities): array {
				if ($type === 'ability') {
					return $abilities;
				}
				if ($type === 'character') {
					return [
						['id' => 'char-1', 'name' => 'Fighter'],
						['id' => 'char-2', 'name' => 'Mage'],
					];
				}
				return [];
			});

		$service = new CharacterService(
			objectFetcher: $fetcher,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			effectApplier: new EffectApplier()
		);
		$results = $service->calculateAllCharacters();

		self::assertCount(expectedCount: 2, haystack: $results);
		self::assertArrayHasKey(key: 'stats', array: $results[0]);
		self::assertArrayHasKey(key: 'stats', array: $results[1]);
	}

	// -----------------------------------------------------------------------
	// #208 — non-cumulative dedup + stat_id double-apply fix
	// -----------------------------------------------------------------------

	public function testEffectWithMissingCumulativeFieldDefaultsToNonCumulative(): void {
		// When the 'cumulative' field is entirely absent from the effect array the
		// schema default is 'non-cumulative'. The same effect attached to both a skill
		// and an item must therefore apply ONCE, not twice. Fixes C1 (wave-9).
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$effects = [
			[
				'id' => 'eff-missing-cumulative',
				'modifier' => 5,
				'modification' => 'positive',
				// 'cumulative' key intentionally absent — no key, no default override.
				'abilities' => ['abil-1'],
			],
		];
		$skills = [['id' => 'skill-1', 'effects' => ['eff-missing-cumulative']]];
		$items = [['id' => 'item-1',  'effects' => ['eff-missing-cumulative']]];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills, items: $items);
		$character = [
			'id' => 'char-1',
			'skills' => ['skill-1'],
			'items' => ['item-1'],
		];
		$result = $service->calculateCharacter($character);

		// 10 + 5 = 15 (applied once). Were the default 'cumulative' it would be 20.
		self::assertSame(expected: 15, actual: $result['stats']['abil-1']['value']);
		self::assertCount(expectedCount: 1, haystack: $result['stats']['abil-1']['audit']);
	}

	/**
	 * Non cumulative effect applied only once.
	 *
	 * @return void
	 */
	public function testNonCumulativeEffectAppliedOnlyOnce(): void {
		// The same non-cumulative effect is attached to BOTH a skill and an item.
		// It should only be applied once regardless.
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$effects = [
			[
				'id' => 'eff-1',
				'name' => 'Iron Will',
				'modifier' => 5,
				'modification' => 'positive',
				'cumulative' => 'non-cumulative',
				'abilities' => ['abil-1'],
			],
		];
		$skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];
		$items = [['id' => 'item-1',  'effects' => ['eff-1']]];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills, items: $items);
		$character = [
			'id' => 'char-1',
			'skills' => ['skill-1'],
			'items' => ['item-1'],
		];
		$result = $service->calculateCharacter($character);

		// 10 + 5 (once, not 10). Closes #208.
		self::assertSame(expected: 15, actual: $result['stats']['abil-1']['value']);
		self::assertCount(expectedCount: 1, haystack: $result['stats']['abil-1']['audit']);
	}

	/**
	 * Cumulative effect applied multiple times.
	 *
	 * @return void
	 */
	public function testCumulativeEffectAppliedMultipleTimes(): void {
		// A cumulative effect on skill + item must stack.
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$effects = [
			[
				'id' => 'eff-1',
				'modifier' => 5,
				'modification' => 'positive',
				'cumulative' => 'cumulative',
				'abilities' => ['abil-1'],
			],
		];
		$skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];
		$items = [['id' => 'item-1',  'effects' => ['eff-1']]];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills, items: $items);
		$character = ['id' => 'char-1', 'skills' => ['skill-1'], 'items' => ['item-1']];
		$result = $service->calculateCharacter($character);

		// 10 + 5 + 5 = 20 (cumulative stacks).
		self::assertSame(expected: 20, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Stat id and abilities deduplication.
	 *
	 * @return void
	 */
	public function testStatIdAndAbilitiesDeduplication(): void {
		// stat_id = 'abil-1' AND abilities = ['abil-1']: must apply only once. Closes #208.
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$effects = [
			[
				'id' => 'eff-1',
				'modifier' => 4,
				'modification' => 'positive',
				'abilities' => ['abil-1'],
				'stat_id' => 'abil-1',
			],
		];
		$skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
		$character = ['id' => 'char-1', 'skills' => ['skill-1']];
		$result = $service->calculateCharacter($character);

		// 10 + 4 = 14 (not 18, because stat_id dedup removes the duplicate ability). Closes #208.
		self::assertSame(expected: 14, actual: $result['stats']['abil-1']['value']);
		self::assertCount(expectedCount: 1, haystack: $result['stats']['abil-1']['audit']);
	}

	// -----------------------------------------------------------------------
	// #209 — sign-direction confusion fix
	// -----------------------------------------------------------------------

	public function testNegativeModifierWithNegativeModificationSubtracts(): void {
		// {modifier: -3, modification: 'negative'} must subtract 3, NOT add 3.
		// Before the fix this would have computed value - (-3) = value + 3.
		// Closes #209.
		$abilities = [
			['id' => 'abil-1', 'name' => 'HP', 'base' => 20],
		];
		$effects = [
			[
				'id' => 'eff-1',
				'modifier' => -3,
				'modification' => 'negative',
				'abilities' => ['abil-1'],
			],
		];
		$skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
		$character = ['id' => 'char-1', 'skills' => ['skill-1']];
		$result = $service->calculateCharacter($character);

		// abs(-3) = 3; modification='negative' → 20 - 3 = 17.
		self::assertSame(expected: 17, actual: $result['stats']['abil-1']['value']);
	}

	/**
	 * Negative modifier with positive modification adds.
	 *
	 * @return void
	 */
	public function testNegativeModifierWithPositiveModificationAdds(): void {
		// {modifier: -5, modification: 'positive'} must add 5 (abs coercion).
		$abilities = [
			['id' => 'abil-1', 'name' => 'HP', 'base' => 10],
		];
		$effects = [
			[
				'id' => 'eff-1',
				'modifier' => -5,
				'modification' => 'positive',
				'abilities' => ['abil-1'],
			],
		];
		$skills = [['id' => 'skill-1', 'effects' => ['eff-1']]];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
		$character = ['id' => 'char-1', 'skills' => ['skill-1']];
		$result = $service->calculateCharacter($character);

		// abs(-5) = 5; modification='positive' → 10 + 5 = 15.
		self::assertSame(expected: 15, actual: $result['stats']['abil-1']['value']);
	}

	// -----------------------------------------------------------------------
	// #219 — lean audit trail
	// -----------------------------------------------------------------------

	public function testAuditTrailContainsOnlyMinimalFields(): void {
		$abilities = [
			['id' => 'abil-1', 'name' => 'Strength', 'base' => 10],
		];
		$effects = [
			[
				'id' => 'eff-42',
				'name' => 'Battle Fury',
				'modifier' => 3,
				'modification' => 'positive',
				'abilities' => ['abil-1'],
				'secret_field' => 'should-not-appear',
			],
		];
		$skills = [['id' => 'skill-1', 'effects' => ['eff-42']]];

		$service = $this->createServiceWithData(abilities: $abilities, effects: $effects, skills: $skills);
		$character = ['id' => 'char-1', 'skills' => ['skill-1']];
		$result = $service->calculateCharacter($character);

		$audit = $result['stats']['abil-1']['audit'][0];

		// Required fields must be present.
		self::assertArrayHasKey(key: 'type', array: $audit);
		self::assertArrayHasKey(key: 'effectId', array: $audit);
		self::assertArrayHasKey(key: 'effectName', array: $audit);
		self::assertArrayHasKey(key: 'old', array: $audit);
		self::assertArrayHasKey(key: 'new', array: $audit);
		self::assertSame(expected: 'eff-42', actual: $audit['effectId']);
		self::assertSame(expected: 'Battle Fury', actual: $audit['effectName']);

		// The full effect object must NOT be stored. Closes #219.
		self::assertArrayNotHasKey(key: 'effect', array: $audit);
		self::assertArrayNotHasKey(key: 'secret_field', array: $audit);
	}

	/**
	 * Each audit entry names the skill, item, condition or event that carried it (REQ-CSP-001).
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function testAuditEntriesNameTheirSource(): void {
		$abilities = [['id' => 'abil-1', 'name' => 'Strength', 'base' => 10]];
		$effects = [
			['id' => 'eff-1', 'name' => 'Blade training', 'modifier' => 3, 'modification' => 'positive', 'abilities' => ['abil-1']],
			['id' => 'eff-2', 'name' => 'Shield weight', 'modifier' => 1, 'modification' => 'positive', 'abilities' => ['abil-1']],
		];
		$service = $this->createServiceWithData(
			abilities: $abilities,
			effects: $effects,
			skills: [['id' => 'skill-1', 'name' => 'Swordsmanship', 'effects' => ['eff-1']]],
			items: [['id' => 'item-1', 'name' => 'Iron shield', 'effects' => ['eff-2']]],
		);

		$audit = $service->calculateCharacter(['id' => 'char-1', 'skills' => ['skill-1'], 'items' => ['item-1']])['stats']['abil-1']['audit'];

		self::assertSame(expected: ['skill', 'skill-1', 'Swordsmanship'], actual: [$audit[0]['source'], $audit[0]['sourceId'], $audit[0]['sourceName']]);
		self::assertSame(expected: ['item', 'item-1', 'Iron shield'], actual: [$audit[1]['source'], $audit[1]['sourceId'], $audit[1]['sourceName']]);
		self::assertSame(
			expected: 14,
			actual: $service->calculateCharacter(['id' => 'char-1', 'skills' => ['skill-1'], 'items' => ['item-1']])['stats']['abil-1']['value']
		);
	}
}
