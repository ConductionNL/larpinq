<?php

/**
 * Larpinq Event Messages Controller Test
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
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

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\EventMessagesController;
use OCA\Larpinq\Service\EventMessageRefusedException;
use OCA\Larpinq\Service\EventMessageService;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Only game masters preview, write and send; a message of another event is
 * not found; a sent message is not sent again.
 */
#[CoversClass(EventMessagesController::class)]
#[UsesClass(CallerAccess::class)]
#[UsesClass(EventMessageRefusedException::class)]
class EventMessagesControllerTest extends TestCase {

	private const EVENT = 'a0000000-0000-4000-8000-000000000001';

	private const OTHER_EVENT = 'a0000000-0000-4000-8000-000000000002';

	private const MESSAGE = 'b0000000-0000-4000-8000-000000000001';

	private EventMessageService&MockObject $messages;

	/** @var array<string, mixed> Request parameters. */
	private array $params = [];

	/** @var array<string, array<string, mixed>> Objects by type and id. */
	private array $objects = [];

	/**
	 * The event and its draft message.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->messages = $this->createMock(EventMessageService::class);
		$this->params = [];
		$this->objects = [
			'event:'.self::EVENT              => ['id' => self::EVENT, 'name' => 'Winter Court 2026'],
			'eventmessage:'.self::MESSAGE     => ['@self' => ['id' => self::MESSAGE], 'event' => self::EVENT, 'state' => 'draft'],
		];
	}//end setUp()

	/**
	 * The controller for a signed-in user, a game master or not.
	 *
	 * @param string|null $uid The signed-in user, or null for nobody.
	 * @param bool $gameMaster Whether they are a game master.
	 *
	 * @return EventMessagesController
	 */
	private function controller(?string $uid = 'gm', bool $gameMaster = true): EventMessagesController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->params[$key] ?? $default
		);

		$fetcher = $this->createMock(RegisterObjectFetcher::class);
		$fetcher->method('getObjectOfType')->willReturnCallback(
			function (string $objectType, string $id): array {
				if (isset($this->objects[$objectType.':'.$id]) === false) {
					throw new RuntimeException('not found');
				}

				return $this->objects[$objectType.':'.$id];
			}
		);

		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(
			static fn (string $who, string $group): bool => $gameMaster === true && $group === AppIdentity::GM_GROUP
		);
		$groups->method('isAdmin')->willReturn(false);

		return new EventMessagesController(
			request: $request,
			messages: $this->messages,
			fetcher: $fetcher,
			callerAccess: new CallerAccess(userSession: $session, groupManager: $groups),
			logger: new NullLogger()
		);
	}//end controller()

	/**
	 * A player who is not a game master can neither preview, write nor send.
	 *
	 * @return void
	 */
	public function testAPlayerIsRefusedEverywhere(): void {
		$this->messages->expects($this->never())->method($this->anything());
		$controller = $this->controller(uid: 'player', gameMaster: false);

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->preview(id: self::EVENT)->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->create(id: self::EVENT)->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->send(id: self::EVENT, messageId: self::MESSAGE)->getStatus());
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(uid: null)->preview(id: self::EVENT)->getStatus());
	}//end testAPlayerIsRefusedEverywhere()

	/**
	 * The preview passes the audience from the query, lists as arrays or comma strings.
	 *
	 * @return void
	 */
	public function testThePreviewReadsTheAudienceFromTheQuery(): void {
		$this->params = ['statuses' => 'accepted,waitlisted', 'checkedIn' => 'true', 'ticketRoles' => ['crew']];
		$this->messages->expects($this->once())->method('preview')->with(
			self::EVENT,
			['statuses' => ['accepted', 'waitlisted'], 'checkedIn' => 'true', 'paymentOpen' => false, 'ticketRoles' => ['crew']]
		)->willReturn(['count' => 2, 'names' => ['Anna', 'Bram'], 'noAddressCount' => 1, 'noAddressNames' => ['Cees']]);

		$response = $this->controller()->preview(id: self::EVENT);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(2, $response->getData()['count']);
	}//end testThePreviewReadsTheAudienceFromTheQuery()

	/**
	 * An unknown event is not found.
	 *
	 * @return void
	 */
	public function testAnUnknownEventIsNotFound(): void {
		$this->messages->expects($this->never())->method($this->anything());

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller()->preview(id: self::OTHER_EVENT)->getStatus());
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller()->create(id: self::OTHER_EVENT)->getStatus());
	}//end testAnUnknownEventIsNotFound()

	/**
	 * A message needs a subject and a body; then it is saved as a draft by the game master.
	 *
	 * @return void
	 */
	public function testCreatingADraft(): void {
		$this->params = ['subject' => 'Practical letter', 'body' => ' '];
		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller()->create(id: self::EVENT)->getStatus());

		$this->params = ['subject' => 'Practical letter', 'body' => 'See you at {event}', 'audience' => ['statuses' => ['accepted']]];
		$this->messages->expects($this->once())->method('createDraft')->with(
			self::EVENT,
			['subject' => 'Practical letter', 'body' => 'See you at {event}', 'audience' => ['statuses' => ['accepted']]],
			'gm'
		)->willReturn(['id' => self::MESSAGE, 'state' => 'draft']);

		$response = $this->controller()->create(id: self::EVENT);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('draft', $response->getData()['state']);
	}//end testCreatingADraft()

	/**
	 * Sending queues the message with its event and the sender.
	 *
	 * @return void
	 */
	public function testSendingQueuesTheMessage(): void {
		$this->messages->expects($this->once())->method('queue')->with(
			array_merge($this->objects['eventmessage:'.self::MESSAGE], ['id' => self::MESSAGE]),
			$this->objects['event:'.self::EVENT],
			'gm'
		)->willReturn(['state' => 'queued']);

		$response = $this->controller()->send(id: self::EVENT, messageId: self::MESSAGE);

		$this->assertSame(Http::STATUS_ACCEPTED, $response->getStatus());
	}//end testSendingQueuesTheMessage()

	/**
	 * A message of another event is not found through this event.
	 *
	 * @return void
	 */
	public function testAMessageOfAnotherEventIsNotFound(): void {
		$this->objects['event:'.self::OTHER_EVENT] = ['id' => self::OTHER_EVENT];
		$this->messages->expects($this->never())->method('queue');

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller()->send(id: self::OTHER_EVENT, messageId: self::MESSAGE)->getStatus());
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller()->send(id: self::EVENT, messageId: 'b0000000-0000-4000-8000-00000000dead')->getStatus());
	}//end testAMessageOfAnotherEventIsNotFound()

	/**
	 * A second send is refused with 409.
	 *
	 * @return void
	 */
	public function testASecondSendIsRefused(): void {
		$this->messages->method('queue')->willThrowException(new EventMessageRefusedException('This message was already sent; send a copy instead.'));

		$response = $this->controller()->send(id: self::EVENT, messageId: self::MESSAGE);

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		$this->assertSame('This message was already sent; send a copy instead.', $response->getData()['error']);
	}//end testASecondSendIsRefused()
}//end class
