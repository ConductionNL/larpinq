<?php

/**
 * Larpinq Form Submission Listener
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\RegistrationService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Turns a sign-up on an event's form into a registration.
 *
 * Nextcloud Forms dispatches `FormSubmittedEvent` after it stored an answer.
 * When an event takes sign-ups from that form (`event.signupForm`), the
 * submitting account gets one registration for the event, with its player
 * when one is linked. The answers stay in Forms. An anonymous answer, or a
 * second answer from the same account for the same event, creates nothing.
 * The registration is written with the app's authority: players may not
 * create registrations themselves.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @template-implements IEventListener<Event>
 *
 * @psalm-suppress UndefinedClass Nextcloud Forms is an optional dependency.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class FormSubmissionListener implements IEventListener {

	/**
	 * Nextcloud Forms' submit event, resolved at runtime.
	 *
	 * @var string
	 */
	public const SUBMITTED_EVENT = 'OCA\Forms\Events\FormSubmittedEvent';

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads events and players, writes the registration.
	 * @param IUserManager $users Tells an account from an anonymous answer.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
		private readonly IUserManager $users,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle a Forms submission.
	 *
	 * @param Event $event The dispatched event.
	 *
	 * @return void
	 *
	 * @psalm-suppress MixedMethodCall Nextcloud Forms classes are optional dependencies.
	 * @psalm-suppress MixedAssignment Nextcloud Forms classes are optional dependencies.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function handle(Event $event): void {
		if (is_a($event, self::SUBMITTED_EVENT) === false || is_callable([$event, 'getWebhookSerializable']) === false) {
			return;
		}

		$payload = (array)$event->getWebhookSerializable();
		$formId = (int)(((array)($payload['form'] ?? []))['id'] ?? 0);
		$submission = (array)($payload['submission'] ?? []);
		if ($formId <= 0) {
			return;
		}

		try {
			$this->intake(formId: $formId, submission: $submission);
		} catch (Throwable $e) {
			$this->logger->error('Larpinq: a sign-up on form {form} did not become a registration.', ['form' => $formId, 'exception' => $e]);
		}
	}//end handle()

	/**
	 * Create the registration for one submission, when it is due.
	 *
	 * @param int $formId The form.
	 * @param array<string, mixed> $submission The submission as Forms serialises it.
	 *
	 * @return void
	 */
	private function intake(int $formId, array $submission): void {
		$events = $this->fetcher->getObjectsWithAppAuthority(objectType: 'event', filters: ['signupForm' => $formId], limit: 1);
		if ($events === []) {
			return;
		}

		$eventId = (string)($events[0]['id'] ?? '');
		$uid = (string)($submission['userId'] ?? '');
		if ($uid === '' || str_starts_with($uid, 'anon-user-') === true || $this->users->userExists($uid) === false) {
			$this->logger->info('Larpinq: an anonymous sign-up for event {event} is not a registration.', ['event' => $eventId]);
			return;
		}

		$existing = $this->fetcher->getObjectsWithAppAuthority(objectType: 'registration', filters: ['event' => $eventId, 'submitterUid' => $uid], limit: 1);
		if ($existing !== []) {
			$this->logger->info('Larpinq: {user} signed up for event {event} again; the first registration stands.', ['user' => $uid, 'event' => $eventId]);
			return;
		}

		$registration = [
			'event' => $eventId,
			'submitterUid' => $uid,
			'status' => RegistrationService::PENDING,
			'submissionId' => (int)($submission['id'] ?? 0),
			'submittedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
		];
		$players = $this->fetcher->getObjectsWithAppAuthority(objectType: 'player', filters: ['userUid' => $uid], limit: 1);
		if ($players !== []) {
			$registration['player'] = (string)($players[0]['id'] ?? '');
		}

		$this->fetcher->saveObjectWithAppAuthority(objectType: 'registration', data: $registration);
	}//end intake()
}//end class
