<?php

/**
 * The parts of a registration's payment request: the amount in cents, the
 * pay-by moment, the payment reference, the currency, the debtor and the
 * payload handed to the payment app.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Larpinq\Service\PaymentRequestBuilder;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(PaymentRequestBuilder::class)]
class PaymentRequestBuilderTest extends TestCase {

	/** @var RegisterObjectFetcher&MockObject */
	private RegisterObjectFetcher $fetcher;

	/** @var IUserManager&MockObject */
	private IUserManager $users;

	protected function setUp(): void {
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->users   = $this->createMock(IUserManager::class);
	}

	public function testTheAmountAddsUpTheNumericLines(): void {
		$registration = ['lines' => [['amount' => 4500], ['amount' => '1250'], ['amount' => 'free'], ['label' => 'no amount'], 'not a line']];

		self::assertSame(5750, $this->builder()->cents(registration: $registration));
	}

	public function testARegistrationWithoutLinesCostsNothing(): void {
		self::assertSame(0, $this->builder()->cents(registration: []));
		self::assertSame(0, $this->builder()->cents(registration: ['lines' => 'none']));
	}

	public function testTheRegistrationsOwnPayByWins(): void {
		$payBy = $this->builder()->payBy(registration: ['payBy' => '2026-12-01T00:00:00+00:00'], event: ['payBy' => '2026-12-15T00:00:00+00:00']);

		self::assertSame('2026-12-01T00:00:00+00:00', $payBy);
	}

	public function testTheEventsPayByIsTheFallback(): void {
		$payBy = $this->builder()->payBy(registration: ['payBy' => ''], event: ['payBy' => '2026-12-15T00:00:00+00:00']);

		self::assertSame('2026-12-15T00:00:00+00:00', $payBy);
	}

	public function testWithoutAPayByThePaymentIsDueInFourteenDays(): void {
		$payBy = $this->builder(now: '2026-11-17T10:00:00+00:00')->payBy(registration: ['payBy' => 7], event: []);

		self::assertSame('2026-12-01T10:00:00+00:00', $payBy);
	}

	public function testAKeptReferenceIsNotRenumbered(): void {
		$this->fetcher->expects(self::never())->method('getObjectsWithAppAuthority');

		self::assertSame('WINT-0003', $this->builder()->reference(registration: ['paymentReference' => 'WINT-0003'], event: ['id' => 'e-1']));
	}

	public function testTheNextReferenceFollowsTheHighestOfTheEvent(): void {
		$this->fetcher->expects(self::once())
			->method('getObjectsWithAppAuthority')
			->with('registration', ['event' => 'e-1'], 2000)
			->willReturn([
				['paymentReference' => 'FEST-0002'],
				['paymentReference' => 'FEST-0010'],
				['paymentReference' => 'OTHER-0099'],
				['paymentReference' => 'FEST-0007-old'],
				[],
			]);

		self::assertSame('FEST-0011', $this->builder()->reference(registration: [], event: ['id' => 'e-1', 'paymentCode' => 'FEST']));
	}

	public function testTheCodeComesFromTheEventNameWithoutAPaymentCode(): void {
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturn([]);

		self::assertSame('WINT-0001', $this->builder()->reference(registration: [], event: ['id' => 'e-1', 'name' => 'Win-ter feast']));
	}

	public function testAShortOrMissingNameFallsBackToLarp(): void {
		$this->fetcher->method('getObjectsWithAppAuthority')->willReturn([['paymentReference' => 'LARP-0004']]);

		self::assertSame('LARP-0005', $this->builder()->reference(registration: [], event: ['id' => 'e-1', 'name' => 'X!']));
		self::assertSame('LARP-0005', $this->builder()->reference(registration: [], event: ['id' => 'e-1']));
	}

	public function testTheCurrencyIsTheFirstLineThatNamesOne(): void {
		$registration = ['lines' => [['amount' => 100], ['amount' => 100, 'currency' => 'GBP'], ['currency' => 'USD']]];

		self::assertSame('GBP', $this->builder()->currency(registration: $registration));
	}

	public function testTheCurrencyDefaultsToEuro(): void {
		self::assertSame('EUR', $this->builder()->currency(registration: ['lines' => [['amount' => 100, 'currency' => '']]]));
		self::assertSame('EUR', $this->builder()->currency(registration: []));
	}

	public function testTheDebtorIsThePlayerWithTheUsersEmail(): void {
		$this->fetcher->method('getObject')->with('player', 'p-1')->willReturn(['name' => 'Anna de Vries']);
		$this->userWithEmail(uid: 'anna', email: 'anna@example.org');

		self::assertSame(
			['name' => 'Anna de Vries', 'email' => 'anna@example.org'],
			$this->builder()->debtor(registration: ['player' => 'p-1', 'playerUid' => 'anna'])
		);
	}

	public function testAnUnreadablePlayerLeavesTheUserIdAsTheName(): void {
		$this->fetcher->method('getObject')->willThrowException(new RuntimeException('gone'));
		$this->userWithEmail(uid: 'anna', email: '');

		self::assertSame(['name' => 'anna'], $this->builder()->debtor(registration: ['player' => 'p-1', 'playerUid' => 'anna']));
	}

	public function testAPlayerWithoutANameKeepsTheUserId(): void {
		$this->fetcher->method('getObject')->willReturn([]);
		$this->users->method('get')->with('anna')->willReturn(null);

		self::assertSame(['name' => 'anna'], $this->builder()->debtor(registration: ['player' => 'p-1', 'playerUid' => 'anna']));
	}

	public function testWithoutAUserIdNoEmailIsLookedUp(): void {
		$this->fetcher->method('getObject')->willReturn(['name' => 'Guest']);
		$this->users->expects(self::never())->method('get');

		self::assertSame(['name' => 'Guest'], $this->builder()->debtor(registration: ['player' => 'p-9']));
	}

	public function testThePayloadDescribesTheRequest(): void {
		$this->fetcher->method('getObject')->willReturn(['name' => 'Anna de Vries']);
		$this->userWithEmail(uid: 'anna', email: 'anna@example.org');
		$registration = [
			'player'           => 'p-1',
			'playerUid'        => 'anna',
			'invoiceRequested' => true,
			'lines'            => [['amount' => 4550, 'currency' => 'EUR'], ['amount' => 1000]],
		];

		$payload = $this->builder()->payload(registration: $registration, event: ['name' => 'Winter feast'], reference: 'WINT-0001', payBy: '2026-12-01T00:00:00+00:00');

		self::assertSame(
			[
				'subjectType'      => 'registration',
				'requestType'      => 'other',
				'amount'           => 55.5,
				'currency'         => 'EUR',
				'description'      => 'Winter feast, Anna de Vries, WINT-0001',
				'debtor'           => ['name' => 'Anna de Vries', 'email' => 'anna@example.org'],
				'dueAt'            => '2026-12-01T00:00:00+00:00',
				'invoiceRequested' => true,
			],
			$payload
		);
	}

	public function testOnlyATrueInvoiceRequestAsksForAnInvoice(): void {
		$this->fetcher->method('getObject')->willReturn([]);

		$payload = $this->builder()->payload(registration: ['invoiceRequested' => 'yes'], event: [], reference: 'LARP-0001', payBy: '');

		self::assertFalse($payload['invoiceRequested']);
		self::assertSame(0.0, $payload['amount']);
		self::assertSame(', , LARP-0001', $payload['description']);
	}

	public function testNowIsTheTimeFactorysNow(): void {
		self::assertSame('2026-11-17T10:00:00+00:00', $this->builder(now: '2026-11-17T10:00:00+00:00')->now()->format(DATE_ATOM));
	}

	/**
	 * A user whose email the user manager returns.
	 *
	 * @param string $uid   The user id.
	 * @param string $email The email address, empty for none.
	 *
	 * @return void
	 */
	private function userWithEmail(string $uid, string $email): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn($email === '' ? null : $email);
		$this->users->method('get')->with($uid)->willReturn($user);
	}

	/**
	 * The builder over the mocks, at a fixed moment.
	 *
	 * @param string $now The current moment.
	 *
	 * @return PaymentRequestBuilder
	 */
	private function builder(string $now = '2026-11-17T10:00:00+00:00'): PaymentRequestBuilder {
		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable($now));

		return new PaymentRequestBuilder(fetcher: $this->fetcher, users: $this->users, time: $time, logger: $this->createMock(LoggerInterface::class));
	}
}
