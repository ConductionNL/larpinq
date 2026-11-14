<?php

/**
 * WriteStamp builds the who/when stamp of a write and reads back the stored one.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Listener;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\Larpinq\Listener\WriteStamp;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Larpinq\Listener\WriteStamp
 */
class WriteStampTest extends TestCase {

	/**
	 * now() puts the uid under the first field and an ATOM time of now under the second.
	 *
	 * @return void
	 */
	public function testNowStampsTheUidAndTheCurrentTime(): void {
		$before = time();
		$stamp = WriteStamp::now(fields: ['reviewedBy', 'reviewedAt'], uid: 'joris');
		$after = time();

		$this->assertSame(expected: ['reviewedBy', 'reviewedAt'], actual: array_keys($stamp));
		$this->assertSame(expected: 'joris', actual: $stamp['reviewedBy']);
		$at = DateTimeImmutable::createFromFormat(format: DateTimeInterface::ATOM, datetime: $stamp['reviewedAt']);
		$this->assertNotFalse(condition: $at);
		$this->assertGreaterThanOrEqual(expected: $before, actual: $at->getTimestamp());
		$this->assertLessThanOrEqual(expected: $after, actual: $at->getTimestamp());
	}

	/**
	 * stored() keeps only the stamp fields that carry a value.
	 *
	 * @return void
	 */
	public function testStoredKeepsOnlyFilledStampFields(): void {
		$object = ['name' => 'Aria', 'reviewedBy' => 'joris', 'reviewedAt' => '', 'other' => 'x'];

		$this->assertSame(expected: ['reviewedBy' => 'joris'], actual: WriteStamp::stored(object: $object, fields: ['reviewedBy', 'reviewedAt']));
	}

	/**
	 * Null and empty values are dropped, falsy non-empty values are kept.
	 *
	 * @return void
	 */
	public function testStoredDropsNullAndEmptyButKeepsZero(): void {
		$object = ['a' => null, 'b' => '', 'c' => '0', 'd' => 0, 'e' => false];

		$this->assertSame(expected: ['c' => '0', 'd' => 0, 'e' => false], actual: WriteStamp::stored(object: $object, fields: ['a', 'b', 'c', 'd', 'e']));
	}

	/**
	 * An object without any stamp fields has no stored stamp.
	 *
	 * @return void
	 */
	public function testStoredIsEmptyWithoutStampFields(): void {
		$this->assertSame(expected: [], actual: WriteStamp::stored(object: ['name' => 'Aria'], fields: ['reviewedBy', 'reviewedAt']));
	}
}
