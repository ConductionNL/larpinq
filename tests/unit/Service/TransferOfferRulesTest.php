<?php

/**
 * The rules of handing a registration to another player, one by one: only the
 * player offers, only an accepted registration before the cancel-by date, only
 * to another player's account; only the recipient accepts, within seven days;
 * the player or a game master withdraws; open offers lapse.
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
use OCA\Larpinq\Service\CancellationPolicy;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationChangeRefusedException;
use OCA\Larpinq\Service\RegistrationReads;
use OCA\Larpinq\Service\TransferOffers;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(TransferOffers::class)]
#[UsesClass(RegistrationReads::class)]
#[UsesClass(RegistrationChangeRefusedException::class)]
class TransferOfferRulesTest extends TestCase {

	private const NOW = '2026-11-17T10:00:00+00:00';

	/** @var array<string,mixed> The stored registration. */
	private array $registration = ['id' => 'r-1', 'event' => 'e-1', 'player' => 'p-anna', 'playerUid' => 'anna', 'status' => 'accepted'];

	/** @var bool Whether the event is still before its cancel-by date. */
	private bool $open = true;

	/** @var list<array{string,array<string,mixed>,?string}> The writes. */
	private array $saved = [];

	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;

	/** @var RegisterObjectFetcher&MockObject */
	private RegisterObjectFetcher $fetcher;

	protected function setUp(): void {
		$this->logger  = $this->createMock(LoggerInterface::class);
		$this->fetcher = $this->createMock(RegisterObjectFetcher::class);
		$this->fetcher->method('getObject')->willReturnCallback(
			fn (string $type, string $id): array => match ([$type, $id]) {
				['registration', 'r-1'] => $this->registration,
				['event', 'e-1'] => ['id' => 'e-1', 'name' => 'Winter feast'],
				default => throw new RuntimeException('not found'),
			}
		);
		$this->fetcher->method('saveObjectWithAppAuthority')->willReturnCallback(
			function (string $type, array $data, ?string $uuid = null): array {
				$this->saved[] = [$type, $data, $uuid];
				return $data + ['id' => $uuid];
			}
		);
	}

	public function testThePlayerOffersTheirRegistrationToAnotherPlayer(): void {
		$this->players(uid: 'bram', rows: [['id' => 'p-bram', 'userUid' => 'bram']]);

		$saved = $this->offers()->offer(registrationId: 'r-1', actingUid: 'anna', recipientUid: ' bram ');

		self::assertSame(
			[['registration', ['transferTo' => 'p-bram', 'transferToUid' => 'bram', 'transferStatus' => 'offered', 'transferOfferedAt' => self::NOW], 'r-1']],
			$this->saved
		);
		self::assertSame('offered', $saved['transferStatus']);
	}

	public function testSomeoneElseCannotOfferTheRegistration(): void {
		$this->assertRefused(
			fn () => $this->offers()->offer(registrationId: 'r-1', actingUid: 'mallory', recipientUid: 'bram'),
			403,
			'Only the player can offer this registration to someone else.'
		);
	}

	public function testOnlyAnAcceptedRegistrationBeforeTheCancelByDateIsOffered(): void {
		$this->registration['status'] = 'waitlisted';
		$this->assertRefused(
			fn () => $this->offers()->offer(registrationId: 'r-1', actingUid: 'anna', recipientUid: 'bram'),
			409,
			'Only an accepted registration can be handed over, before the cancel-by date.'
		);

		$this->registration['status'] = 'accepted';
		$this->open = false;
		$this->assertRefused(
			fn () => $this->offers()->offer(registrationId: 'r-1', actingUid: 'anna', recipientUid: 'bram'),
			409,
			'Only an accepted registration can be handed over, before the cancel-by date.'
		);
	}

	public function testThePlayerCannotOfferToThemselvesOrToNobody(): void {
		$this->fetcher->expects(self::never())->method('getObjectsWithAppAuthority');
		foreach (['anna', '  '] as $recipient) {
			$this->assertRefused(
				fn () => $this->offers()->offer(registrationId: 'r-1', actingUid: 'anna', recipientUid: $recipient),
				404,
				'There is no other player with that account.'
			);
		}
	}

	public function testAnAccountWithoutAPlayerCannotReceiveTheRegistration(): void {
		$this->players(uid: 'ghost', rows: []);

		$this->assertRefused(
			fn () => $this->offers()->offer(registrationId: 'r-1', actingUid: 'anna', recipientUid: 'ghost'),
			404,
			'There is no other player with that account.'
		);
	}

	public function testAnUnknownRegistrationIsNotFound(): void {
		$this->assertRefused(
			fn () => $this->offers()->offer(registrationId: 'r-9', actingUid: 'anna', recipientUid: 'bram'),
			404,
			'Registration not found.'
		);
	}

	public function testTheRecipientAcceptsAndTheRegistrationBecomesTheirs(): void {
		$this->offered(at: '2026-11-15T10:00:00+00:00');

		$this->offers()->accept(registrationId: 'r-1', actingUid: 'bram');

		self::assertSame(
			[
				'player'          => 'p-bram',
				'playerUid'       => 'bram',
				'character'       => '',
				'bookedByUid'     => '',
				'transferredFrom' => 'p-anna',
				'transferredAt'   => self::NOW,
				'transferStatus'  => 'accepted',
				'transferToUid'   => '',
			],
			$this->saved[0][1]
		);
		self::assertSame('r-1', $this->saved[0][2]);
	}

	public function testOnlyAnOpenOfferCanBeAccepted(): void {
		$this->registration['transferStatus'] = 'withdrawn';

		$this->assertRefused(
			fn () => $this->offers()->accept(registrationId: 'r-1', actingUid: 'bram'),
			409,
			'There is no open offer on this registration.'
		);
	}

	public function testOnlyTheRecipientAccepts(): void {
		$this->offered(at: '2026-11-15T10:00:00+00:00');
		foreach (['mallory', 'anna', ''] as $uid) {
			$this->assertRefused(
				fn () => $this->offers()->accept(registrationId: 'r-1', actingUid: $uid),
				403,
				'This registration is offered to someone else.'
			);
		}
	}

	public function testAnOfferOlderThanSevenDaysHasLapsed(): void {
		$this->offered(at: '2026-11-10T09:59:59+00:00');

		$this->assertRefused(fn () => $this->offers()->accept(registrationId: 'r-1', actingUid: 'bram'), 409, 'This offer has lapsed.');
	}

	public function testAnOfferOfExactlySevenDaysStillStands(): void {
		$this->offered(at: '2026-11-10T10:00:00+00:00');

		$this->offers()->accept(registrationId: 'r-1', actingUid: 'bram');

		self::assertSame('accepted', $this->saved[0][1]['transferStatus']);
	}

	public function testAnOfferPastTheCancelByDateOrWithoutAReadableTimeHasLapsed(): void {
		$this->offered(at: '2026-11-16T10:00:00+00:00');
		$this->open = false;
		$this->assertRefused(fn () => $this->offers()->accept(registrationId: 'r-1', actingUid: 'bram'), 409, 'This offer has lapsed.');

		$this->open = true;
		$this->registration['transferOfferedAt'] = 'not a date';
		$this->assertRefused(fn () => $this->offers()->accept(registrationId: 'r-1', actingUid: 'bram'), 409, 'This offer has lapsed.');
	}

	public function testThePlayerOrAGameMasterWithdrawsTheOffer(): void {
		$this->offered(at: '2026-11-15T10:00:00+00:00');

		$this->offers()->withdraw(registrationId: 'r-1', actingUid: 'anna');
		$this->offers()->withdraw(registrationId: 'r-1', actingUid: 'joris');

		self::assertSame(['transferStatus' => 'withdrawn', 'transferToUid' => ''], $this->saved[0][1]);
		self::assertSame(['r-1', 'r-1'], array_column($this->saved, 2));
	}

	public function testTheRecipientCannotWithdrawTheOffer(): void {
		$this->offered(at: '2026-11-15T10:00:00+00:00');

		$this->assertRefused(
			fn () => $this->offers()->withdraw(registrationId: 'r-1', actingUid: 'bram'),
			403,
			'Only the player or a game master can withdraw this offer.'
		);
	}

	public function testWithoutAnOpenOfferThereIsNothingToWithdraw(): void {
		$this->assertRefused(
			fn () => $this->offers()->withdraw(registrationId: 'r-1', actingUid: 'anna'),
			409,
			'There is no open offer on this registration.'
		);
	}

	public function testOpenOffersPastSevenDaysLapse(): void {
		$this->fetcher->method('getObjectsWithAppAuthority')
			->with('registration', ['transferStatus' => 'offered'], 500)
			->willReturn([
				['id' => 'r-old', 'event' => 'e-1', 'transferOfferedAt' => '2026-11-01T10:00:00+00:00'],
				['id' => 'r-new', 'event' => 'e-1', 'transferOfferedAt' => '2026-11-16T10:00:00+00:00'],
				['id' => 'r-bad', 'event' => 'e-1', 'transferOfferedAt' => 'soon'],
			]);

		$lapsed = $this->offers()->lapse(now: new DateTimeImmutable(self::NOW));

		self::assertSame(2, $lapsed);
		self::assertSame(['r-old', 'r-bad'], array_column($this->saved, 2));
		self::assertSame(['transferStatus' => 'lapsed', 'transferToUid' => ''], $this->saved[0][1]);
	}

	public function testAnOfferThatFailsToLapseIsLoggedAndNotCounted(): void {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObject')->willReturn(['id' => 'e-1']);
		$fetcher->method('getObjectsWithAppAuthority')->willReturn([
			['id' => 'r-1', 'event' => 'e-1', 'transferOfferedAt' => '2026-11-01T10:00:00+00:00'],
		]);
		$fetcher->method('saveObjectWithAppAuthority')->willThrowException(new RuntimeException('locked'));
		$this->fetcher = $fetcher;
		$this->logger->expects(self::once())
			->method('error')
			->with('Larpinq: the transfer offer on registration {id} did not lapse.', self::callback(static fn (array $context): bool => $context['id'] === 'r-1'));

		self::assertSame(0, $this->offers()->lapse(now: new DateTimeImmutable(self::NOW)));
	}

	/**
	 * Assert that a call is refused with a status and message, and wrote nothing.
	 *
	 * @param callable $call    The call.
	 * @param int      $status  The expected HTTP status.
	 * @param string   $message The expected message.
	 *
	 * @return void
	 */
	private function assertRefused(callable $call, int $status, string $message): void {
		$before = count($this->saved);
		try {
			$call();
			self::fail('The call was not refused: ' . $message);
		} catch (RegistrationChangeRefusedException $e) {
			self::assertSame([$status, $message], [$e->getStatus(), $e->getMessage()]);
		}

		self::assertCount($before, $this->saved, 'A refused call wrote nothing');
	}

	/**
	 * The registration carries an open offer to bram, made at a moment.
	 *
	 * @param string $at When the offer was made.
	 *
	 * @return void
	 */
	private function offered(string $at): void {
		$this->registration += ['transferStatus' => 'offered', 'transferTo' => 'p-bram', 'transferToUid' => 'bram', 'transferOfferedAt' => $at];
	}

	/**
	 * The players the fetcher finds for an account.
	 *
	 * @param string                     $uid  The account.
	 * @param list<array<string,string>> $rows The players.
	 *
	 * @return void
	 */
	private function players(string $uid, array $rows): void {
		$this->fetcher->expects(self::once())
			->method('getObjectsWithAppAuthority')
			->with('player', ['userUid' => $uid], 1)
			->willReturn($rows);
	}

	/**
	 * The service over the mocks; the policy answers from the test's state.
	 *
	 * @return TransferOffers
	 */
	private function offers(): TransferOffers {
		$policy = $this->createMock(CancellationPolicy::class);
		$policy->method('isParticipant')->willReturnCallback(static fn (array $registration, string $uid): bool => $uid !== '' && $uid === ($registration['playerUid'] ?? null));
		$policy->method('isGameMaster')->willReturnCallback(static fn (string $uid): bool => $uid === 'joris');
		$policy->method('isOpen')->willReturnCallback(fn (): bool => $this->open);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable(self::NOW));

		return new TransferOffers(fetcher: $this->fetcher, policy: $policy, time: $time, logger: $this->logger);
	}
}
