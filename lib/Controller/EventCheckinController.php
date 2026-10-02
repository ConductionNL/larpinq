<?php

/**
 * Larpinq Event Check-in Controller
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\CodeCheckin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Check a participant in at the gate by the code of their registration
 * (events-qr-checkin). Game masters only; rate limited per user.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
 */
class EventCheckinController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param IUserSession $userSession The user session.
	 * @param IGroupManager $groups Who is a game master.
	 * @param CodeCheckin $checkin The check-in by code.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groups,
		private readonly CodeCheckin $checkin,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Check in the participant whose registration has the posted code.
	 *
	 * @param string $id The event UUID.
	 *
	 * @return JSONResponse checked-in or already (200), unknown (404), not-accepted or no-character (409), or 401/403.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 120, period: 60)]
	public function checkinByCode(string $id): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		$uid = $user->getUID();
		if ($this->groups->isInGroup($uid, Application::GM_GROUP) === false && $this->groups->isAdmin($uid) === false) {
			return new JSONResponse(data: ['error' => 'Access denied'], statusCode: Http::STATUS_FORBIDDEN);
		}

		$result = $this->checkin->checkIn(eventId: $id, code: (string)$this->request->getParam('code', ''), actingUid: $uid);
		return new JSONResponse(data: $result['body'], statusCode: $result['status']);
	}//end checkinByCode()
}//end class
