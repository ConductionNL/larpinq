<?php

/**
 * Unit tests for CharacterXpHistory.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/characters-skill-refund/specs/character-management/spec.md#requirement-the-refund-shows-in-the-xp-history-req-csp-006
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\CharacterXpHistory;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The refund lines read from a character's audit trail.
 *
 * @covers \OCA\Larpinq\Service\CharacterXpHistory
 */
class CharacterXpHistoryTest extends TestCase {

	/**
	 * The XP each test skill costs.
	 */
	private const COSTS = ['sk-sword' => 10, 'sk-herb' => 6, 'sk-free' => 0];

	/**
	 * The XP a character has earned in these tests.
	 */
	private const EARNED = 40;

	/**
	 * The fetcher of the audit trail and the skills.
	 *
	 * @var RegisterObjectFetcher&MockObject
	 */
	private RegisterObjectFetcher&MockObject $fetcher;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * The stat engine.
	 *
	 * @var CharacterService&MockObject
	 */
	private CharacterService&MockObject $engine;

	/**
	 * The requirement service, for the XP ability.
	 *
	 * @var SkillRequirementService&MockObject
	 */
	private SkillRequirementService&MockObject $requirements;

	/**
	 * Set up the collaborators: XP left is what was earned minus the cost of each skill held.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$this->fetcher->method('getObject')->willReturnCallback(
			static function (string $objectType, string $id): array {
				if ($id === 'sk-gone') {
					throw new RuntimeException('deleted');
				}

				return ['id' => $id, 'name' => ['sk-sword' => 'Swordsmanship', 'sk-herb' => 'Kruidenkunde', 'sk-free' => 'Dancing'][$id] ?? ''];
			}
		);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->engine = $this->createMock(originalClassName: CharacterService::class);
		$this->engine->method('calculateCharacter')->willReturnCallback(
			static function (array $character): array {
				$left = self::EARNED;
				foreach ((array)($character['skills'] ?? []) as $skill) {
					$left -= (self::COSTS[$skill] ?? 0);
				}

				return ['stats' => ['xp' => ['value' => $left]]];
			}
		);
		$this->requirements = $this->createMock(originalClassName: SkillRequirementService::class);
		$this->requirements->method('resolveXpAbility')->willReturn('xp');
	}//end setUp()

	/**
	 * The subject.
	 *
	 * @return CharacterXpHistory The history over the mocked collaborators.
	 */
	private function history(): CharacterXpHistory {
		return new CharacterXpHistory(
			objectFetcher: $this->fetcher,
			characterService: $this->engine,
			requirementService: $this->requirements,
			logger: $this->logger
		);
	}//end history()

	/**
	 * A removed skill becomes one refund line with its cost, name, moment and user.
	 *
	 * @return void
	 */
	public function testARemovedSkillIsOneRefundLineWithItsCost(): void {
		$this->fetcher->expects($this->once())->method('getObjectLogs')->with('char-1')->willReturn([
			[
				'changed' => ['skills' => ['old' => ['sk-sword', 'sk-herb'], 'new' => ['sk-herb']]],
				'created' => '2026-05-02T10:00:00+00:00',
				'userName' => 'spelleider',
			],
		]);

		$lines = $this->history()->refunds(character: ['id' => 'char-1', 'skills' => ['sk-herb']]);

		$this->assertSame(
			expected: [
				[
					'type' => 'refund',
					'skillId' => 'sk-sword',
					'skillName' => 'Swordsmanship',
					'xp' => 10,
					'date' => '2026-05-02T10:00:00+00:00',
					'user' => 'spelleider',
				],
			],
			actual: $lines
		);
	}//end testARemovedSkillIsOneRefundLineWithItsCost()

	/**
	 * Lines come newest first, skills stored as objects are read by id, and a DateTime moment is formatted.
	 *
	 * @return void
	 */
	public function testLinesAreNewestFirstAndObjectEntriesAreReadById(): void {
		$this->fetcher->method('getObjectLogs')->willReturn([
			[
				'changed' => ['skills' => ['old' => [['id' => 'sk-herb']], 'new' => []]],
				'created' => new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
				'user' => 'admin',
			],
			[
				'changed' => ['skills' => ['old' => [['uuid' => 'sk-sword'], 'sk-free'], 'new' => ['sk-free']]],
				'created' => '2026-03-01T09:00:00+00:00',
				'user' => 'player',
			],
		]);

		$lines = $this->history()->refunds(character: ['id' => 'char-1', 'skills' => []]);

		$this->assertSame(expected: ['sk-sword', 'sk-herb'], actual: array_column($lines, 'skillId'));
		$this->assertSame(expected: [10, 6], actual: array_column($lines, 'xp'));
		$this->assertSame(expected: ['2026-03-01T09:00:00+00:00', '2026-01-01T09:00:00+00:00'], actual: array_column($lines, 'date'));
		$this->assertSame(expected: ['player', 'admin'], actual: array_column($lines, 'user'));
	}//end testLinesAreNewestFirstAndObjectEntriesAreReadById()

	/**
	 * Rows that did not change the skills, or only added one, give no line.
	 *
	 * @return void
	 */
	public function testRowsWithoutARemovalGiveNoLine(): void {
		$this->fetcher->method('getObjectLogs')->willReturn([
			['changed' => ['name' => ['old' => 'Mirela', 'new' => 'Mirela Vos']], 'created' => '2026-01-01'],
			['changed' => ['skills' => ['old' => ['sk-herb'], 'new' => ['sk-herb', 'sk-sword']]], 'created' => '2026-01-02'],
			['changed' => ['skills' => ['new' => ['sk-herb']]], 'created' => '2026-01-03'],
			['changed' => ['skills' => 'not-a-change'], 'created' => '2026-01-04'],
		]);

		$this->assertSame(expected: [], actual: $this->history()->refunds(character: ['id' => 'char-1', 'skills' => ['sk-herb', 'sk-sword']]));
	}//end testRowsWithoutARemovalGiveNoLine()

	/**
	 * A skill that can no longer be read keeps its line with an empty name.
	 *
	 * @return void
	 */
	public function testADeletedSkillKeepsItsLineWithoutAName(): void {
		$this->fetcher->method('getObjectLogs')->willReturn([
			['changed' => ['skills' => ['old' => ['sk-gone'], 'new' => []]], 'created' => '2026-02-01'],
		]);

		$lines = $this->history()->refunds(character: ['id' => 'char-1', 'skills' => []]);

		$this->assertCount(expectedCount: 1, haystack: $lines);
		$this->assertSame(expected: 'sk-gone', actual: $lines[0]['skillId']);
		$this->assertSame(expected: '', actual: $lines[0]['skillName']);
		$this->assertSame(expected: 0, actual: $lines[0]['xp']);
	}//end testADeletedSkillKeepsItsLineWithoutAName()

	/**
	 * Without an XP ability the refund is 0, never negative or an error.
	 *
	 * @return void
	 */
	public function testNoXpAbilityMeansARefundOfZero(): void {
		$this->requirements = $this->createMock(originalClassName: SkillRequirementService::class);
		$this->requirements->method('resolveXpAbility')->willReturn(null);
		$this->fetcher->method('getObjectLogs')->willReturn([
			['changed' => ['skills' => ['old' => ['sk-sword'], 'new' => []]], 'created' => '2026-02-01'],
		]);

		$lines = $this->history()->refunds(character: ['id' => 'char-1', 'skills' => []]);

		$this->assertSame(expected: 0, actual: $lines[0]['xp']);
	}//end testNoXpAbilityMeansARefundOfZero()

	/**
	 * An unreadable audit trail is an empty history and a warning, not an error.
	 *
	 * @return void
	 */
	public function testAnUnreadableAuditTrailIsAnEmptyHistory(): void {
		$this->fetcher->method('getObjectLogs')->willThrowException(new RuntimeException('OpenRegister down'));
		$this->logger->expects($this->once())->method('warning')->with(
			$this->stringContains(string: 'could not be read'),
			$this->callback(callback: static fn (array $context): bool => $context['character'] === 'char-1' && $context['exception'] === 'OpenRegister down')
		);

		$this->assertSame(expected: [], actual: $this->history()->refunds(character: ['id' => 'char-1']));
	}//end testAnUnreadableAuditTrailIsAnEmptyHistory()

	/**
	 * A character without an id has no history and the audit trail is not read.
	 *
	 * @return void
	 */
	public function testACharacterWithoutAnIdHasNoHistory(): void {
		$this->fetcher->expects($this->never())->method('getObjectLogs');

		$this->assertSame(expected: [], actual: $this->history()->refunds(character: ['name' => 'Nameless']));
	}//end testACharacterWithoutAnIdHasNoHistory()
}//end class
