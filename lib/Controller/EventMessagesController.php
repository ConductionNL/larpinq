<?php

/**
 * Event messages controller: game masters email an event's participants.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Service\EventMessageRefusedException;
use OCA\Larpinq\Service\EventMessageService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Preview, write and send a message to the participants of one event. Every
 * endpoint is for game masters and administrators only.
 */
class EventMessagesController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param EventMessageService $messages The message service.
	 * @param RegisterObjectFetcher $fetcher Reads the event and the message.
	 * @param CallerAccess $callerAccess The game-master check.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		IRequest $request,
		private readonly EventMessageService $messages,
		private readonly RegisterObjectFetcher $fetcher,
		private readonly CallerAccess $callerAccess,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);
	}//end __construct()

	/**
	 * How many people a message with this audience reaches, the first ten
	 * names and who has no address. The audience comes from the query:
	 * `statuses[]`, `checkedIn`, `paymentOpen`, `ticketRoles[]`.
	 *
	 * @param string $id The event UUID.
	 *
	 * @return JSONResponse `{count, names, noAddressCount, noAddressNames}`, or 401, 403, 404.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-the-recipients-are-shown-before-sending-req-cmp-002
	 */
	#[NoAdminRequired]
	public function preview(string $id): JSONResponse {
		[, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		if ($this->event(id: $id) === null) {
			return $this->notFound(what: 'Event');
		}

		$audience = [
			'statuses'    => $this->listParam(name: 'statuses'),
			'checkedIn'   => $this->request->getParam('checkedIn', false),
			'paymentOpen' => $this->request->getParam('paymentOpen', false),
			'ticketRoles' => $this->listParam(name: 'ticketRoles'),
		];

		return new JSONResponse(data: $this->messages->preview(eventId: $id, audience: $audience));
	}//end preview()

	/**
	 * Save a message to the event's participants as a draft.
	 *
	 * @param string $id The event UUID.
	 *
	 * @return JSONResponse The draft (201), or 400 without subject or body, 401, 403, 404.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-game-masters-write-a-message-to-an-events-participants-req-cmp-001
	 */
	#[NoAdminRequired]
	public function create(string $id): JSONResponse {
		[$uid, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		if ($this->event(id: $id) === null) {
			return $this->notFound(what: 'Event');
		}

		$subject = trim((string)$this->request->getParam('subject', ''));
		$body = trim((string)$this->request->getParam('body', ''));
		if ($subject === '' || $body === '') {
			return new JSONResponse(data: ['error' => 'A message needs a subject and a body'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		$audience = $this->request->getParam('audience', []);
		if (is_array($audience) === false) {
			$audience = [];
		}

		$draft = $this->messages->createDraft(
			eventId: $id,
			input: ['subject' => $subject, 'body' => $body, 'audience' => $audience],
			uid: $uid
		);

		return new JSONResponse(data: $draft, statusCode: Http::STATUS_CREATED);
	}//end create()

	/**
	 * Send a draft message of the event: it is queued and mailed one recipient
	 * at a time. A message is sent once.
	 *
	 * @param string $id The event UUID.
	 * @param string $messageId The message UUID.
	 *
	 * @return JSONResponse The queued message (202), or 401, 403, 404, 409 when it was sent before.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/changes/communication-mail-participants/specs/event-messages/spec.md#requirement-each-recipient-gets-their-own-mail-req-cmp-003
	 */
	#[NoAdminRequired]
	public function send(string $id, string $messageId): JSONResponse {
		[$uid, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		$event = $this->event(id: $id);
		if ($event === null) {
			return $this->notFound(what: 'Event');
		}

		$message = $this->message(id: $messageId);
		// A message of another event is not this event's to send.
		if ($message === null || (string)($message['event'] ?? '') !== $id) {
			return $this->notFound(what: 'Message');
		}

		try {
			$queued = $this->messages->queue(message: $message, event: $event, uid: $uid);
		} catch (EventMessageRefusedException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_CONFLICT);
		}

		return new JSONResponse(data: $queued, statusCode: Http::STATUS_ACCEPTED);
	}//end send()

	/**
	 * The event, or null when there is none with this id.
	 *
	 * @param string $id The event UUID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function event(string $id): ?array {
		try {
			return $this->fetcher->getObjectOfType(objectType: 'event', id: $id);
		} catch (Throwable $e) {
			$this->logger->debug('Larpinq: an event for a message could not be read.', ['exception' => $e]);
			return null;
		}
	}//end event()

	/**
	 * The message with its id at the top level, or null when there is none.
	 *
	 * @param string $id The message UUID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function message(string $id): ?array {
		try {
			$message = $this->fetcher->getObjectOfType(objectType: 'eventmessage', id: $id);
		} catch (Throwable $e) {
			$this->logger->debug('Larpinq: an event message could not be read.', ['exception' => $e]);
			return null;
		}

		$message['id'] = (string)($message['id'] ?? ($message['@self']['id'] ?? $id));

		return $message;
	}//end message()

	/**
	 * A list from the query: an array, or one comma-separated string.
	 *
	 * @param string $name The parameter name.
	 *
	 * @return list<string>
	 */
	private function listParam(string $name): array {
		$value = $this->request->getParam($name, []);
		if (is_string($value) === true) {
			$value = explode(',', $value);
		}

		if (is_array($value) === false) {
			return [];
		}

		return array_values(array_map('strval', $value));
	}//end listParam()

	/**
	 * A 404 answer.
	 *
	 * @param string $what What was not found.
	 *
	 * @return JSONResponse
	 */
	private function notFound(string $what): JSONResponse {
		return new JSONResponse(data: ['error' => $what.' not found'], statusCode: Http::STATUS_NOT_FOUND);
	}//end notFound()
}//end class
