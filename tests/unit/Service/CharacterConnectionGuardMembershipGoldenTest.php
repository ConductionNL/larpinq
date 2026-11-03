<?php

/**
 * Golden test for the membership check of CharacterConnectionGuard.
 *
 * Every combination of the stored status (none for a create), the written
 * status, a role change, the caller (leader, owner, both, neither, judged
 * from the stored faction and character) and a moved membership runs through
 * checkMembership() over a fake register, with a few unreadable and
 * non-group cases. The refusal of each case is compared, as JSON text, with
 * fixtures/connection-guard-membership.golden.json. Set RECORD_GOLDEN=1 to
 * rewrite it.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-connections/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CharacterConnectionGuard;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\TestCase;

/**
 * Pins every refusal checkMembership() gives.
 *
 * @covers \OCA\Larpinq\Service\CharacterConnectionGuard
 * @uses   \OCA\Larpinq\Service\MembershipCaller
 */
class CharacterConnectionGuardMembershipGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/connection-guard-membership.golden.json';

	private const STORED = [null, 'requested', 'invited', 'active', 'declined', 'left', 'removed', 'bogus', ''];

	private const WRITTEN = [null, 'requested', 'invited', 'active', 'declined', 'left', 'removed', 'bogus'];

	/**
	 * Callers by name: the uid that leads the group and the uid that owns the character.
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private const CALLERS = [
		'leader' => ['me', 'other'],
		'owner' => ['other', 'me'],
		'both' => ['me', 'me'],
		'neither' => ['other', 'other'],
	];

	/**
	 * The guard over a register holding the given faction and character.
	 *
	 * @param array<string,array<string,mixed>> $objects Objects by id.
	 *
	 * @return CharacterConnectionGuard The guard.
	 */
	private function guard(array $objects): CharacterConnectionGuard {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->method('getObject')->willReturnCallback(
			function (string $objectType, string $id) use ($objects): array {
				if (isset($objects[$objectType . ':' . $id]) === false) {
					throw new \RuntimeException('not found');
				}

				return $objects[$objectType . ':' . $id];
			}
		);

		return new CharacterConnectionGuard(objectFetcher: $fetcher);
	}//end guard()

	/**
	 * Every case: name => [membership, old or null, objects, user].
	 *
	 * @return array<string,array{0:array<string,mixed>,1:array<string,mixed>|null,2:array<string,array<string,mixed>>,3:string}>
	 */
	private function cases(): array {
		$cases = [];
		foreach (self::CALLERS as $caller => [$leaderUid, $ownerUid]) {
			$objects = [
				'faction:f1' => ['id' => 'f1', 'kind' => 'group', 'leaderOwnerUid' => $leaderUid],
				'faction:f2' => ['id' => 'f2', 'kind' => 'group', 'leaderOwnerUid' => $leaderUid],
				'character:c1' => ['id' => 'c1', 'ownerUid' => $ownerUid],
			];
			foreach (self::STORED as $from) {
				foreach (self::WRITTEN as $target) {
					foreach (['same role' => 'member', 'role changed' => 'officer', 'no role' => null] as $roleCase => $role) {
						$membership = ['faction' => 'f1', 'character' => ['id' => 'c1']];
						if ($target !== null) {
							$membership['status'] = $target;
						}

						if ($role !== null) {
							$membership['role'] = $role;
						}

						$old = null;
						if ($from !== null) {
							$old = ['faction' => ['id' => 'f1'], 'character' => 'c1', 'role' => 'member'];
							if ($from !== '') {
								$old['status'] = $from;
							}
						}

						$name = sprintf('%s %s -> %s, %s', $caller, ($from ?? 'create'), ($target ?? 'unset'), $roleCase);
						$cases[$name] = [$membership, $old, $objects, 'me'];
					}
				}
			}

			$active = ['faction' => 'f1', 'character' => 'c1', 'status' => 'active'];
			$cases[$caller . ' moved to another faction'] = [['faction' => 'f2'] + $active, $active, $objects, 'me'];
			$cases[$caller . ' moved to another character'] = [['character' => 'c2'] + $active, $active, $objects, 'me'];
			$cases[$caller . ' unreadable faction'] = [['faction' => 'f9', 'character' => 'c1'], null, $objects, 'me'];
			$cases[$caller . ' unreadable character'] = [['faction' => 'f1', 'character' => 'c9'], null, $objects, 'me'];
			$cases[$caller . ' no faction'] = [['character' => 'c1'], null, $objects, 'me'];
			$cases[$caller . ' a faction, not a group'] = [
				['faction' => 'f1', 'character' => 'c1', 'status' => 'requested'],
				null,
				['faction:f1' => ['id' => 'f1', 'kind' => 'faction', 'leaderOwnerUid' => $leaderUid]] + $objects,
				'me',
			];
			foreach (['member', 'officer', null] as $role) {
				$written = ['faction' => 'f1', 'character' => 'c1', 'status' => 'active'];
				if ($role !== null) {
					$written['role'] = $role;
				}

				$cases[sprintf('%s stored without a role, written %s', $caller, ($role ?? 'without one'))] = [
					$written,
					['faction' => 'f1', 'character' => 'c1', 'status' => 'active'],
					$objects,
					'me',
				];
			}

			$cases[$caller . ' empty user'] = [['faction' => 'f1', 'character' => 'c1', 'status' => 'requested'], null, $objects, ''];
		}

		return $cases;
	}//end cases()

	/**
	 * Every case gives the recorded refusal.
	 *
	 * @return void
	 */
	public function testRefusalsMatchTheGolden(): void {
		$actual = [];
		foreach ($this->cases() as $name => [$membership, $old, $objects, $user]) {
			$refusal = $this->guard(objects: $objects)->checkMembership(membership: $membership, old: $old, userId: $user);
			$actual[$name] = json_encode(value: $refusal, flags: JSON_THROW_ON_ERROR);
		}

		if (getenv('RECORD_GOLDEN') === '1') {
			$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
			file_put_contents(filename: self::FIXTURE, data: json_encode(value: $actual, flags: $flags) . "\n");
		}

		$expected = json_decode(json: (string)file_get_contents(filename: self::FIXTURE), associative: true, flags: JSON_THROW_ON_ERROR);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($actual as $name => $json) {
			self::assertSame(expected: $expected[$name], actual: $json, message: $name);
		}
	}//end testRefusalsMatchTheGolden()
}//end class
