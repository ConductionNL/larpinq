<?php

/**
 * XP awards controller for Larpinq.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Support\AppIdentity;
use OCA\Larpinq\Service\EventRosterService;
use OCA\Larpinq\Service\XpAwardBatchService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Award XP to a whole event in one save (events-xp-batch-award). Every
 * method is for game masters (the `AppIdentity::GM_GROUP` group, or a
 * Nextcloud admin); the awards themselves are written through OpenRegister,
 * whose xpAward rules apply as well.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/specs/event-xp-awards/spec.md
 */
class XpAwardsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param XpAwardBatchService $batch The batch award.
	 * @param EventRosterService $rosterService The event roster (event lookup).
	 * @param CallerAccess $callerAccess The signed-in caller and the game master check.
	 */
	public function __construct(
		IRequest $request,
		private readonly XpAwardBatchService $batch,
		private readonly EventRosterService $rosterService,
		private readonly CallerAccess $callerAccess,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Whether the signed-in user may award XP; the event page shows its
	 * "Award XP" action on this answer.
	 *
	 * @return JSONResponse `{allowed: bool}`, or 401.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	#[NoAdminRequired]
	public function access(): JSONResponse {
		return $this->callerAccess->gameMasterAnswer();
	}//end access()

	/**
	 * The award roster of an event: participants, attendance, existing awards
	 * and the default ticks. Game masters only.
	 *
	 * @param string $id The event UUID.
	 *
	 * @return JSONResponse `{eventName, rows, attendanceAvailable}`, or 401, 403, 404.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	#[NoAdminRequired]
	public function roster(string $id): JSONResponse {
		[, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		$event = $this->rosterService->getEvent(eventId: $id);
		if ($event === null) {
			return new JSONResponse(data: ['error' => 'Event not found'], statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(data: ['eventName' => (string)($event['name'] ?? '')] + $this->batch->awardRoster(eventId: $id));
	}//end roster()

	/**
	 * Create one award per row. Game masters only.
	 *
	 * @param string $id The event UUID.
	 * @param array<int,mixed> $rows The rows: {character, amount, reason?, extra?}.
	 *
	 * @return JSONResponse `{created, refused}`, or 400, 401, 403, 404.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	#[NoAdminRequired]
	public function award(string $id, array $rows = []): JSONResponse {
		[$actingUid, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		if ($rows === []) {
			return new JSONResponse(data: ['error' => 'No rows to award'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		if ($this->rosterService->getEvent(eventId: $id) === null) {
			return new JSONResponse(data: ['error' => 'Event not found'], statusCode: Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse(data: $this->batch->award(eventId: $id, rows: $rows, actingUid: $actingUid));
	}//end award()
}//end class
