<?php

/**
 * ReadsRelationIdTest.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Support
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/character-builds/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Support;

use OCA\Larpinq\Support\ReadsRelationId;
use PHPUnit\Framework\TestCase;

/**
 * The UUID of each shape of relation value.
 *
 * @covers \OCA\Larpinq\Support\ReadsRelationId
 */
class ReadsRelationIdTest extends TestCase {

	/**
	 * Relation values and the UUID read from each.
	 *
	 * @return array<string,array{0:mixed,1:string}> The cases.
	 */
	public static function values(): array {
		return [
			'string' => ['c-1', 'c-1'],
			'object with id' => [['id' => 'c-2', 'name' => 'x'], 'c-2'],
			'object with uuid' => [['uuid' => 'c-3'], 'c-3'],
			'id before uuid' => [['id' => 'c-4', 'uuid' => 'c-5'], 'c-4'],
			'object without id' => [['name' => 'x'], ''],
			'numeric id' => [['id' => 7], ''],
			'integer' => [7, ''],
			'null' => [null, ''],
		];
	}//end values()

	/**
	 * Each value reads as its UUID, or '' when it has none.
	 *
	 * @param mixed  $value    The relation value.
	 * @param string $expected The UUID.
	 *
	 * @dataProvider values
	 *
	 * @return void
	 */
	public function testReadsTheUuid(mixed $value, string $expected): void {
		$reader = new class {
			use ReadsRelationId;

			/**
			 * Expose the trait's reader.
			 *
			 * @param mixed $value The relation value.
			 *
			 * @return string The UUID.
			 */
			public function read(mixed $value): string {
				return $this->idOf(value: $value);
			}
		};

		$this->assertSame($expected, $reader->read($value));
	}//end testReadsTheUuid()
}//end class
