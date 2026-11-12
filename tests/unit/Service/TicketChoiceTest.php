<?php

/**
 * Unit tests for TicketChoice.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\TicketChoice;
use PHPUnit\Framework\TestCase;

/**
 * Tests the choice value and its stored lines.
 *
 * @covers \OCA\Larpinq\Service\TicketChoice
 */
class TicketChoiceTest extends TestCase {

	/**
	 * Adding the code leaves the choice it came from without one.
	 *
	 * @return void
	 */
	public function testWithCodeReturnsACopy(): void {
		$choice = new TicketChoice(registration: ['event' => 'e1'], stored: []);
		$coded = $choice->withCode(codeRow: ['id' => 'code-1']);

		self::assertNull(actual: $choice->codeRow());
		self::assertSame(expected: ['id' => 'code-1'], actual: $coded->codeRow());
		self::assertSame(expected: 'e1', actual: $coded->eventId());
	}//end testWithCodeReturnsACopy()

	/**
	 * A stored line is kept only for the same kind and reference.
	 *
	 * @return void
	 */
	public function testKeptLineMatchesKindAndReference(): void {
		$line = ['kind' => 'option', 'ref' => 'o1', 'amount' => 5];
		$choice = new TicketChoice(registration: [], stored: ['lines' => ['junk', $line]]);

		self::assertSame(expected: $line, actual: $choice->keptLine(kind: 'option', ref: 'o1'));
		self::assertNull(actual: $choice->keptLine(kind: 'ticket', ref: 'o1'));
		self::assertNull(actual: $choice->keptLine(kind: 'option', ref: 'o2'));
	}//end testKeptLineMatchesKindAndReference()
}//end class
