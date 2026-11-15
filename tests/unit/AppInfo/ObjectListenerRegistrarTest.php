<?php

/**
 * ObjectListenerRegistrar wires each listener to the OpenRegister and Forms events it handles.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\AppInfo
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\AppInfo;

use OCA\Larpinq\AppInfo\ObjectListenerRegistrar;
use OCA\Larpinq\Listener\CharacterStatusListener;
use OCA\Larpinq\Listener\FactionMembershipListener;
use OCA\Larpinq\Listener\FormSubmissionListener;
use OCA\Larpinq\Listener\PaymentRequestListener;
use OCA\Larpinq\Listener\PlayerReviewListener;
use OCA\Larpinq\Listener\PortalProfileListener;
use OCA\Larpinq\Listener\RegistrationListener;
use OCA\Larpinq\Listener\UniqueHolderListener;
use OCA\Larpinq\Listener\XpAwardProvenanceListener;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\TestCase;

/**
 * The OpenRegister and Forms event stubs are on the test autoloader, so every
 * guarded block registers.
 *
 * @covers \OCA\Larpinq\AppInfo\ObjectListenerRegistrar
 */
class ObjectListenerRegistrarTest extends TestCase {

	private const CREATING = 'OCA\OpenRegister\Event\ObjectCreatingEvent';
	private const CREATED = 'OCA\OpenRegister\Event\ObjectCreatedEvent';
	private const UPDATING = 'OCA\OpenRegister\Event\ObjectUpdatingEvent';
	private const UPDATED = 'OCA\OpenRegister\Event\ObjectUpdatedEvent';

	/**
	 * Register and record every (event, listener) pair in order.
	 *
	 * @return list<array{string,string}>
	 */
	private function registrations(): array {
		$pairs = [];
		$context = $this->createMock(originalClassName: IRegistrationContext::class);
		$context->method('registerEventListener')->willReturnCallback(
			function (string $event, string $listener) use (&$pairs): void {
				$pairs[] = [$event, $listener];
			}
		);

		(new ObjectListenerRegistrar())->register(context: $context);

		return $pairs;
	}

	/**
	 * The exact registrations, in order: guards before the registration and payment listeners.
	 *
	 * @return void
	 */
	public function testEveryListenerIsRegisteredInOrder(): void {
		$this->assertSame(
			expected: [
				[self::CREATING, UniqueHolderListener::class],
				[self::CREATING, FactionMembershipListener::class],
				[self::CREATING, CharacterStatusListener::class],
				[self::CREATING, XpAwardProvenanceListener::class],
				[self::CREATING, PortalProfileListener::class],
				[self::CREATED, PortalProfileListener::class],
				[self::UPDATING, UniqueHolderListener::class],
				[self::UPDATING, FactionMembershipListener::class],
				[self::UPDATING, CharacterStatusListener::class],
				[self::UPDATING, XpAwardProvenanceListener::class],
				[self::UPDATING, PlayerReviewListener::class],
				[self::CREATING, RegistrationListener::class],
				[self::UPDATING, RegistrationListener::class],
				[self::CREATED, RegistrationListener::class],
				[self::UPDATED, RegistrationListener::class],
				[self::CREATED, PaymentRequestListener::class],
				[self::UPDATED, PaymentRequestListener::class],
				[FormSubmissionListener::SUBMITTED_EVENT, FormSubmissionListener::class],
			],
			actual: $this->registrations()
		);
	}

	/**
	 * The player review listener only guards updates: a new player is not yet reviewed.
	 *
	 * @return void
	 */
	public function testPlayerReviewOnlyListensToUpdates(): void {
		$events = array_column(
			array_filter($this->registrations(), static fn (array $p): bool => $p[1] === PlayerReviewListener::class),
			0
		);

		$this->assertSame(expected: [self::UPDATING], actual: array_values($events));
	}

	/**
	 * Forms submissions go to the form submission listener only.
	 *
	 * @return void
	 */
	public function testFormsSubmissionsHaveOneListener(): void {
		$listeners = array_column(
			array_filter($this->registrations(), static fn (array $p): bool => $p[0] === 'OCA\Forms\Events\FormSubmittedEvent'),
			1
		);

		$this->assertSame(expected: [FormSubmissionListener::class], actual: array_values($listeners));
	}
}
