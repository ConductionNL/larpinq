<?php

/**
 * Unit tests for IdListNormaliser.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/skill-requirement-enforcement/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\IdListNormaliser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every relation shape becomes a flat list of string ids.
 *
 * @covers \OCA\Larpinq\Service\IdListNormaliser
 */
class IdListNormaliserTest extends TestCase {

	/**
	 * Raw relation values and the list each becomes.
	 *
	 * @return array<string, array{0: mixed, 1: list<string>}>
	 */
	public static function values(): array {
		return [
			'null' => [null, []],
			'empty string' => ['', []],
			'one string' => ['abc', ['abc']],
			'one int' => [7, ['7']],
			'zero is an id, not empty' => [0, ['0']],
			'false becomes an empty id' => [false, ['']],
			'empty list' => [[], []],
			'list of strings' => [['a', 'b'], ['a', 'b']],
			'list of ints' => [[1, 2], ['1', '2']],
			'nulls and empty strings dropped' => [['a', null, '', 'b'], ['a', 'b']],
			'objects with an id' => [[['id' => 'x'], ['id' => 5]], ['x', '5']],
			'objects without an id dropped' => [[['uuid' => 'x'], ['id' => 'y'], []], ['y']],
			'an object with a null id is dropped' => [[['id' => null]], []],
			'mixed shapes keep their order' => [['a', ['id' => 'b'], 'c'], ['a', 'b', 'c']],
			'keys are not kept' => [['k1' => 'a', 'k2' => 'b'], ['a', 'b']],
			'duplicates are kept' => [['a', 'a'], ['a', 'a']],
		];
	}

	/**
	 * @param mixed        $value    The raw value.
	 * @param list<string> $expected The list.
	 *
	 * @return void
	 */
	#[DataProvider('values')]
	public function testItNormalisesEachShape(mixed $value, array $expected): void {
		$this->assertSame(expected: $expected, actual: (new IdListNormaliser())->normalise(value: $value));
	}
}
