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
 * @spec openspec/changes/larpinq-runsheet-scoped-cast-query/tasks.md#5-quality
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

		$find = $options->find(register: '5', schema: '7');
		$this->assertSame('5', (string)$find->register);
		$this->assertSame('7', (string)$find->schema);

		$save = $options->save(register: '5', schema: '7', uuid: self::UUID);
		$this->assertSame('5', (string)$save->target->register);
		$this->assertSame('7', (string)$save->target->schema);
		$this->assertSame(self::UUID, $save->uuid);
		$this->assertNull($options->save(register: '5', schema: '7', uuid: null)->uuid);

		$deletion = $options->deletion(uuid: self::UUID, register: '5', schema: '7');
		$this->assertSame(self::UUID, $deletion->uuid);
		$this->assertSame('5', (string)$deletion->target->register);
		$this->assertSame('7', (string)$deletion->target->schema);
	}//end testEachOptionNamesItsTarget()
}//end class
