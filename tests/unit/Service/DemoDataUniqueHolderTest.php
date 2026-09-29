<?php

/**
 * DemoDataUniqueHolderTest.
 *
 * The demo dataset must show the unique-holder rule at work and must never
 * break it: a unique item or condition held by two characters would make the
 * demo import refuse itself once UniqueHolderListener is registered.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * Checks the shipped demo descriptor against the unique-holder rule.
 */
class DemoDataUniqueHolderTest extends TestCase {

	/**
	 * Holder counts per unique item and condition in the demo descriptor.
	 *
	 * @return array<string,int> Slug => number of distinct holders.
	 */
	private function uniqueHolderCounts(): array {
		$descriptor = json_decode(
			(string) file_get_contents(dirname(__DIR__, 3) . '/lib/Settings/larpinq_mock_register.json'),
			true,
			512,
			JSON_THROW_ON_ERROR
		);
		$counts = [];
		foreach ($descriptor['components']['objects'] as $object) {
			$schema = $object['@self']['schema'] ?? '';
			if (in_array($schema, ['larping_item', 'condition'], true) === false || ($object['unique'] ?? false) !== true) {
				continue;
			}

			$counts[$object['@self']['slug']] = count(array_unique($object['characters'] ?? []));
		}

		return $counts;
	}//end uniqueHolderCounts()

	/**
	 * The demo carries at least one unique item with exactly one holder.
	 *
	 * @return void
	 */
	public function testTheDemoSeedsAUniqueItemWithOneHolder(): void {
		$counts = $this->uniqueHolderCounts();
		$itemsWithOneHolder = array_filter(
			$counts,
			static fn (int $n, string $slug): bool => $n === 1 && str_starts_with($slug, 'larping-item-'),
			ARRAY_FILTER_USE_BOTH
		);

		$this->assertNotEmpty($itemsWithOneHolder);
	}//end testTheDemoSeedsAUniqueItemWithOneHolder()

	/**
	 * No unique item or condition in the demo has two holders.
	 *
	 * @return void
	 */
	public function testNoUniqueObjectInTheDemoHasTwoHolders(): void {
		$shared = array_filter($this->uniqueHolderCounts(), static fn (int $n): bool => $n > 1);

		$this->assertSame([], $shared);
	}//end testNoUniqueObjectInTheDemoHasTwoHolders()
}//end class
