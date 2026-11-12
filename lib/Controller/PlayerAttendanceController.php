<?php

/**
 * Player attendance history controller for Larpinq.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/events-players/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Support\AppIdentity;
use OCA\Larpinq\Service\CharacterConnectionGuard;
use OCA\Larpinq\Service\PlayerAttendanceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The events a player attended, for game masters and for the player themself
 * (players-attendance-history REQ-PAH-001, REQ-PAH-002). Attendance is not
 * readable by players through the object API (DECISIONS row 30), so the
 * player's own list is served here, behind larpinq's ownership check.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/specs/events-players/spec.md
 */
class PlayerAttendanceController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param PlayerAttendanceService $history Reads the history.
	 * @param CharacterConnectionGuard $guard Decides whether the caller is the player.
	 * @param CallerAccess $callerAccess The signed-in caller and the game master check.
	 */
	public function __construct(
		IRequest $request,
		private readonly PlayerAttendanceService $history,
		private readonly CharacterConnectionGuard $guard,
		private readonly CallerAccess $callerAccess,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);
	}//end __construct()

	/**
	 * The events the player was checked in at, newest first, and how many.
	 * Game masters see every player's; a player sees only their own.
	 *
	 * @param string $id The player UUID.
	 *
	 * @return JSONResponse `{count, events: [{id, event, character, eventStartDate, checkedInAt}]}`, or 401, 403.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/events-players/spec.md
	 */
	#[NoAdminRequired]
	public function index(string $id): JSONResponse {
		$user = $this->callerAccess->user();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		$uid = $user->getUID();
		if ($this->callerAccess->isGameMasterOrAdmin(uid: $uid) === false && $this->guard->owns(objectType: 'player', id: $id, userId: $uid) === false) {
			return new JSONResponse(data: ['error' => 'Only game masters and the player see this history'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse(data: $this->history->history(playerId: $id));
	}//end index()
}//end class
