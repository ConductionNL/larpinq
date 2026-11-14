<?php

/**
 * Tests for the care copy on acceptance in RegistrationService::afterWrite()
 * (players-care-details-and-erasure REQ-PCE-002).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/players-care-details-and-erasure/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\EventCareRecords;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationService;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The register and the copies are doubles; only the status change matters.
 *
 * @covers \OCA\Larpinq\Service\RegistrationService
 */
class RegistrationCareCopyTest extends TestCase {

	/**
	 * Run afterWrite() and say how many copies were asked for.
	 *
	 * @param array<string, mixed> $new The stored registration.
	 * @param array<string, mixed>|null $old The registration before.
	 *
	 * @return array<int, array<string, mixed>> The registrations a copy was asked for.
	 */
	private function copiesAfter(array $new, ?array $old): array {
		$asked = [];
		$care = $this->createMock(EventCareRecords::class);
		$care->method('copyForRegistration')->willReturnCallback(
			function (array $registration) use (&$asked): bool {
				$asked[] = $registration;
				return true;
			}
		);
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willReturn([]);

		$service = new RegistrationService(
			fetcher: $fetcher,
			locking: $this->createMock(ILockingProvider::class),
			logger: new NullLogger(),
			lockAttempts: 1,
			lockWaitMicros: 0,
			care: $care
		);
		$service->afterWrite(new: $new, old: $old);

		return $asked;
	}//end copiesAfter()

	/**
	 * A registration that becomes accepted, by a decision or on creation, gets its copy.
	 *
	 * @return void
	 */
	public function testAnAcceptedRegistrationGetsItsCopy(): void {
		$accepted = ['id' => 'r-1', 'event' => 'e-1', 'player' => 'p-anna', 'status' => 'accepted'];

		$this->assertSame([$accepted], $this->copiesAfter(new: $accepted, old: ['id' => 'r-1', 'event' => 'e-1', 'status' => 'pending']));
		$this->assertSame([$accepted], $this->copiesAfter(new: $accepted, old: null));
	}//end testAnAcceptedRegistrationGetsItsCopy()

	/**
	 * Staying accepted, or any other status, asks for no copy.
	 *
	 * @return void
	 */
	public function testOtherWritesAskForNoCopy(): void {
		$this->assertSame([], $this->copiesAfter(new: ['id' => 'r-1', 'event' => 'e-1', 'status' => 'accepted', 'note' => 'x'], old: ['id' => 'r-1', 'event' => 'e-1', 'status' => 'accepted']));
		$this->assertSame([], $this->copiesAfter(new: ['id' => 'r-1', 'event' => 'e-1', 'status' => 'waitlisted'], old: ['id' => 'r-1', 'event' => 'e-1', 'status' => 'pending']));
		$this->assertSame([], $this->copiesAfter(new: ['id' => 'r-1', 'event' => 'e-1', 'status' => 'pending'], old: null));
	}//end testOtherWritesAskForNoCopy()
}//end class
