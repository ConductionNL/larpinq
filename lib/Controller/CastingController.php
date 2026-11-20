<?php

/**
 * Casting endpoints: the proposal and the confirmation, game masters only.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-game-masters-confirm-the-casting-req-ecc-003
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Service\CastingRefusedException;
use OCA\Larpinq\Service\CastingService;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The casting proposal and its confirmation for one event.
 *
 * A thin HTTP boundary: the game master check lives here, the optimisation
 * and the writes in CastingService.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
 */
class CastingController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest       $request      The request.
	 * @param CastingService $casting      Proposes and confirms the casting.
	 * @param CallerAccess   $callerAccess The signed-in caller and the game master check.
	 *
	 * @spec exclude pure framework plumbing: constructor dependency injection.
	 */
	public function __construct(
		IRequest $request,
		private readonly CastingService $casting,
		private readonly CallerAccess $callerAccess,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);

	}//end __construct()

	/**
	 * The proposed casting of the event. Writes nothing.
	 *
	 * @param string $id The event id.
	 *
	 * @return JSONResponse The pairs, the unmatched players and the rank counts; 401, 403, or 422 when the casting is too large.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-larpinq-proposes-the-best-overall-casting-req-ecc-002
	 */
	#[NoAdminRequired]
	public function proposal(string $id): JSONResponse {
		[, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		try {
			return new JSONResponse(data: $this->casting->propose(eventId: $id));
		} catch (CastingRefusedException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_UNPROCESSABLE_ENTITY);
		}

	}//end proposal()

	/**
	 * Confirm the pairs the game master kept or changed.
	 *
	 * @param string $id The event id.
	 *
	 * @return JSONResponse The pairs written and the pairs refused with the reason; 401, 403, 400 without pairs.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/changes/events-casting-and-crew-roles/specs/event-casting/spec.md#requirement-game-masters-confirm-the-casting-req-ecc-003
	 */
	#[NoAdminRequired]
	public function confirm(string $id): JSONResponse {
		[, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		$pairs = $this->request->getParam('pairs', []);
		if (is_array($pairs) === false || $pairs === []) {
			return new JSONResponse(data: ['error' => 'No pairs to confirm'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		$pairs = array_values(array_filter($pairs, 'is_array'));

		try {
			return new JSONResponse(data: $this->casting->confirm(eventId: $id, pairs: $pairs));
		} catch (CastingRefusedException $e) {
			return new JSONResponse(data: ['error' => $e->getMessage()], statusCode: Http::STATUS_UNPROCESSABLE_ENTITY);
		}

	}//end confirm()
}//end class
