<?php

/**
 * Unit tests for CharacterResubmissionService: a rejected character goes back to waiting.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CharacterResubmissionService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\ResubmissionRefusedException;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Who may submit a rejected character again, and what the write carries.
 */
class CharacterResubmissionServiceTest extends TestCase {

	private const MIRELA = 'c1000000-0000-4000-8000-000000000001';

	/** @var RegisterObjectFetcher&MockObject The fetcher double. */
	private RegisterObjectFetcher&MockObject $fetcher;

	/**
	 * Mirela, rejected, owned by Anna.
	 *
	 * @param string $approved The approval state.
	 *
	 * @return array<string, mixed>
	 */
	private function mirela(string $approved = 'rejected'): array {
		return [
			'id' => self::MIRELA,
			'name' => 'Mirela the Wanderer',
			'ownerUid' => 'anna',
			'approved' => $approved,
			'rejectionReason' => 'Pick one bloodline, not two',
			'slNotesPrivate' => 'kept',
			'@self' => ['id' => self::MIRELA],
		];
	}//end mirela()

	/**
	 * The service over a fetcher that reads the given character.
	 *
	 * @param array<string, mixed>|null $character The stored character, or null for none.
	 *
	 * @return CharacterResubmissionService
	 */
	private function service(?array $character): CharacterResubmissionService {
		$this->fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectOfType')->willReturnCallback(
			static function (string $objectType, string $id) use ($character): array {
				if ($character === null || $objectType !== 'character' || $id !== self::MIRELA) {
					throw new DoesNotExistException('no character');
				}

				return $character;
			}
		);

		return new CharacterResubmissionService(fetcher: $this->fetcher);
	}//end service()

	/**
	 * The owner submits Mirela again: she waits for approval, and only the state changes.
	 *
	 * @return void
	 */
	public function testTheOwnerSubmitsARejectedCharacterAgain(): void {
		$service = $this->service(character: $this->mirela());
		$expected = $this->mirela(approved: 'no');
		unset($expected['@self']);
		$this->fetcher->expects($this->once())
			->method('saveObjectWithAppAuthority')
			->with('character', $expected, self::MIRELA)
			->willReturn($expected);

		self::assertSame(expected: 'no', actual: $service->resubmit(characterId: self::MIRELA, uid: 'anna', isGameMaster: false)['approved']);
	}//end testTheOwnerSubmitsARejectedCharacterAgain()

	/**
	 * A game master may do it for the owner.
	 *
	 * @return void
	 */
	public function testAGameMasterMaySubmitItAgain(): void {
		$service = $this->service(character: $this->mirela());
		$this->fetcher->expects($this->once())->method('saveObjectWithAppAuthority')->willReturnArgument(1);

		self::assertSame(expected: 'no', actual: $service->resubmit(characterId: self::MIRELA, uid: 'joris', isGameMaster: true)['approved']);
	}//end testAGameMasterMaySubmitItAgain()

	/**
	 * Another player is refused with 403 and nothing is written.
	 *
	 * @return void
	 */
	public function testAnotherPlayerIsRefused(): void {
		$this->assertRefused(character: $this->mirela(), uid: 'sem', status: 403, message: 'Only the owner and game masters submit this character');
	}//end testAnotherPlayerIsRefused()

	/**
	 * A character without an owner is not anyone's to submit but a game master's.
	 *
	 * @return void
	 */
	public function testACharacterWithoutOwnerIsRefusedToPlayers(): void {
		$character = $this->mirela();
		$character['ownerUid'] = '';
		$this->assertRefused(character: $character, uid: '', status: 403, message: 'Only the owner and game masters submit this character');
	}//end testACharacterWithoutOwnerIsRefusedToPlayers()

	/**
	 * Only a rejected character goes back; one waiting or approved is refused with 409.
	 *
	 * @return void
	 */
	public function testOnlyARejectedCharacterIsSubmittedAgain(): void {
		$this->assertRefused(character: $this->mirela(approved: 'no'), uid: 'anna', status: 409, message: 'Only a rejected character is submitted again');
		$this->assertRefused(
			character: $this->mirela(approved: 'approved'),
			uid: 'anna',
			status: 409,
			message: 'Only a rejected character is submitted again'
		);
	}//end testOnlyARejectedCharacterIsSubmittedAgain()

	/**
	 * A character that cannot be read is answered 404.
	 *
	 * @return void
	 */
	public function testAMissingCharacterIs404(): void {
		$this->assertRefused(character: null, uid: 'anna', status: 404, message: 'Character not found');
	}//end testAMissingCharacterIs404()

	/**
	 * Assert a resubmission is refused with a status and message and writes nothing.
	 *
	 * @param array<string, mixed>|null $character The stored character.
	 * @param string                    $uid       The caller.
	 * @param int                       $status    The expected status.
	 * @param string                    $message   The expected message.
	 *
	 * @return void
	 */
	private function assertRefused(?array $character, string $uid, int $status, string $message): void {
		$service = $this->service(character: $character);
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');
		try {
			$service->resubmit(characterId: self::MIRELA, uid: $uid, isGameMaster: false);
			self::fail(message: 'the resubmission should be refused');
		} catch (ResubmissionRefusedException $refused) {
			self::assertSame(expected: $status, actual: $refused->getStatus());
			self::assertSame(expected: $message, actual: $refused->getMessage());
		}
	}//end assertRefused()
}//end class
