<?php

/**
 * Ticket choices controller for Larpinq.
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
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\TicketChoiceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

/**
 * What a registration's player may choose (ticket types on sale, hidden ones
 * only with a valid code, the options), and how many accepted registrations of
 * an event chose each ticket type and option
 * (registration-ticket-types-and-options REQ-RTO-002, REQ-RTO-003,
 * REQ-RTO-006). Hidden ticket types and codes are not readable by players
 * through the object API, so the offer is served here, behind the
 * registration's own player check.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationChoicesController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param TicketChoiceService $choices The offer and the counts.
	 * @param RegisterObjectFetcher $fetcher Reads the registration as the signed-in user.
	 * @param IUserSession $userSession The user session.
	 * @param IGroupManager $groupManager The group manager.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly TicketChoiceService $choices,
		private readonly RegisterObjectFetcher $fetcher,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The ticket types and options the registration's player may choose now.
	 *
	 * @param string $id The registration UUID.
	 * @param string $code The code the player typed, or empty.
	 *
	 * @return JSONResponse `{ticketTypes, options, code, chosen}`, or 401, 403, 404.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function offer(string $id, string $code = ''): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$registration = $this->fetcher->getObject(objectType: 'registration', id: $id);
		} catch (Throwable $e) {
			return new JSONResponse(data: ['error' => 'Registration not found'], statusCode: Http::STATUS_NOT_FOUND);
		}

		$uid = $user->getUID();
		$own = in_array($uid, [(string)($registration['playerUid'] ?? ''), (string)($registration['submitterUid'] ?? '')], true);
		if ($own === false && $this->isGameMaster(uid: $uid) === false) {
			return new JSONResponse(data: ['error' => 'Only game masters and the player see this offer'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse(data: $this->choices->offer(registration: $registration, code: $code));
	}//end offer()

	/**
	 * How many accepted registrations of the event chose each ticket type and option.
	 *
	 * @param string $id The event UUID.
	 *
	 * @return JSONResponse `{ticketTypes: [{id, name, role, count}], options: [{id, name, category, count}]}`, or 401, 403.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function counts(string $id): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		if ($this->isGameMaster(uid: $user->getUID()) === false) {
			return new JSONResponse(data: ['error' => 'Only game masters see the choices'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse(data: $this->choices->counts(eventId: $id));
	}//end counts()

	/**
	 * Whether a user is a game master (the group, or a Nextcloud admin).
	 *
	 * @param string $uid The user id.
	 *
	 * @return bool True for a game master.
	 */
	private function isGameMaster(string $uid): bool {
		return $this->groupManager->isInGroup($uid, Application::GM_GROUP) === true
			|| $this->groupManager->isAdmin($uid) === true;
	}//end isGameMaster()
}//end class
