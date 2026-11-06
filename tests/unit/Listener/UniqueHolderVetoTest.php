<?php

/**
 * Unit tests for UniqueHolderVeto::uncheckedRefusal().
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use OCA\Larpinq\Listener\ObjectWrite;
use OCA\Larpinq\Listener\UniqueHolderVeto;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\UniqueHolderService;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * REQ-UHE-006: the try-again refusal applies only to writes that add a holder.
 *
 * @covers \OCA\Larpinq\Listener\UniqueHolderVeto
 * @uses   \OCA\Larpinq\Listener\ObjectWrite
 * @uses   \OCA\Larpinq\Service\HolderQuery
 * @uses   \OCA\Larpinq\Service\IdListNormaliser
 * @uses   \OCA\Larpinq\Service\UniqueHolderService
 */
class UniqueHolderVetoTest extends TestCase {

	/**
	 * The veto, with a register that is never read.
	 *
	 * @return UniqueHolderVeto The veto.
	 */
	private function veto(): UniqueHolderVeto {
		$fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
		$fetcher->expects(self::never())->method(self::anything());
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$normaliser = new IdListNormaliser();

		return new UniqueHolderVeto(
			holders: new UniqueHolderService(objectFetcher: $fetcher, idNormaliser: $normaliser),
			idNormaliser: $normaliser,
			l10n: $l10n
		);
	}//end veto()

	/**
	 * Item and condition writes that add no holder of a unique object are not refused.
	 *
	 * @return void
	 */
	public function testWritesThatAddNoHolderAreNotRefused(): void {
		$writes = [
			'a non-unique item gains a holder' => ['item', new ObjectWrite(
				data: ['unique' => false, 'characters' => ['a', 'b']],
				old: ['characters' => ['a']],
				id: 'i-1',
				isCreate: false
			)],
			'a non-unique condition is created' => ['condition', new ObjectWrite(data: ['characters' => ['a']], old: [], id: 'c-1', isCreate: true)],
			'a unique item keeps its holders' => ['item', new ObjectWrite(
				data: ['unique' => true, 'characters' => ['a']],
				old: ['unique' => true, 'characters' => ['a']],
				id: 'i-2',
				isCreate: false
			)],
			'a character drops an item' => ['character', new ObjectWrite(data: ['items' => []], old: ['items' => ['i-1']], id: 'ch-1', isCreate: false)],
		];

		foreach ($writes as $name => [$subject, $write]) {
			self::assertNull(actual: $this->veto()->uncheckedRefusal(subject: $subject, write: $write), message: $name);
		}
	}//end testWritesThatAddNoHolderAreNotRefused()

	/**
	 * Writes that add a holder get the try-again refusal on the changed field.
	 *
	 * @return void
	 */
	public function testWritesThatAddAHolderAreRefusedOnTheChangedField(): void {
		$writes = [
			'characters' => ['item', new ObjectWrite(
				data: ['unique' => true, 'characters' => ['a', 'b']],
				old: ['unique' => true, 'characters' => ['a']],
				id: 'i-1',
				isCreate: false
			)],
			'unique' => ['item', new ObjectWrite(
				data: ['unique' => true, 'characters' => ['a']],
				old: ['unique' => false, 'characters' => ['a']],
				id: 'i-1',
				isCreate: false
			)],
			'conditions' => ['character', new ObjectWrite(data: ['conditions' => ['c-1']], old: [], id: 'ch-1', isCreate: false)],
		];

		foreach ($writes as $field => [$subject, $write]) {
			$errors = $this->veto()->uncheckedRefusal(subject: $subject, write: $write);
			self::assertIsArray(actual: $errors, message: $field);
			self::assertStringEndsWith(suffix: '_unchecked', string: $errors[$field][0]['code'], message: $field);
		}
	}//end testWritesThatAddAHolderAreRefusedOnTheChangedField()
}//end class
