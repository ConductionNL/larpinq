<?php

/**
 * Larpinq Event Audience Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use OCA\Larpinq\Service\EventAudience;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The audience of an event message: its stored shape and which registrations it selects.
 */
#[CoversClass(EventAudience::class)]
class EventAudienceTest extends TestCase {

	/**
	 * Unknown statuses and roles are dropped; without a status it is accepted.
	 *
	 * @return void
	 */
	public function testTheAudienceIsNormalised(): void {
		$audience = new EventAudience();

		$this->assertSame(
			['statuses' => ['accepted'], 'checkedIn' => true, 'paymentOpen' => false, 'ticketRoles' => ['crew', 'npc']],
			$audience->normalise(raw: ['statuses' => ['declined'], 'checkedIn' => '1', 'paymentOpen' => 'no', 'ticketRoles' => ['npc', 'crew', 'boss']])
		);
		$this->assertSame(
			['statuses' => ['accepted'], 'checkedIn' => false, 'paymentOpen' => true, 'ticketRoles' => []],
			$audience->normalise(raw: ['statuses' => 'waitlisted', 'paymentOpen' => true]),
			'a status that is not a list falls back to accepted'
		);
		$this->assertSame(['accepted', 'pending'], $audience->normalise(raw: ['statuses' => ['pending', 'accepted']])['statuses']);
	}//end testTheAudienceIsNormalised()

	/**
	 * Each rule rejects on its own; all together they select.
	 *
	 * @return void
	 */
	public function testARegistrationMatchesEveryRule(): void {
		$audience = new EventAudience();
		$all = ['statuses' => ['accepted'], 'checkedIn' => true, 'paymentOpen' => true, 'ticketRoles' => ['crew']];
		$roles = ['t1' => 'crew', 't2' => 'player'];
		$checkedIn = ['c1' => true];
		$registration = ['status' => 'accepted', 'paymentState' => 'open', 'ticketType' => 't1', 'character' => 'c1'];

		$this->assertTrue($audience->matches(registration: $registration, audience: $all, roles: $roles, checkedIn: $checkedIn));
		$this->assertFalse($audience->matches(registration: array_merge($registration, ['status' => 'waitlisted']), audience: $all, roles: $roles, checkedIn: $checkedIn));
		$this->assertFalse($audience->matches(registration: array_merge($registration, ['paymentState' => 'paid']), audience: $all, roles: $roles, checkedIn: $checkedIn));
		$this->assertFalse($audience->matches(registration: array_merge($registration, ['ticketType' => 't2']), audience: $all, roles: $roles, checkedIn: $checkedIn));
		$this->assertFalse($audience->matches(registration: array_merge($registration, ['character' => 'c9']), audience: $all, roles: $roles, checkedIn: $checkedIn));

		$plain = ['statuses' => ['accepted'], 'checkedIn' => false, 'paymentOpen' => false, 'ticketRoles' => []];
		$this->assertTrue($audience->matches(registration: ['status' => 'accepted'], audience: $plain, roles: [], checkedIn: []));
	}//end testARegistrationMatchesEveryRule()
}//end class
