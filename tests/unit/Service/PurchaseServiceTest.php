<?php

/**
 * Tests for buying items with in-game money (REQ-RPG-001).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\PurchaseRefusedException;
use OCA\Larpinq\Service\PurchaseService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * REQ-RPG-001: a purchase adds the item and takes the money in one write, or changes nothing.
 *
 * @covers \OCA\Larpinq\Service\PurchaseService
 * @covers \OCA\Larpinq\Service\PurchaseRefusedException
 */
class PurchaseServiceTest extends TestCase {

	private const MIRELA = 'c1000000-0000-4000-8000-000000000001';
	private const ROPE = 'e1000000-0000-4000-8000-000000000001';
	private const CROWN = 'e1000000-0000-4000-8000-000000000002';
	private const LANTERN = 'e1000000-0000-4000-8000-000000000003';
	private const ALDMOOR = 'a1000000-0000-4000-8000-000000000001';

	/** @var RegisterObjectFetcher&MockObject The fetcher double. */
	private RegisterObjectFetcher&MockObject $fetcher;

	/** @var array<string,array<string,mixed>> Objects by id. */
	private array $objects = [];

	protected function setUp(): void {
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObjectOfType')->willReturnCallback(
			function (string $objectType, string $id): array {
				if (isset($this->objects[$id]) === false || $this->objects[$id]['type'] !== $objectType) {
					throw new DoesNotExistException('no ' . $objectType);
				}

				return $this->objects[$id]['data'];
			}
		);

		$this->put(type: 'character', data: [
			'id' => self::MIRELA,
			'name' => 'Mirela the Wanderer',
			'setting' => self::ALDMOOR,
			'gold' => 3,
			'silver' => 5,
			'copper' => 0,
			'items' => [self::LANTERN],
			'slNotesPrivate' => 'kept',
			'@self' => ['id' => self::MIRELA],
		]);
		$this->put(type: 'item', data: ['id' => self::ROPE, 'name' => 'Rope', 'setting' => self::ALDMOOR, 'price' => ['silver' => 2]]);
		$this->put(type: 'item', data: ['id' => self::CROWN, 'name' => 'Crown of Aldmoor', 'setting' => self::ALDMOOR, 'price' => ['gold' => 500]]);
		$this->put(type: 'item', data: ['id' => self::LANTERN, 'name' => 'Lantern', 'setting' => self::ALDMOOR, 'price' => ['copper' => 4]]);
	}//end setUp()

	/**
	 * Store an object the double answers.
	 *
	 * @param string              $type The object type.
	 * @param array<string,mixed> $data The object.
	 *
	 * @return void
	 */
	private function put(string $type, array $data): void {
		$this->objects[$data['id']] = ['type' => $type, 'data' => $data];
	}//end put()

	/**
	 * The service under test.
	 *
	 * @return PurchaseService The service.
	 */
	private function service(): PurchaseService {
		return new PurchaseService(fetcher: $this->fetcher);
	}//end service()

	/**
	 * Scenario "Mirela buys a rope": she carries it and has 3 silver left, in one write.
	 */
	public function testMirelaBuysARope(): void {
		$this->fetcher->expects($this->once())
			->method('saveObjectWithAppAuthority')
			->with(
				'character',
				$this->callback(function (array $data): bool {
					$this->assertSame(3, $data['silver']);
					$this->assertSame(3, $data['gold']);
					$this->assertSame(0, $data['copper']);
					$this->assertSame([self::LANTERN, self::ROPE], $data['items']);
					$this->assertSame('kept', $data['slNotesPrivate']);
					$this->assertArrayNotHasKey('@self', $data);
					return true;
				}),
				self::MIRELA
			)
			->willReturnCallback(static fn (string $type, array $data): array => $data);
		$service = $this->service();

		$saved = $service->buy(character: $service->character(characterId: self::MIRELA), itemId: self::ROPE);

		$this->assertSame(3, $saved['silver']);
		$this->assertContains(self::ROPE, $saved['items']);
	}//end testMirelaBuysARope()

	/**
	 * Scenario "Not enough money": refused, and nothing is written.
	 */
	public function testNotEnoughMoneyIsRefusedAndChangesNothing(): void {
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');
		$this->fetcher->expects($this->never())->method('saveObject');
		$service = $this->service();

		try {
			$service->buy(character: $service->character(characterId: self::MIRELA), itemId: self::CROWN);
			$this->fail('The purchase was not refused');
		} catch (PurchaseRefusedException $refused) {
			$this->assertSame('The character cannot pay this item', $refused->getMessage());
			$this->assertSame(409, $refused->getStatus());
		}

		$this->assertSame(3, $this->objects[self::MIRELA]['data']['gold']);
	}//end testNotEnoughMoneyIsRefusedAndChangesNothing()

	/**
	 * A purse short in one coin is refused even when another coin would cover it in value.
	 */
	public function testEachCoinIsPaidFromItsOwnPurse(): void {
		$this->put(type: 'item', data: ['id' => 'e1000000-0000-4000-8000-000000000009', 'setting' => self::ALDMOOR, 'price' => ['copper' => 1]]);
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');
		$service = $this->service();

		$this->expectException(PurchaseRefusedException::class);
		$this->expectExceptionMessage('The character cannot pay this item');

		$service->buy(character: $service->character(characterId: self::MIRELA), itemId: 'e1000000-0000-4000-8000-000000000009');
	}//end testEachCoinIsPaidFromItsOwnPurse()

	/**
	 * An item already carried, an item of another world and an unpriced item are refused with 409.
	 */
	public function testImpossiblePurchasesAreRefused(): void {
		$this->put(type: 'item', data: ['id' => 'e1000000-0000-4000-8000-000000000010', 'setting' => 'a1000000-0000-4000-8000-000000000002', 'price' => ['copper' => 0, 'silver' => 1]]);
		$this->put(type: 'item', data: ['id' => 'e1000000-0000-4000-8000-000000000011', 'setting' => self::ALDMOOR]);
		$this->fetcher->expects($this->never())->method('saveObjectWithAppAuthority');
		$service   = $this->service();
		$character = $service->character(characterId: self::MIRELA);

		$cases = [
			self::LANTERN => 'The character already carries this item',
			'e1000000-0000-4000-8000-000000000010' => 'This item belongs to another world',
			'e1000000-0000-4000-8000-000000000011' => 'This item has no price',
		];
		foreach ($cases as $itemId => $reason) {
			try {
				$service->buy(character: $character, itemId: $itemId);
				$this->fail('Buying ' . $itemId . ' was not refused');
			} catch (PurchaseRefusedException $refused) {
				$this->assertSame($reason, $refused->getMessage());
				$this->assertSame(409, $refused->getStatus());
			}
		}
	}//end testImpossiblePurchasesAreRefused()

	/**
	 * An unknown character or item answers 404, and an object of the wrong type is unknown.
	 */
	public function testUnknownObjectsAnswer404(): void {
		$service = $this->service();

		foreach ([[fn () => $service->character(characterId: self::ROPE), 'Character not found'], [fn () => $service->buy(character: $this->objects[self::MIRELA]['data'], itemId: self::MIRELA), 'Item not found']] as [$call, $reason]) {
			try {
				$call();
				$this->fail('No refusal for ' . $reason);
			} catch (PurchaseRefusedException $refused) {
				$this->assertSame($reason, $refused->getMessage());
				$this->assertSame(404, $refused->getStatus());
			}
		}
	}//end testUnknownObjectsAnswer404()

	/**
	 * A price is whole, non-negative coins with at least one above zero; anything else is no price.
	 */
	public function testPriceReading(): void {
		$service = $this->service();

		$this->assertSame(['gold' => 1, 'silver' => 0, 'copper' => 7], $service->price(item: ['price' => ['gold' => 1, 'copper' => 7]]));
		$this->assertNull($service->price(item: []));
		$this->assertNull($service->price(item: ['price' => 'cheap']));
		$this->assertNull($service->price(item: ['price' => ['gold' => 0]]));
		$this->assertNull($service->price(item: ['price' => ['silver' => -1, 'gold' => 2]]));
		$this->assertNull($service->price(item: ['price' => ['silver' => '2']]));
	}//end testPriceReading()
}//end class
