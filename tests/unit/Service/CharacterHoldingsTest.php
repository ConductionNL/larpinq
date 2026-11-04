<?php

/**
 * Unit tests for CharacterHoldings.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\CharacterHoldings;
use PHPUnit\Framework\TestCase;

/**
 * Tests reading the holdings per kind.
 *
 * @covers \OCA\Larpinq\Service\CharacterHoldings
 */
class CharacterHoldingsTest extends TestCase {

	/**
	 * Reading for one kind leaves the holdings it came from unchanged.
	 *
	 * @return void
	 */
	public function testForKindReturnsACopy(): void {
		$holdings = new CharacterHoldings(
			held: ['item' => ['crown' => ['queen' => 'Queen']], 'condition' => ['curse' => ['tomas' => 'Tomas']]],
			names: ['queen' => 'Queen', 'tomas' => 'Tomas']
		);

		$items = $holdings->forKind(kind: 'item');
		$conditions = $holdings->forKind(kind: 'condition');

		self::assertSame(expected: '', actual: $holdings->kind());
		self::assertSame(expected: ['Queen', 'ghost'], actual: $items->holderNames(objectId: 'crown', objectSide: ['ghost']));
		self::assertSame(expected: ['Queen', 'Tomas'], actual: $conditions->holderNames(objectId: 'curse', objectSide: ['queen']));
	}//end testForKindReturnsACopy()
}//end class
