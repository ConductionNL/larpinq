<?php

/**
 * EventFeedbackController: the feedback summary is for game masters only.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\EventFeedbackController;
use OCA\Larpinq\Service\EventFeedbackService;
use OCA\Larpinq\Service\FeedbackDelivery;
use OCA\Larpinq\Support\AppIdentity;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * REQ-EPS-004 of events-post-event-survey.
 */
#[CoversClass(EventFeedbackController::class)]
#[UsesClass(CallerAccess::class)]
class EventFeedbackControllerTest extends TestCase {

	private const EVENT = '11111111-1111-4111-8111-111111111111';

	private EventFeedbackService&MockObject $feedback;
	private FeedbackDelivery&MockObject $delivery;

	/**
	 * The controller for a caller in these groups, or nobody signed in.
	 *
	 * @param list<string>|null $groups The caller's groups; null for no session.
	 *
	 * @return EventFeedbackController
	 */
	private function controllerFor(?array $groups): EventFeedbackController {
		$this->feedback = $this->createMock(EventFeedbackService::class);
		$this->delivery = $this->createMock(FeedbackDelivery::class);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isInGroup')->willReturnCallback(static fn (string $uid, string $group): bool => in_array($group, $groups ?? [], true));
		$groupManager->method('isAdmin')->willReturn(false);
		$user = null;
		if ($groups !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('gm');
		}

		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		return new EventFeedbackController(
			request: $this->createMock(IRequest::class),
			feedback: $this->feedback,
			delivery: $this->delivery,
			callerAccess: new CallerAccess(userSession: $session, groupManager: $groupManager),
		);
	}//end controllerFor()

	/**
	 * A game master gets the summary with the link to the form's results.
	 *
	 * @return void
	 */
	public function testAGameMasterSeesWhoAnswered(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$event = ['id' => self::EVENT, 'feedbackForm' => 42];
		$this->feedback->method('event')->with(self::EVENT)->willReturn($event);
		$this->feedback->method('summary')->with($event)->willReturn(['formId' => 42, 'invited' => 2, 'answered' => 1, 'notAnswered' => [['uid' => 'sanne', 'character' => 'Brannoc']]]);
		$this->delivery->method('resultsLink')->with(42)->willReturn('https://cloud.example/apps/forms/abc/results');

		$response = $controller->summary(id: self::EVENT);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 1, actual: $response->getData()['answered']);
		$this->assertSame(expected: 'https://cloud.example/apps/forms/abc/results', actual: $response->getData()['resultsLink']);
	}//end testAGameMasterSeesWhoAnswered()

	/**
	 * An event without a form has no results link.
	 *
	 * @return void
	 */
	public function testNoFormNoLink(): void {
		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$this->feedback->method('event')->willReturn(['id' => self::EVENT]);
		$this->feedback->method('summary')->willReturn(['formId' => 0, 'invited' => 0, 'answered' => null, 'notAnswered' => null]);
		$this->delivery->expects($this->never())->method('resultsLink');

		$this->assertNull(actual: $controller->summary(id: self::EVENT)->getData()['resultsLink']);
	}//end testNoFormNoLink()

	/**
	 * A player or a steward gets 403, nobody signed in 401, and neither reads anything; an unknown event is 404.
	 *
	 * @return void
	 */
	public function testOnlyGameMastersAndAnUnknownEvent(): void {
		foreach ([[['larpers'], 403], [[AppIdentity::ROLE_STEWARDS], 403], [null, 401]] as [$groups, $status]) {
			$controller = $this->controllerFor(groups: $groups);
			$this->feedback->expects($this->never())->method('event');
			$this->feedback->expects($this->never())->method('summary');
			$this->assertSame(expected: $status, actual: $controller->summary(id: self::EVENT)->getStatus());
		}

		$controller = $this->controllerFor(groups: [AppIdentity::GM_GROUP]);
		$this->feedback->method('event')->willReturn(null);
		$this->assertSame(expected: 404, actual: $controller->summary(id: self::EVENT)->getStatus());
	}//end testOnlyGameMastersAndAnUnknownEvent()
}//end class
