<?php

/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit
 *
 * @spec openspec/specs/event-casting/spec.md#requirement-players-rank-characters-open-for-casting-req-ecc-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Larpinq\Service\CastingPreferenceGuard;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * REQ-ECC-001: Anna ranks until the casting deadline, on her own registration,
 * only characters open on the event, at most five.
 *
 * @covers \OCA\Larpinq\Service\CastingPreferenceGuard
 */
class CastingPreferenceGuardTest extends TestCase {

	private const EVENT = 'e-winter-court';

	private RegisterObjectFetcher&MockObject $fetcher;

	private IGroupManager&MockObject $groups;

	/** @var array<string, array<string, array<string, mixed>>> Objects per type and id. */
	private array $objects = [];

	/** @var list<array<string, mixed>> The stored rankings of Anna's registration. */
	private array $rankings = [];

	/** @var list<array{string, array<string, mixed>, ?int}> The list reads. */
	private array $listReads = [];

	/** Whether reading the stored rankings fails. */
	private bool $listFails = false;

	protected function setUp(): void {
		$this->objects = [
			'event' => [self::EVENT => ['id' => self::EVENT, 'castingDeadline' => '2026-11-01T00:00:00+00:00']],
			'registration' => [
				'r-anna' => ['id' => 'r-anna', 'event' => self::EVENT, 'playerUid' => 'anna'],
				'r-pieter' => ['id' => 'r-pieter', 'event' => self::EVENT, 'playerUid' => 'pieter'],
				'r-other' => ['id' => 'r-other', 'event' => 'e-summer', 'playerUid' => 'anna'],
			],
			'character' => [
				'heir' => ['id' => 'heir', 'castingEvent' => self::EVENT],
				'aldric' => ['id' => 'aldric', 'castingEvent' => null],
			],
		];

		$this->fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectOfType')->willReturnCallback(
			function (string $objectType, string $id): array {
				if (isset($this->objects[$objectType][$id]) === false) {
					throw new DoesNotExistException('missing');
				}

				return $this->objects[$objectType][$id];
			}
		);
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $filters, ?int $limit = null): array {
				$this->listReads[] = [$objectType, $filters, $limit];
				if ($this->listFails === true) {
					throw new \RuntimeException('unavailable');
				}

				return $this->rankings;
			}
		);

		$this->groups = $this->createMock(originalClassName: IGroupManager::class);
		$this->groups->method('isInGroup')->willReturnCallback(static fn (string $uid, string $group): bool => $uid === 'gm' && $group === 'gamemasters');
		$this->groups->method('isAdmin')->willReturn(false);
	}

	private function guard(): CastingPreferenceGuard {
		return new CastingPreferenceGuard(fetcher: $this->fetcher, groups: $this->groups);
	}

	/**
	 * @param array<string, mixed> $override Fields to change.
	 *
	 * @return array<string, mixed> Anna's ranking of The Heir.
	 */
	private function ranking(array $override = []): array {
		return array_merge(['event' => self::EVENT, 'registration' => 'r-anna', 'character' => 'heir', 'rank' => 1], $override);
	}

	private function before(): DateTimeImmutable {
		return new DateTimeImmutable('2026-10-15T12:00:00+00:00');
	}

	public function testAnnaRanksTheHeirBeforeTheDeadline(): void {
		$this->assertNull($this->guard()->check(candidate: $this->ranking(), uid: 'anna', now: $this->before()));
		$this->assertSame(
			expected: [['castingpreference', ['registration' => 'r-anna'], 6]],
			actual: $this->listReads
		);
	}

	public function testRankingIsRefusedFromTheDeadlineOn(): void {
		foreach (['2026-11-01T00:00:00+00:00', '2026-12-01T00:00:00+00:00'] as $moment) {
			$errors = $this->guard()->check(candidate: $this->ranking(), uid: 'anna', now: new DateTimeImmutable($moment));

			$this->assertSame(expected: 'casting_closed', actual: $errors['code'] ?? null, message: $moment);
			$this->assertArrayHasKey(key: 'rank', array: $errors['fields']);
		}
	}

	public function testAnEventWithoutDeadlineStaysOpen(): void {
		$this->objects['event'][self::EVENT]['castingDeadline'] = null;
		$this->assertNull($this->guard()->check(candidate: $this->ranking(), uid: 'anna', now: new DateTimeImmutable('2030-01-01')));

		$this->objects['event'][self::EVENT]['castingDeadline'] = 'not a date';
		$this->assertNull($this->guard()->check(candidate: $this->ranking(), uid: 'anna', now: new DateTimeImmutable('2030-01-01')));
	}

	public function testGameMastersAndSystemWritesPassAfterTheDeadline(): void {
		$late = new DateTimeImmutable('2026-12-01');

		$this->assertNull($this->guard()->check(candidate: $this->ranking(), uid: 'gm', now: $late));
		$this->assertNull($this->guard()->check(candidate: $this->ranking(), uid: '', now: $late));
		$this->assertSame(expected: [], actual: $this->listReads, message: 'nothing is read for a game master');
	}

	public function testAPlayerRanksOnlyOnTheirOwnRegistrationForTheEvent(): void {
		$cases = [
			'Pieter writes on Anna' => [$this->ranking(), 'pieter'],
			'another event' => [$this->ranking(['registration' => 'r-other']), 'anna'],
			'unknown registration' => [$this->ranking(['registration' => 'r-missing']), 'anna'],
		];
		foreach ($cases as $label => [$candidate, $uid]) {
			$errors = $this->guard()->check(candidate: $candidate, uid: $uid, now: $this->before());

			$this->assertSame(expected: 'casting_registration_not_yours', actual: $errors['code'] ?? null, message: $label);
			$this->assertArrayHasKey(key: 'registration', array: $errors['fields']);
		}
	}

	public function testOnlyCharactersOpenOnTheEventCanBeRanked(): void {
		foreach (['aldric', 'missing', ''] as $character) {
			$errors = $this->guard()->check(candidate: $this->ranking(['character' => $character]), uid: 'anna', now: $this->before());

			$this->assertSame(expected: 'casting_character_not_open', actual: $errors['code'] ?? null, message: $character);
		}
	}

	public function testAnUnknownEventIsRefused(): void {
		$errors = $this->guard()->check(candidate: $this->ranking(['event' => 'e-missing']), uid: 'anna', now: $this->before());

		$this->assertSame(expected: 'casting_event_unknown', actual: $errors['code'] ?? null);
	}

	public function testASixthRankingIsRefusedButReRankingOneOfFivePasses(): void {
		$this->rankings = array_map(static fn (int $i): array => ['id' => 'p' . $i], range(1, 5));

		$errors = $this->guard()->check(candidate: $this->ranking(), uid: 'anna', now: $this->before());
		$this->assertSame(expected: 'casting_too_many', actual: $errors['code'] ?? null);

		$this->assertNull($this->guard()->check(candidate: $this->ranking(['id' => 'p3', 'rank' => 2]), uid: 'anna', now: $this->before()));
	}

	public function testARankingIsRefusedWhenTheStoredRankingsCannotBeRead(): void {
		$this->listFails = true;

		$errors = $this->guard()->check(candidate: $this->ranking(), uid: 'anna', now: $this->before());
		$this->assertSame(expected: 'casting_too_many', actual: $errors['code'] ?? null);
	}
}
