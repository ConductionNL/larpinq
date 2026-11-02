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
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->expects(self::never())->method(self::anything());
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$normaliser = new IdListNormaliser();

		return new UniqueHolderVeto(new UniqueHolderService($fetcher, $normaliser), $normaliser, $l10n);
	}//end veto()

	/**
	 * Item and condition writes that add no holder of a unique object are not refused.
	 *
	 * @return void
	 */
	public function testWritesThatAddNoHolderAreNotRefused(): void {
		$writes = [
			'a non-unique item gains a holder' => ['item', new ObjectWrite(['unique' => false, 'characters' => ['a', 'b']], ['characters' => ['a']], 'i-1', false)],
			'a non-unique condition is created' => ['condition', new ObjectWrite(['characters' => ['a']], [], 'c-1', true)],
			'a unique item keeps its holders' => ['item', new ObjectWrite(['unique' => true, 'characters' => ['a']], ['unique' => true, 'characters' => ['a']], 'i-2', false)],
			'a character drops an item' => ['character', new ObjectWrite(['items' => []], ['items' => ['i-1']], 'ch-1', false)],
		];

		foreach ($writes as $name => [$subject, $write]) {
			self::assertNull($this->veto()->uncheckedRefusal(subject: $subject, write: $write), $name);
		}
	}//end testWritesThatAddNoHolderAreNotRefused()

	/**
	 * Writes that add a holder get the try-again refusal on the changed field.
	 *
	 * @return void
	 */
	public function testWritesThatAddAHolderAreRefusedOnTheChangedField(): void {
		$writes = [
			'characters' => ['item', new ObjectWrite(['unique' => true, 'characters' => ['a', 'b']], ['unique' => true, 'characters' => ['a']], 'i-1', false)],
			'unique' => ['item', new ObjectWrite(['unique' => true, 'characters' => ['a']], ['unique' => false, 'characters' => ['a']], 'i-1', false)],
			'conditions' => ['character', new ObjectWrite(['conditions' => ['c-1']], [], 'ch-1', false)],
		];

		foreach ($writes as $field => [$subject, $write]) {
			$errors = $this->veto()->uncheckedRefusal(subject: $subject, write: $write);
			self::assertIsArray($errors, $field);
			self::assertStringEndsWith('_unchecked', $errors[$field][0]['code'], $field);
		}
	}//end testWritesThatAddAHolderAreRefusedOnTheChangedField()
}//end class
