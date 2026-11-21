<?php

/**
 * Character resubmission controller for Larpinq.
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
 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Service\CharacterResubmissionService;
use OCA\Larpinq\Service\ResubmissionRefusedException;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * POST /api/characters/{id}/resubmit: the owner sends a rejected character back to the game masters.
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md
 */
class CharacterResubmissionController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest                     $request      The request.
	 * @param CharacterResubmissionService $resubmission Moves a rejected character back to waiting.
	 * @param CallerAccess                 $callerAccess The signed-in caller and the game master check.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly CharacterResubmissionService $resubmission,
		private readonly CallerAccess $callerAccess,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Submit a rejected character again.
	 *
	 * @param string $id The character.
	 *
	 * @return JSONResponse The saved character, or the refusal with its status.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/character-management/spec.md#requirement-a-game-master-rejects-a-submitted-character-with-a-reason-req-cta-001
	 */
	#[NoAdminRequired]
	public function resubmit(string $id): JSONResponse {
		$user = $this->callerAccess->user();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		$uid = $user->getUID();
		try {
			return new JSONResponse(
				data: $this->resubmission->resubmit(
					characterId: $id,
					uid: $uid,
					isGameMaster: $this->callerAccess->isGameMasterOrAdmin(uid: $uid)
				)
			);
		} catch (ResubmissionRefusedException $refused) {
			return new JSONResponse(data: ['error' => $refused->getMessage()], statusCode: $refused->getStatus());
		}
	}//end resubmit()
}//end class
