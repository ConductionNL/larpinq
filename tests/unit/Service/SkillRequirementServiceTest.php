<?php

/**
 * Unit tests for SkillRequirementService.
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
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementChecker;
use OCA\Larpinq\Service\SkillRequirementService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the skill-requirement / XP-budget enforcement logic.
 */
class SkillRequirementServiceTest extends TestCase {

	private LoggerInterface $logger;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
	}

	/**
	 * Build a service backed by a CharacterService whose fetcher returns the
	 * given world data. The SkillRequirementService uses the same fetcher.
	 */
	private function makeService(
		array $abilities = [],
		array $effects = [],
		array $skills = [],
		array $conditions = [],
	): SkillRequirementService {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')
			->willReturnCallback(function (string $type) use ($abilities, $effects, $skills, $conditions): array {
				return match ($type) {
					'ability' => $abilities,
					'effect' => $effects,
					'skill' => $skills,
					'condition' => $conditions,
					default => [],
				};
			});

		$characterService = new CharacterService(objectFetcher: $fetcher, logger: $this->logger, effectApplier: new EffectApplier());
		$idList = new IdListNormaliser();
		return new SkillRequirementService(
			characterService: $characterService,
			objectFetcher: $fetcher,
			logger: $this->logger,
			checker: new SkillRequirementChecker(idList: $idList),
			idList: $idList
		);
	}

	/**
	 * New character with no skills is valid.
	 *
	 * @return void
	 */
	public function testNewCharacterWithNoSkillsIsValid(): void {
		$service = $this->makeService();
		$result = $service->validate(['skills' => []], []);
		$this->assertTrue(condition: $result['valid']);
		$this->assertSame(expected: [], actual: $result['requirements']);
	}

	/**
	 * Missing prerequisite skill is unmet.
	 *
	 * @return void
	 */
	public function testMissingPrerequisiteSkillIsUnmet(): void {
		$skills = [
			['id' => 'basic', 'name' => 'Basic Swordplay'],
			['id' => 'adv', 'name' => 'Advanced Swordplay', 'requiredSkills' => ['basic']],
		];
		$service = $this->makeService(skills: $skills);

		// Adding only the advanced skill, without basic.
		$result = $service->validate(['skills' => ['adv']], ['skills' => []]);
		$this->assertFalse(condition: $result['valid']);
		$unmet = array_filter($result['requirements'], fn ($r) => $r['status'] === 'unmet');
		$this->assertCount(expectedCount: 1, haystack: $unmet);
		$entry = array_values($unmet)[0];
		$this->assertSame(expected: 'requiredSkill', actual: $entry['type']);
		$this->assertSame(expected: 'basic', actual: $entry['target']);
	}

	/**
	 * Same prerequisite satisfied in one write.
	 *
	 * @return void
	 */
	public function testSamePrerequisiteSatisfiedInOneWrite(): void {
		$skills = [
			['id' => 'basic', 'name' => 'Basic Swordplay'],
			['id' => 'adv', 'name' => 'Advanced Swordplay', 'requiredSkills' => ['basic']],
		];
		$service = $this->makeService(skills: $skills);

		// Adding both basic and advanced together is valid.
		$result = $service->validate(['skills' => ['basic', 'adv']], ['skills' => []]);
		$this->assertTrue(condition: $result['valid']);
	}

	/**
	 * Required stat score enforced.
	 *
	 * @return void
	 */
	public function testRequiredStatScoreEnforced(): void {
		$abilities = [['id' => 'str', 'name' => 'Strength', 'base' => 2]];
		$skills = [
			['id' => 'heavy', 'name' => 'Heavy Armor', 'requiredStats' => ['str'], 'requiredScore' => 5],
		];
		$service = $this->makeService(abilities: $abilities, skills: $skills);

		$result = $service->validate(['skills' => ['heavy']], ['skills' => []]);
		$this->assertFalse(condition: $result['valid']);
		$entry = array_values(array_filter($result['requirements'], fn ($r) => $r['type'] === 'requiredStat'))[0];
		$this->assertSame(expected: 'unmet', actual: $entry['status']);
		$this->assertSame(expected: 2, actual: $entry['current']);
		$this->assertSame(expected: 5, actual: $entry['required']);
	}

	/**
	 * XP budget rejects overspend.
	 *
	 * @return void
	 */
	public function testXpBudgetRejectsOverspend(): void {
		// XP ability base 5; skill costs 8 via a negative effect → -3.
		$abilities = [['id' => 'xpid', 'name' => 'XP', 'base' => 5]];
		$effects = [
			['id' => 'cost', 'name' => 'XP cost', 'modifier' => 8, 'modification' => 'negative', 'abilities' => ['xpid']],
		];
		$skills = [
			['id' => 'expensive', 'name' => 'Expensive Skill', 'effects' => ['cost']],
		];
		$service = $this->makeService(abilities: $abilities, effects: $effects, skills: $skills);

		$result = $service->validate(['skills' => ['expensive']], ['skills' => []]);
		$this->assertFalse(condition: $result['valid']);
		$this->assertFalse(condition: $result['budget']['ok']);
		$this->assertSame(expected: -3, actual: $result['budget']['value']);
		$this->assertSame(expected: 3, actual: $result['budget']['shortfall']);
	}

	/**
	 * XP budget accepts affordable.
	 *
	 * @return void
	 */
	public function testXpBudgetAcceptsAffordable(): void {
		$abilities = [['id' => 'xpid', 'name' => 'Experience Points', 'base' => 10]];
		$effects = [
			['id' => 'cost', 'name' => 'XP cost', 'modifier' => 4, 'modification' => 'negative', 'abilities' => ['xpid']],
		];
		$skills = [
			['id' => 'cheap', 'name' => 'Cheap Skill', 'effects' => ['cost']],
		];
		$service = $this->makeService(abilities: $abilities, effects: $effects, skills: $skills);

		$result = $service->validate(['skills' => ['cheap']], ['skills' => []]);
		$this->assertTrue(condition: $result['valid']);
		$this->assertSame(expected: 6, actual: $result['budget']['value']);
	}

	/**
	 * Validator XP agrees with engine.
	 *
	 * @return void
	 */
	public function testValidatorXpAgreesWithEngine(): void {
		$abilities = [['id' => 'xpid', 'name' => 'XP', 'base' => 20]];
		$effects = [
			['id' => 'cost', 'name' => 'cost', 'modifier' => 7, 'modification' => 'negative', 'abilities' => ['xpid']],
		];
		$skills = [['id' => 's1', 'name' => 'S1', 'effects' => ['cost']]];

		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObjects')->willReturnCallback(function (string $type) use ($abilities, $effects, $skills): array {
			return match ($type) {
				'ability' => $abilities,
				'effect' => $effects,
				'skill' => $skills,
				default => [],
			};
		});
		$engine = new CharacterService(objectFetcher: $fetcher, logger: $this->logger, effectApplier: new EffectApplier());
		$idList = new IdListNormaliser();
		$service = new SkillRequirementService(
			characterService: $engine,
			objectFetcher: $fetcher,
			logger: $this->logger,
			checker: new SkillRequirementChecker(idList: $idList),
			idList: $idList
		);

		$character = ['skills' => ['s1']];
		$calculated = $engine->calculateCharacter($character);
		$engineXp = (int)$calculated['stats']['xpid']['value'];

		$result = $service->validate($character, []);
		$this->assertSame(expected: $engineXp, actual: $result['budget']['value']);
	}

	/**
	 * Dangling prerequisite is unresolvable.
	 *
	 * @return void
	 */
	public function testDanglingPrerequisiteIsUnresolvable(): void {
		$skills = [
			['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['ghost-uuid']],
		];
		$service = $this->makeService(skills: $skills);

		$result = $service->validate(['skills' => ['adv']], ['skills' => []]);
		$this->assertFalse(condition: $result['valid']);
		$entry = array_values(array_filter($result['requirements'], fn ($r) => $r['type'] === 'requiredSkill'))[0];
		$this->assertSame(expected: 'unresolvable', actual: $entry['status']);
	}

	/**
	 * Override marks as overridden and valid.
	 *
	 * @return void
	 */
	public function testOverrideMarksAsOverriddenAndValid(): void {
		$skills = [
			['id' => 'basic', 'name' => 'Basic'],
			['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
		];
		$service = $this->makeService(skills: $skills);

		$candidate = [
			'skills' => ['adv'],
			'requirementOverrides' => [
				['skill' => 'adv', 'reason' => 'Story event respec'],
			],
		];
		$result = $service->validate($candidate, ['skills' => []]);
		$this->assertTrue(condition: $result['valid']);
		$entry = array_values(array_filter($result['requirements'], fn ($r) => $r['type'] === 'requiredSkill'))[0];
		$this->assertSame(expected: 'overridden', actual: $entry['status']);
	}

	/**
	 * Empty reason override does not apply.
	 *
	 * @return void
	 */
	public function testEmptyReasonOverrideDoesNotApply(): void {
		$skills = [
			['id' => 'basic', 'name' => 'Basic'],
			['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['basic']],
		];
		$service = $this->makeService(skills: $skills);

		$candidate = [
			'skills' => ['adv'],
			'requirementOverrides' => [['skill' => 'adv', 'reason' => '   ']],
		];
		$result = $service->validate($candidate, ['skills' => []]);
		$this->assertFalse(condition: $result['valid']);
	}

	/**
	 * Removal flags dependent.
	 *
	 * @return void
	 */
	public function testRemovalFlagsDependent(): void {
		$skills = [
			['id' => 'basic', 'name' => 'Basic Swordplay'],
			['id' => 'adv', 'name' => 'Advanced Swordplay', 'requiredSkills' => ['basic']],
		];
		$service = $this->makeService(skills: $skills);

		// Old: both. New: basic removed, adv kept.
		$result = $service->validate(['skills' => ['adv']], ['skills' => ['basic', 'adv']]);
		$this->assertCount(expectedCount: 1, haystack: $result['dependents']);
		$this->assertSame(expected: 'adv', actual: $result['dependents'][0]['skill']);
		$this->assertSame(expected: 'basic', actual: $result['dependents'][0]['missingSkill']);
	}

	/**
	 * No XP ability degrades budget open.
	 *
	 * @return void
	 */
	public function testNoXpAbilityDegradesBudgetOpen(): void {
		// No ability named XP — budget cannot be enforced, never blocks.
		$abilities = [['id' => 'str', 'name' => 'Strength', 'base' => 1]];
		$service = $this->makeService(abilities: $abilities);

		$result = $service->validate(['skills' => []], []);
		$this->assertTrue(condition: $result['budget']['ok']);
		$this->assertSame(expected: '', actual: $result['budget']['ability']);
	}
}
