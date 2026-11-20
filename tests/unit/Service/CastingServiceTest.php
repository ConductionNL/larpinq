<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CastingRefusedException;
use OCA\Larpinq\Service\CastingService;
use OCA\Larpinq\Service\CastingSolver;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * REQ-ECC-002 and REQ-ECC-003 on the seed of the design: Winter Court 2026.
 *
 * @covers \OCA\Larpinq\Service\CastingService
 * @uses \OCA\Larpinq\Service\CastingSolver
 * @uses \OCA\Larpinq\Service\CastingRefusedException
 */
class CastingServiceTest extends TestCase {

	private const EVENT = 'e-winter-court';

	private RegisterObjectFetcher&MockObject $fetcher;

	/** @var array<string, list<array<string, mixed>>> Rows per object type. */
	private array $rows = [];

	/** @var list<array{type: string, data: array<string, mixed>, uuid: ?string}> */
	private array $saves = [];

	/** @var list<array{type: string, filters: array<string, mixed>}> */
	private array $reads = [];

	protected function setUp(): void {
		$this->rows = [
			'character' => [
				['id' => 'chancellor', 'name' => 'The Chancellor'],
				['id' => 'spy', 'name' => 'The Spy'],
				['id' => 'heir', 'name' => 'The Heir'],
				['id' => 'bard', 'name' => 'The Bard'],
			],
			'registration' => [
				['id' => 'r-sanne', 'player' => 'pl-sanne', 'submittedAt' => '2026-09-03T10:00:00+00:00'],
				['id' => 'r-anna', 'player' => 'pl-anna', 'submittedAt' => '2026-09-01T10:00:00+00:00'],
				['id' => 'r-pieter', 'player' => 'pl-pieter', 'submittedAt' => '2026-09-02T10:00:00+00:00'],
				['id' => 'r-joris', 'player' => 'pl-joris', 'submittedAt' => '2026-09-04T10:00:00+00:00'],
			],
			'castingpreference' => [
				['registration' => 'r-anna', 'character' => 'heir', 'rank' => 1],
				['registration' => 'r-anna', 'character' => 'spy', 'rank' => 2],
				['registration' => 'r-pieter', 'character' => 'heir', 'rank' => 1],
				['registration' => 'r-pieter', 'character' => 'bard', 'rank' => 2],
				['registration' => 'r-sanne', 'character' => 'spy', 'rank' => 1],
				// Not counted: a registration that is not accepted, a character that is not open, a rank out of range.
				['registration' => 'r-gone', 'character' => 'heir', 'rank' => 1],
				['registration' => 'r-sanne', 'character' => 'king', 'rank' => 2],
				['registration' => 'r-sanne', 'character' => 'bard', 'rank' => 9],
			],
		];

		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $filters, ?int $limit = null): array {
				$this->reads[] = ['type' => $objectType, 'filters' => $filters];
				return $this->rows[$objectType] ?? [];
			}
		);
		$this->fetcher->method('saveObject')->willReturnCallback(
			function (string $objectType, array $data, ?string $uuid = null): array {
				$this->saves[] = ['type' => $objectType, 'data' => $data, 'uuid' => $uuid];
				return $data;
			}
		);
	}

	private function service(): CastingService {
		return new CastingService($this->fetcher, new CastingSolver(), new NullLogger());
	}

	public function testTheSeedProposalCastsEveryRankingPlayerAndWritesNothing(): void {
		$proposal = $this->service()->propose(self::EVENT);

		self::assertSame(
			[
				['registration' => 'r-anna', 'character' => 'heir', 'rank' => 1, 'characterName' => 'The Heir'],
				['registration' => 'r-pieter', 'character' => 'bard', 'rank' => 2, 'characterName' => 'The Bard'],
				['registration' => 'r-sanne', 'character' => 'spy', 'rank' => 1, 'characterName' => 'The Spy'],
			],
			$proposal['pairs']
		);
		self::assertSame([], $proposal['unmatched'], 'Joris ranked nothing, so he takes no part.');
		self::assertSame([], $this->saves);
		self::assertSame(
			[
				['type' => 'character', 'filters' => ['castingEvent' => self::EVENT]],
				['type' => 'registration', 'filters' => ['event' => self::EVENT, 'status' => 'accepted']],
				['type' => 'castingpreference', 'filters' => ['event' => self::EVENT]],
			],
			$this->reads
		);
	}

	public function testTheProposalCountsTheRanksPerCharacter(): void {
		$counts = array_column($this->service()->propose(self::EVENT)['characters'], 'ranked', 'name');

		self::assertSame([1 => 2, 2 => 0, 3 => 0, 4 => 0, 5 => 0], $counts['The Heir']);
		self::assertSame([1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 0], $counts['The Spy']);
		self::assertSame([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], $counts['The Chancellor']);
	}

	public function testAPlayerWithoutARankedMatchIsListed(): void {
		$this->rows['character'] = [['id' => 'heir', 'name' => 'The Heir']];

		$proposal = $this->service()->propose(self::EVENT);

		self::assertSame(['r-anna'], array_column($proposal['pairs'], 'registration'), 'The earliest sign-up wins the tie on the heir.');
		self::assertSame(['r-pieter'], $proposal['unmatched']);
	}

	public function testUnreadableDataRefusesTheProposal(): void {
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectsWithAppAuthority')->willThrowException(new \RuntimeException('not configured'));

		$this->expectException(CastingRefusedException::class);
		$this->service()->propose(self::EVENT);
	}

	public function testConfirmingWritesOwnershipAndTheRegistrationsCharacter(): void {
		$result = $this->service()->confirm(self::EVENT, [['registration' => 'r-anna', 'character' => 'heir']]);

		self::assertSame([['registration' => 'r-anna', 'character' => 'heir']], $result['written']);
		self::assertSame([], $result['refused']);
		self::assertSame(
			[
				['type' => 'character', 'data' => ['ocName' => 'pl-anna', 'castingEvent' => null], 'uuid' => 'heir'],
				['type' => 'registration', 'data' => ['character' => 'heir'], 'uuid' => 'r-anna'],
			],
			$this->saves
		);
	}

	public function testConfirmingRefusesPairsOutsideTheEventByName(): void {
		$result = $this->service()->confirm(
			self::EVENT,
			[
				['registration' => 'r-anna', 'character' => 'king'],
				['registration' => 'r-gone', 'character' => 'spy'],
				['registration' => 'r-pieter', 'character' => 'bard'],
				['registration' => 'r-sanne', 'character' => 'bard'],
			]
		);

		self::assertSame([['registration' => 'r-pieter', 'character' => 'bard']], $result['written']);
		self::assertSame(
			[
				['registration' => 'r-anna', 'character' => 'king', 'reason' => 'character-not-open'],
				['registration' => 'r-gone', 'character' => 'spy', 'reason' => 'registration-not-accepted'],
				['registration' => 'r-sanne', 'character' => 'bard', 'reason' => 'cast-twice'],
			],
			$result['refused']
		);
		self::assertCount(2, $this->saves);
	}

	public function testARefusedWriteIsReportedAndTheRegistrationLeftAlone(): void {
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(fn (string $objectType): array => $this->rows[$objectType] ?? []);
		$this->fetcher->expects(self::once())->method('saveObject')->willThrowException(new \RuntimeException('unique holder'));

		$result = $this->service()->confirm(self::EVENT, [['registration' => 'r-anna', 'character' => 'heir']]);

		self::assertSame([], $result['written']);
		self::assertSame('character-not-written', $result['refused'][0]['reason']);
	}
}//end class
