<?php

/**
 * RegistrationReads loads a registration (refusing a missing one with 404) and its event (empty when missing).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationChangeRefusedException;
use OCA\Larpinq\Service\RegistrationReads;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\Larpinq\Service\RegistrationReads
 * @uses   \OCA\Larpinq\Service\RegistrationChangeRefusedException
 */
class RegistrationReadsTest extends TestCase {

	/** @var RegisterObjectFetcher&MockObject */
	private RegisterObjectFetcher&MockObject $fetcher;

	/**
	 * Build the fetcher double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->fetcher = $this->createMock(originalClassName: RegisterObjectFetcher::class);
	}

	/**
	 * A found registration is returned as fetched.
	 *
	 * @return void
	 */
	public function testAFoundRegistrationIsReturned(): void {
		$this->fetcher->expects($this->once())->method('getObject')
			->with('registration', 'r-1')
			->willReturn(['id' => 'r-1', 'event' => 'e-1']);

		$registration = RegistrationReads::registration(fetcher: $this->fetcher, registrationId: 'r-1');

		$this->assertSame(expected: ['id' => 'r-1', 'event' => 'e-1'], actual: $registration);
	}

	/**
	 * Any failure to fetch the registration is a 404 refusal that hides the cause.
	 *
	 * @return void
	 */
	public function testAMissingRegistrationIsA404(): void {
		$this->fetcher->method('getObject')->willThrowException(new RuntimeException('db down: secret detail'));

		try {
			RegistrationReads::registration(fetcher: $this->fetcher, registrationId: 'r-1');
			$this->fail(message: 'a missing registration should be refused');
		} catch (RegistrationChangeRefusedException $e) {
			$this->assertSame(expected: 'Registration not found.', actual: $e->getMessage());
			$this->assertSame(expected: 404, actual: $e->getStatus());
		}
	}

	/**
	 * The event is fetched by the registration's event id.
	 *
	 * @return void
	 */
	public function testTheEventIsFetchedByTheRegistrationsEventId(): void {
		$this->fetcher->expects($this->once())->method('getObject')
			->with('event', 'e-7')
			->willReturn(['id' => 'e-7', 'title' => 'Winter']);

		$event = RegistrationReads::event(fetcher: $this->fetcher, registration: ['event' => 'e-7']);

		$this->assertSame(expected: ['id' => 'e-7', 'title' => 'Winter'], actual: $event);
	}

	/**
	 * A registration without an event asks for the empty id; a failing lookup gives no event.
	 *
	 * @return void
	 */
	public function testAMissingEventIsEmpty(): void {
		$this->fetcher->expects($this->once())->method('getObject')
			->with('event', '')
			->willThrowException(new RuntimeException('not found'));

		$this->assertSame(expected: [], actual: RegistrationReads::event(fetcher: $this->fetcher, registration: []));
	}
}
