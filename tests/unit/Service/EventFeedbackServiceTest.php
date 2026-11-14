<?php

/**
 * Tests for EventFeedbackService: post-event feedback invitations and reminders.
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
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Larpinq\Service\EventFeedbackService;
use OCA\Larpinq\Service\FeedbackAnswers;
use OCA\Larpinq\Service\FeedbackDelivery;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Invited once, reminded once, never someone marked no-show, on a fixed clock.
 *
 * @covers \OCA\Larpinq\Service\EventFeedbackService
 */
class EventFeedbackServiceTest extends TestCase {

	private const EVENT = '11111111-1111-4111-8111-111111111111';

	/** @var array<string, array<string, mixed>> Attendance rows by id. */
	private array $attendance = [];

	/** @var list<array{0: string, 1: bool}> Sent: uid, reminder. */
	private array $sent = [];

	/** @var list<array<string, mixed>> Read filters. */
	private array $filters = [];

	/**
	 * "Summer Siege 2025": Anna and Sanne checked in, Karel a no-show (also
	 * returned by the store, to prove the service checks the status itself).
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->attendance = [
			'a-anna' => ['id' => 'a-anna', 'event' => self::EVENT, 'character' => 'c-anna', 'status' => 'checked-in'],
			'a-sanne' => ['id' => 'a-sanne', 'event' => self::EVENT, 'character' => ['id' => 'c-sanne'], 'status' => 'checked-in'],
			'a-karel' => ['id' => 'a-karel', 'event' => self::EVENT, 'character' => 'c-karel', 'status' => 'no-show'],
		];
		$this->sent = [];
		$this->filters = [];
	}//end setUp()

	/**
	 * The service over an in-memory store.
	 *
	 * @param array           $event   The event.
	 * @param list<string>|null $answered Who answered, or null when unknown.
	 *
	 * @return EventFeedbackService
	 */
	private function service(array $event, ?array $answered = null): EventFeedbackService {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $filters, ?int $limit = null) use ($event): array {
				$this->filters[] = [$objectType => $filters];
				if ($objectType === 'event') {
					return [$event];
				}

				return array_values($this->attendance);
			}
		);
		$fetcher->method('getObject')->willReturnCallback(
			static fn (string $objectType, string $id): array => ['c-anna' => ['ownerUid' => 'anna'], 'c-sanne' => ['ownerUid' => 'sanne'], 'c-karel' => ['ownerUid' => 'karel']][$id]
		);
		$fetcher->method('saveObjectWithAppAuthority')->willReturnCallback(
			function (string $objectType, array $data, ?string $uuid = null): array {
				self::assertSame('attendance', $objectType);
				$this->attendance[(string)$uuid] = $data + $this->attendance[(string)$uuid];

				return $this->attendance[(string)$uuid];
			}
		);

		$delivery = $this->createMock(FeedbackDelivery::class);
		$delivery->method('send')->willReturnCallback(
			function (string $uid, array $event, int $formId, bool $reminder): bool {
				self::assertSame(42, $formId);
				$this->sent[] = [$uid, $reminder];

				return true;
			}
		);
		$answers = $this->createMock(FeedbackAnswers::class);
		$answers->method('answeredBy')->with(42)->willReturn($answered);

		return new EventFeedbackService($fetcher, $delivery, $answers, $this->createMock(LoggerInterface::class));
	}//end service()

	/**
	 * The event: ended 2025-08-17 16:00, feedback form 42, default window.
	 *
	 * @param array $extra Extra fields.
	 *
	 * @return array
	 */
	private static function event(array $extra = []): array {
		return $extra + ['id' => self::EVENT, 'name' => 'Summer Siege 2025', 'endDate' => '2025-08-17T16:00:00+00:00', 'feedbackForm' => 42];
	}//end event()

	/**
	 * Scenario "A game master sets up the post-event letter": feedback opens at the end and closes 21 days later.
	 *
	 * @return void
	 */
	public function testTheWindowDefaultsToTheEndAndTwentyOneDays(): void {
		$window = EventFeedbackService::window(event: self::event());
		self::assertSame('2025-08-17T16:00:00+00:00', $window['opens']->format(DATE_ATOM));
		self::assertSame('2025-09-07T16:00:00+00:00', $window['closes']->format(DATE_ATOM));

		$set = EventFeedbackService::window(event: self::event(['feedbackOpensAt' => '2025-08-18T09:00:00+00:00', 'feedbackClosesAt' => '2025-08-25T09:00:00+00:00']));
		self::assertSame(['2025-08-18T09:00:00+00:00', '2025-08-25T09:00:00+00:00'], [$set['opens']->format(DATE_ATOM), $set['closes']->format(DATE_ATOM)]);
		self::assertNull(EventFeedbackService::window(event: ['feedbackForm' => 42]));
	}//end testTheWindowDefaultsToTheEndAndTwentyOneDays()

	/**
	 * Scenario "Anna gets her invitation": the checked-in participants are invited once; Karel, a no-show, never.
	 *
	 * @return void
	 */
	public function testCheckedInParticipantsAreInvitedOnceAndNoShowsNever(): void {
		$service = $this->service(event: self::event());

		$first = $service->daily(now: new DateTimeImmutable('2025-08-18T03:00:00+00:00'));
		self::assertSame(['events' => 1, 'invited' => 2, 'reminded' => 0], $first);
		self::assertSame([['anna', false], ['sanne', false]], $this->sent);
		self::assertSame('2025-08-18T03:00:00+00:00', $this->attendance['a-anna']['feedbackInvitedAt']);
		self::assertArrayNotHasKey('feedbackInvitedAt', $this->attendance['a-karel']);
		self::assertContains(['event' => ['feedbackForm' => 'IS NOT NULL']], $this->filters);
		self::assertContains(['attendance' => ['event' => self::EVENT, 'status' => 'checked-in']], $this->filters);

		$second = $service->daily(now: new DateTimeImmutable('2025-08-19T03:00:00+00:00'));
		self::assertSame(['events' => 1, 'invited' => 0, 'reminded' => 0], $second);
		self::assertCount(2, $this->sent, 'nobody is invited twice');
	}//end testCheckedInParticipantsAreInvitedOnceAndNoShowsNever()

	/**
	 * Scenario "Sanne forgot": five days before closing, those who have not answered are reminded, once.
	 *
	 * @return void
	 */
	public function testThoseWhoHaveNotAnsweredAreRemindedOnceFiveDaysBeforeClosing(): void {
		$service = $this->service(event: self::event(), answered: ['anna']);
		$service->daily(now: new DateTimeImmutable('2025-08-18T03:00:00+00:00'));
		$this->sent = [];

		self::assertSame(['events' => 1, 'invited' => 0, 'reminded' => 0], $service->daily(now: new DateTimeImmutable('2025-09-02T15:59:00+00:00')));
		self::assertSame(['events' => 1, 'invited' => 0, 'reminded' => 1], $service->daily(now: new DateTimeImmutable('2025-09-02T16:00:00+00:00')));
		self::assertSame([['sanne', true]], $this->sent);
		self::assertSame('2025-09-02T16:00:00+00:00', $this->attendance['a-sanne']['feedbackRemindedAt']);

		self::assertSame(['events' => 1, 'invited' => 0, 'reminded' => 0], $service->daily(now: new DateTimeImmutable('2025-09-03T03:00:00+00:00')));
		self::assertCount(1, $this->sent, 'nobody is reminded twice');
	}//end testThoseWhoHaveNotAnsweredAreRemindedOnceFiveDaysBeforeClosing()

	/**
	 * When who answered is unknown (design D3 fallback), every invitee is reminded.
	 *
	 * @return void
	 */
	public function testWithoutPerUserAnswersEveryInviteeIsReminded(): void {
		$service = $this->service(event: self::event(), answered: null);
		$service->daily(now: new DateTimeImmutable('2025-08-18T03:00:00+00:00'));
		$this->sent = [];

		$service->daily(now: new DateTimeImmutable('2025-09-03T03:00:00+00:00'));
		self::assertSame([['anna', true], ['sanne', true]], $this->sent);
	}//end testWithoutPerUserAnswersEveryInviteeIsReminded()

	/**
	 * Before feedback opens and once it closed nothing is sent, and an event without a form is skipped.
	 *
	 * @return void
	 */
	public function testNothingIsSentOutsideTheWindow(): void {
		self::assertSame(['events' => 0, 'invited' => 0, 'reminded' => 0], $this->service(event: self::event())->daily(now: new DateTimeImmutable('2025-08-17T15:59:00+00:00')));
		self::assertSame(['events' => 0, 'invited' => 0, 'reminded' => 0], $this->service(event: self::event())->daily(now: new DateTimeImmutable('2025-09-07T16:00:00+00:00')));
		self::assertSame(['events' => 0, 'invited' => 0, 'reminded' => 0], $this->service(event: self::event(['feedbackForm' => 0]))->daily(now: new DateTimeImmutable('2025-08-18T03:00:00+00:00')));
		self::assertSame([], $this->sent);
	}//end testNothingIsSentOutsideTheWindow()

	/**
	 * A participant without an account is stamped, so the job does not retry them daily, and counted as not sent.
	 *
	 * @return void
	 */
	public function testAParticipantWithoutAnAccountIsStampedButNotCounted(): void {
		$this->attendance = ['a-npc' => ['id' => 'a-npc', 'event' => self::EVENT, 'character' => '', 'status' => 'checked-in']];
		$counts = $this->service(event: self::event())->daily(now: new DateTimeImmutable('2025-08-18T03:00:00+00:00'));

		self::assertSame(0, $counts['invited']);
		self::assertSame([], $this->sent);
		self::assertSame('2025-08-18T03:00:00+00:00', $this->attendance['a-npc']['feedbackInvitedAt']);
	}//end testAParticipantWithoutAnAccountIsStampedButNotCounted()

	/**
	 * One failing event is logged and the others still run.
	 *
	 * @return void
	 */
	public function testAFailingEventIsLogged(): void {
		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjectsWithAppAuthority')->willReturnCallback(
			static function (string $objectType): array {
				if ($objectType === 'event') {
					return [self::event()];
				}

				throw new RuntimeException('store down');
			}
		);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with('Larpinq: feedback for event {id} was not sent.', $this->callback(static fn (array $context): bool => $context['id'] === self::EVENT));

		$service = new EventFeedbackService($fetcher, $this->createMock(FeedbackDelivery::class), $this->createMock(FeedbackAnswers::class), $logger);
		self::assertSame(['events' => 1, 'invited' => 0, 'reminded' => 0], $service->daily(now: new DateTimeImmutable('2025-08-18T03:00:00+00:00')));
	}//end testAFailingEventIsLogged()

}//end class
