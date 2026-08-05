<?php

/**
 * Unit tests for SkillRequirementService.
 *
 * @category Test
 * @package  OCA\LarpingApp\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Tests\Unit\Service;

use OCA\LarpingApp\Service\CharacterService;
use OCA\LarpingApp\Service\EffectApplier;
use OCA\LarpingApp\Service\IdListNormaliser;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use OCA\LarpingApp\Service\SkillRequirementChecker;
use OCA\LarpingApp\Service\SkillRequirementService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the skill-requirement / XP-budget enforcement logic.
 */
class SkillRequirementServiceTest extends TestCase
{

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * Build a service backed by a CharacterService whose fetcher returns the
     * given world data. The SkillRequirementService uses the same fetcher.
     */
    private function makeService(
        array $abilities = [],
        array $effects = [],
        array $skills = [],
        array $conditions = []
    ): SkillRequirementService {
        $fetcher = $this->createMock(RegisterObjectFetcher::class);
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

        $characterService = new CharacterService($fetcher, $this->logger, new EffectApplier());
        $idList           = new IdListNormaliser();
        return new SkillRequirementService(
            $characterService,
            $fetcher,
            $this->logger,
            new SkillRequirementChecker($idList),
            $idList
        );
    }

    public function testNewCharacterWithNoSkillsIsValid(): void
    {
        $service = $this->makeService();
        $result  = $service->validate(['skills' => []], []);
        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['requirements']);
    }

    public function testMissingPrerequisiteSkillIsUnmet(): void
    {
        $skills = [
            ['id' => 'basic', 'name' => 'Basic Swordplay'],
            ['id' => 'adv', 'name' => 'Advanced Swordplay', 'requiredSkills' => ['basic']],
        ];
        $service = $this->makeService(skills: $skills);

        // Adding only the advanced skill, without basic.
        $result = $service->validate(['skills' => ['adv']], ['skills' => []]);
        $this->assertFalse($result['valid']);
        $unmet = array_filter($result['requirements'], fn($r) => $r['status'] === 'unmet');
        $this->assertCount(1, $unmet);
        $entry = array_values($unmet)[0];
        $this->assertSame('requiredSkill', $entry['type']);
        $this->assertSame('basic', $entry['target']);
    }

    public function testSamePrerequisiteSatisfiedInOneWrite(): void
    {
        $skills = [
            ['id' => 'basic', 'name' => 'Basic Swordplay'],
            ['id' => 'adv', 'name' => 'Advanced Swordplay', 'requiredSkills' => ['basic']],
        ];
        $service = $this->makeService(skills: $skills);

        // Adding both basic and advanced together is valid.
        $result = $service->validate(['skills' => ['basic', 'adv']], ['skills' => []]);
        $this->assertTrue($result['valid']);
    }

    public function testRequiredStatScoreEnforced(): void
    {
        $abilities = [['id' => 'str', 'name' => 'Strength', 'base' => 2]];
        $skills = [
            ['id' => 'heavy', 'name' => 'Heavy Armor', 'requiredStats' => ['str'], 'requiredScore' => 5],
        ];
        $service = $this->makeService(abilities: $abilities, skills: $skills);

        $result = $service->validate(['skills' => ['heavy']], ['skills' => []]);
        $this->assertFalse($result['valid']);
        $entry = array_values(array_filter($result['requirements'], fn($r) => $r['type'] === 'requiredStat'))[0];
        $this->assertSame('unmet', $entry['status']);
        $this->assertSame(2, $entry['current']);
        $this->assertSame(5, $entry['required']);
    }

    public function testXpBudgetRejectsOverspend(): void
    {
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
        $this->assertFalse($result['valid']);
        $this->assertFalse($result['budget']['ok']);
        $this->assertSame(-3, $result['budget']['value']);
        $this->assertSame(3, $result['budget']['shortfall']);
    }

    public function testXpBudgetAcceptsAffordable(): void
    {
        $abilities = [['id' => 'xpid', 'name' => 'Experience Points', 'base' => 10]];
        $effects = [
            ['id' => 'cost', 'name' => 'XP cost', 'modifier' => 4, 'modification' => 'negative', 'abilities' => ['xpid']],
        ];
        $skills = [
            ['id' => 'cheap', 'name' => 'Cheap Skill', 'effects' => ['cost']],
        ];
        $service = $this->makeService(abilities: $abilities, effects: $effects, skills: $skills);

        $result = $service->validate(['skills' => ['cheap']], ['skills' => []]);
        $this->assertTrue($result['valid']);
        $this->assertSame(6, $result['budget']['value']);
    }

    public function testValidatorXpAgreesWithEngine(): void
    {
        $abilities = [['id' => 'xpid', 'name' => 'XP', 'base' => 20]];
        $effects = [
            ['id' => 'cost', 'name' => 'cost', 'modifier' => 7, 'modification' => 'negative', 'abilities' => ['xpid']],
        ];
        $skills = [['id' => 's1', 'name' => 'S1', 'effects' => ['cost']]];

        $fetcher = $this->createMock(RegisterObjectFetcher::class);
        $fetcher->method('getObjects')->willReturnCallback(function (string $type) use ($abilities, $effects, $skills): array {
            return match ($type) {
                'ability' => $abilities,
                'effect' => $effects,
                'skill' => $skills,
                default => [],
            };
        });
        $engine  = new CharacterService($fetcher, $this->logger, new EffectApplier());
        $idList  = new IdListNormaliser();
        $service = new SkillRequirementService(
            $engine,
            $fetcher,
            $this->logger,
            new SkillRequirementChecker($idList),
            $idList
        );

        $character  = ['skills' => ['s1']];
        $calculated = $engine->calculateCharacter($character);
        $engineXp   = (int) $calculated['stats']['xpid']['value'];

        $result = $service->validate($character, []);
        $this->assertSame($engineXp, $result['budget']['value']);
    }

    public function testDanglingPrerequisiteIsUnresolvable(): void
    {
        $skills = [
            ['id' => 'adv', 'name' => 'Advanced', 'requiredSkills' => ['ghost-uuid']],
        ];
        $service = $this->makeService(skills: $skills);

        $result = $service->validate(['skills' => ['adv']], ['skills' => []]);
        $this->assertFalse($result['valid']);
        $entry = array_values(array_filter($result['requirements'], fn($r) => $r['type'] === 'requiredSkill'))[0];
        $this->assertSame('unresolvable', $entry['status']);
    }

    public function testOverrideMarksAsOverriddenAndValid(): void
    {
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
        $this->assertTrue($result['valid']);
        $entry = array_values(array_filter($result['requirements'], fn($r) => $r['type'] === 'requiredSkill'))[0];
        $this->assertSame('overridden', $entry['status']);
    }

    public function testEmptyReasonOverrideDoesNotApply(): void
    {
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
        $this->assertFalse($result['valid']);
    }

    public function testRemovalFlagsDependent(): void
    {
        $skills = [
            ['id' => 'basic', 'name' => 'Basic Swordplay'],
            ['id' => 'adv', 'name' => 'Advanced Swordplay', 'requiredSkills' => ['basic']],
        ];
        $service = $this->makeService(skills: $skills);

        // Old: both. New: basic removed, adv kept.
        $result = $service->validate(['skills' => ['adv']], ['skills' => ['basic', 'adv']]);
        $this->assertCount(1, $result['dependents']);
        $this->assertSame('adv', $result['dependents'][0]['skill']);
        $this->assertSame('basic', $result['dependents'][0]['missingSkill']);
    }

    public function testNoXpAbilityDegradesBudgetOpen(): void
    {
        // No ability named XP — budget cannot be enforced, never blocks.
        $abilities = [['id' => 'str', 'name' => 'Strength', 'base' => 1]];
        $service = $this->makeService(abilities: $abilities);

        $result = $service->validate(['skills' => []], []);
        $this->assertTrue($result['budget']['ok']);
        $this->assertSame('', $result['budget']['ability']);
    }
}
