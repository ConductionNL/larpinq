<?php

/**
 * RegisterCallOptions: the register and schema of each OpenRegister call.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/object-service/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\RegisterCallOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Each built option names the register and schema it was given.
 */
#[CoversClass(RegisterCallOptions::class)]
class RegisterCallOptionsTest extends TestCase {

	private const UUID = '11111111-2222-3333-4444-555555555555';

	/**
	 * Find, save and delete carry the register, schema and uuid.
	 *
	 * @return void
	 */
	public function testEachOptionNamesItsTarget(): void {
		$options = new RegisterCallOptions();

		$find = $options->find(target: ['5', '7']);
		$this->assertSame(expected: '5', actual: (string)$find->register);
		$this->assertSame(expected: '7', actual: (string)$find->schema);

		$save = $options->save(target: ['5', '7'], uuid: self::UUID);
		$this->assertSame(expected: '5', actual: (string)$save->target->register);
		$this->assertSame(expected: '7', actual: (string)$save->target->schema);
		$this->assertSame(expected: self::UUID, actual: $save->uuid);
		$this->assertNull(actual: $options->save(target: ['5', '7'], uuid: null)->uuid);

		$deletion = $options->deletion(uuid: self::UUID, target: ['5', '7']);
		$this->assertSame(expected: self::UUID, actual: $deletion->uuid);
		$this->assertSame(expected: '5', actual: (string)$deletion->target->register);
		$this->assertSame(expected: '7', actual: (string)$deletion->target->schema);
	}//end testEachOptionNamesItsTarget()
}//end class
