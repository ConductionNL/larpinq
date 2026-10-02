<?php

/**
 * Larpinq Payment Edge Cases Test
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
 * @spec openspec/changes/registration-payments-through-shillinq/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use DateTimeImmutable;
use OCA\Larpinq\Service\PaymentFollowUp;
use OCA\Larpinq\Service\PaymentLeaf;
use OCA\Larpinq\Service\PaymentRequestBuilder;
use OCA\Larpinq\Service\PaymentRequestRefusedException;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * The paths a payment takes when something is missing or fails: no shillinq,
 * shillinq down, an unreadable event or player, no pay-by date, no code, a
 * registration created already accepted.
 */
class PaymentEdgeCasesTest extends TestCase {

	private const REG = 'e0000000-0000-4000-8000-000000000001';

	private PaymentWorld $world;

	protected function setUp(): void {
		$this->world = new PaymentWorld($this);
	}//end setUp()

	/**
	 * A registration created already accepted by a game master asks for its payment.
	 *
	 * @return void
	 */
	public function testACreatedAcceptedRegistrationAsksForPayment(): void {
		$lines = [['kind' => 'ticket', 'amount' => 8500, 'currency' => 'EUR']];
		$created = $this->world->fetcher()->saveObjectWithAppAuthority('registration', ['event' => PaymentWorld::EVENT, 'player' => PaymentWorld::ANNA, 'playerUid' => 'anna', 'lines' => $lines]);

		$this->assertSame('accepted', $created['status']);
		$this->assertSame('open', $this->world->registration(id: (string)$created['id'])['paymentState']);
	}//end testACreatedAcceptedRegistrationAsksForPayment()

	/**
	 * The daily pass: no pay-by date, no request, an unreadable date, a captured
	 * request whose link the registration lacks, and a write that fails.
	 *
	 * @return void
	 */
	public function testTheDailyPassOnIncompleteRegistrations(): void {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::ANNA, status: 'accepted', more: ['paymentState' => 'open']);
		$this->world->seedRegistration(id: 'e0000000-0000-4000-8000-000000000002', player: PaymentWorld::SANNE, status: 'accepted', more: ['paymentState' => 'open', 'payBy' => 'not a date']);
		$this->assertSame(['paid' => 0, 'reminded' => 0, 'expired' => 0, 'none' => 2], $this->world->followUp()->daily(now: $this->world->now));

		$this->world->update(id: 'e0000000-0000-4000-8000-000000000003', data: []);
		$this->world->leaf->requests['f1'] = ['id' => 'f1', 'subject' => ['register' => '3', 'schema' => 'registration', 'id' => self::REG], 'state' => 'captured', 'amount' => 120, 'currency' => 'EUR', 'requestType' => 'other', 'description' => '', 'paymentLink' => 'https://pay.example.org/r/f1'];
		$this->world->store->objects['registration'][self::REG]['paymentRequestId'] = 'f1';
		$this->world->followUp()->daily(now: $this->world->now);
		$this->assertSame('paid', $this->world->registration(id: self::REG)['paymentState']);
		$this->assertSame('https://pay.example.org/r/f1', $this->world->registration(id: self::REG)['paymentLink']);

		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willReturn([['id' => self::REG, 'paymentState' => 'open', 'payBy' => '2026-10-16T00:00:00+00:00']]);
		$fetcher->method('saveObjectWithAppAuthority')->willThrowException(new RuntimeException('database gone'));
		$counts = (new PaymentFollowUp($fetcher, $this->world->paymentLeaf(), new NullLogger()))->daily(now: new DateTimeImmutable('2026-10-15T10:00:00+00:00'));
		$this->assertSame(0, array_sum($counts), 'a failed write is logged and the run goes on');
	}//end testTheDailyPassOnIncompleteRegistrations()

	/**
	 * Without shillinq, or with its registry unreadable, there is no leaf; a
	 * failing list reads as no requests.
	 *
	 * @return void
	 */
	public function testTheLeafWithoutShillinq(): void {
		$this->world->leaf->failList = true;
		$this->assertSame([], $this->world->paymentLeaf()->requests(registrationId: self::REG));
		$this->assertFalse($this->world->paymentLeaf()->isRegistrationSubject(subject: null));

		$this->world->shillinq = false;
		try {
			$this->world->paymentLeaf()->create(registrationId: self::REG, payload: []);
			$this->fail('without shillinq a request cannot be raised');
		} catch (PaymentRequestRefusedException $e) {
			$this->assertSame(409, $e->getStatus());
		}

		$absent = $this->createMock(ContainerInterface::class);
		$absent->method('has')->willReturn(false);
		$this->assertFalse((new PaymentLeaf($absent, $this->world->config(), new NullLogger()))->available());

		$broken = $this->createMock(ContainerInterface::class);
		$broken->method('has')->willReturn(true);
		$broken->method('get')->willThrowException(new RuntimeException('registry failed'));
		$this->assertFalse((new PaymentLeaf($broken, $this->world->config(), new NullLogger()))->available());
	}//end testTheLeafWithoutShillinq()

	/**
	 * The builder's fallbacks: 14 days without a pay-by date, a kept reference,
	 * a code from the event name or LARP, EUR, and a debtor without a player.
	 *
	 * @return void
	 */
	public function testTheBuilderFallsBack(): void {
		$builder = new PaymentRequestBuilder($this->world->fetcher(), $this->world->users(), $this->world->clock(), new NullLogger());

		$this->assertSame('2026-10-29T10:00:00+00:00', $builder->payBy(registration: [], event: []));
		$this->assertSame('WC26-0042', $builder->reference(registration: ['paymentReference' => 'WC26-0042'], event: []));
		$this->assertStringStartsWith('WINT-', $builder->reference(registration: [], event: ['id' => PaymentWorld::EVENT, 'name' => 'Winter Court']));
		$this->assertStringStartsWith('LARP-', $builder->reference(registration: [], event: ['id' => PaymentWorld::EVENT, 'name' => '!']));
		$this->assertSame($this->world->now, $builder->now());

		$payload = $builder->payload(registration: ['lines' => [['amount' => 1000]]], event: ['name' => 'Moot'], reference: 'M-1', payBy: '2026-11-01T00:00:00+00:00');
		$this->assertSame('EUR', $payload['currency']);
		$this->assertSame(['name' => ''], $payload['debtor']);
	}//end testTheBuilderFallsBack()

	/**
	 * A game master's request is refused for an unknown, a pending, or a free
	 * registration; an unreadable event does not stop it.
	 *
	 * @return void
	 */
	public function testAGameMastersRequestIsRefusedWhenItCannotBePaid(): void {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::ANNA, status: 'pending');
		$this->world->seedRegistration(id: 'e0000000-0000-4000-8000-000000000002', player: PaymentWorld::SANNE, status: 'accepted', more: ['lines' => []]);
		$cases = ['e0000000-0000-4000-8000-00000000ffff' => 404, self::REG => 409, 'e0000000-0000-4000-8000-000000000002' => 409];
		foreach ($cases as $id => $status) {
			try {
				$this->world->payments()->request(registrationId: $id, actingUid: 'gm');
				$this->fail("{$id} must be refused");
			} catch (PaymentRequestRefusedException $e) {
				$this->assertSame($status, $e->getStatus(), $id);
			}
		}

		$this->world->seedRegistration(id: 'e0000000-0000-4000-8000-000000000004', player: PaymentWorld::PIETER, status: 'accepted', more: ['event' => 'a0000000-0000-4000-8000-00000000ffff']);
		$raised = $this->world->payments()->request(registrationId: 'e0000000-0000-4000-8000-000000000004', actingUid: 'gm');
		$this->assertSame('open', $raised['paymentState'], 'the request goes out with the registration\'s own date or 14 days');
	}//end testAGameMastersRequestIsRefusedWhenItCannotBePaid()
}//end class
