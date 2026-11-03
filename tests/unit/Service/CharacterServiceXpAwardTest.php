<?php

/**
 * Unit tests for the XP-award fifth stage of CharacterService.
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
 * Tests for the XP-award stage (event-xp-award-workflow).
 */
class CharacterServiceXpAwardTest extends TestCase {

	private function makeService(
		array $abilities = [],
		array $effects = [],
		array $skills = [],
		array $xpAwards = [],
	): CharacterService {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(
			function (string $type) use ($abilities, $effects, $skills, $xpAwards): array {
				return match ($type) {
					'ability' => $abilities,
					'effect' => $effects,
					'skill' => $skills,
					'xpAward' => $xpAwards,
					default => [],
				};
			}
		);

		return new CharacterService(
			objectFetcher: $fetcher,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			effectApplier: new EffectApplier()
		);
	}

	public function testAwardsSumOntoXpAbility(): void {
		$abilities = [['id' => 'xpid', 'name' => 'XP', 'base' => 0]];
		$xpAwards = [
			['id' => 'a1', 'character' => 'char-1', 'event' => 'ev-1', 'amount' => 5, 'reason' => 'attendance'],
			['id' => 'a2', 'character' => 'char-1', 'event' => 'ev-2', 'amount' => 3, 'reason' => 'plot'],
		];
		$service = $this->makeService(abilities: $abilities, xpAwards: $xpAwards);

		$result = $service->calculateCharacter(['id' => 'char-1', 'name' => 'Hero']);
		self::assertSame(expected: 8, actual: $result['stats']['xpid']['value']);
	}

	public function testAuditEntriesRecordedInOrder(): void {
		$abilities = [['id' => 'xpid', 'name' => 'Experience', 'base' => 10]];
		$xpAwards = [
			['id' => 'a1', 'character' => 'char-1', 'event' => 'ev-1', 'amount' => 4, 'reason' => 'attendance'],
		];
		$service = $this->makeService(abilities: $abilities, xpAwards: $xpAwards);

		$result = $service->calculateCharacter(['id' => 'char-1', 'name' => 'Hero']);
		$audit = $result['stats']['xpid']['audit'];
		self::assertCount(expectedCount: 1, haystack: $audit);
		self::assertSame(expected: 'xpAward', actual: $audit[0]['type']);
		self::assertSame(expected: 10, actual: $audit[0]['old']);
		self::assertSame(expected: 14, actual: $audit[0]['new']);
		self::assertSame(expected: 'a1', actual: $audit[0]['award']['id']);
		self::assertSame(expected: 4, actual: $audit[0]['award']['amount']);
	}

	public function testAwardsAppliedAfterEffects(): void {
		// XP base 0, a skill grants +2 XP via effect, then award +5 → 7.
		$abilities = [['id' => 'xpid', 'name' => 'XP', 'base' => 0]];
		$effects = [['id' => 'e1', 'name' => 'Bonus', 'modifier' => 2, 'modification' => 'positive', 'abilities' => ['xpid']]];
		$skills = [['id' => 's1', 'name' => 'S1', 'effects' => ['e1']]];
		$xpAwards = [['id' => 'a1', 'character' => 'char-1', 'event' => 'ev-1', 'amount' => 5]];
		$service = $this->makeService(abilities: $abilities, effects: $effects, skills: $skills, xpAwards: $xpAwards);

		$result = $service->calculateCharacter(['id' => 'char-1', 'skills' => ['s1']]);
		self::assertSame(expected: 7, actual: $result['stats']['xpid']['value']);
		// Audit: effect first, then award.
		$audit = $result['stats']['xpid']['audit'];
		self::assertSame(expected: 'effect', actual: $audit[0]['type']);
		self::assertSame(expected: 'xpAward', actual: $audit[1]['type']);
	}

	public function testFourStageRegressionUnchangedWhenNoAwards(): void {
		// No awards → arithmetic and audit identical to pre-feature behaviour.
		$abilities = [['id' => 'str', 'name' => 'Strength', 'base' => 10]];
		$effects = [['id' => 'e1', 'name' => 'Buff', 'modifier' => 3, 'modification' => 'positive', 'abilities' => ['str']]];
		$skills = [['id' => 's1', 'name' => 'S1', 'effects' => ['e1']]];
		$service = $this->makeService(abilities: $abilities, effects: $effects, skills: $skills, xpAwards: []);

		$result = $service->calculateCharacter(['id' => 'char-1', 'skills' => ['s1']]);
		self::assertSame(expected: 13, actual: $result['stats']['str']['value']);
		self::assertCount(expectedCount: 1, haystack: $result['stats']['str']['audit']);
		self::assertSame(expected: 'effect', actual: $result['stats']['str']['audit'][0]['type']);
	}

	public function testAwardForOtherCharacterIgnored(): void {
		$abilities = [['id' => 'xpid', 'name' => 'XP', 'base' => 0]];
		$xpAwards = [['id' => 'a1', 'character' => 'other-char', 'event' => 'ev-1', 'amount' => 5]];
		$service = $this->makeService(abilities: $abilities, xpAwards: $xpAwards);

		$result = $service->calculateCharacter(['id' => 'char-1', 'name' => 'Hero']);
		self::assertSame(expected: 0, actual: $result['stats']['xpid']['value']);
	}

	public function testNoXpAbilityMeansNoCrash(): void {
		// Award present but no XP ability → graceful no-op.
		$abilities = [['id' => 'str', 'name' => 'Strength', 'base' => 5]];
		$xpAwards = [['id' => 'a1', 'character' => 'char-1', 'event' => 'ev-1', 'amount' => 5]];
		$service = $this->makeService(abilities: $abilities, xpAwards: $xpAwards);

		$result = $service->calculateCharacter(['id' => 'char-1', 'name' => 'Hero']);
		self::assertSame(expected: 5, actual: $result['stats']['str']['value']);
	}

	public function testNonNumericAmountSkipped(): void {
		$abilities = [['id' => 'xpid', 'name' => 'XP', 'base' => 2]];
		$xpAwards = [
			['id' => 'a1', 'character' => 'char-1', 'event' => 'ev-1', 'amount' => 'oops'],
			['id' => 'a2', 'character' => 'char-1', 'event' => 'ev-2', 'amount' => 3],
		];
		$service = $this->makeService(abilities: $abilities, xpAwards: $xpAwards);

		$result = $service->calculateCharacter(['id' => 'char-1', 'name' => 'Hero']);
		self::assertSame(expected: 5, actual: $result['stats']['xpid']['value']);
	}

	public function testCharacterWithoutIdGetsNoAwards(): void {
		$abilities = [['id' => 'xpid', 'name' => 'XP', 'base' => 0]];
		$xpAwards = [['id' => 'a1', 'character' => 'char-1', 'event' => 'ev-1', 'amount' => 5]];
		$service = $this->makeService(abilities: $abilities, xpAwards: $xpAwards);

		$result = $service->calculateCharacter(['name' => 'No Id']);
		self::assertSame(expected: 0, actual: $result['stats']['xpid']['value']);
	}
}
