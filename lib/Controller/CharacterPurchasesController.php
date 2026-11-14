<?php

/**
 * Character purchases controller for Larpinq.
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
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Service\PurchaseRefusedException;
use OCA\Larpinq\Service\PurchaseService;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Buys an item for a character with the character's in-game money (REQ-RPG-001).
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
 */
class CharacterPurchasesController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest        $request      The request.
	 * @param PurchaseService $purchases    The purchase service.
	 * @param CallerAccess    $callerAccess Who is calling and whether they are a game master.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	public function __construct(
		IRequest $request,
		private readonly PurchaseService $purchases,
		private readonly CallerAccess $callerAccess,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);
	}//end __construct()

	/**
	 * Buy an item for a character; only its owner or a game master may.
	 *
	 * @param string $id     The character UUID.
	 * @param string $itemId The item UUID.
	 *
	 * @return JSONResponse The saved character, or the refusal.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	#[NoAdminRequired]
	public function buy(string $id, string $itemId = ''): JSONResponse {
		$user = $this->callerAccess->user();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			$character = $this->purchases->character(characterId: $id);
			$uid       = $user->getUID();
			$owner     = (string)($character['ownerUid'] ?? '');
			if (($owner === '' || $owner !== $uid) && $this->callerAccess->isGameMasterOrAdmin(uid: $uid) === false) {
				return new JSONResponse(
					data: ['error' => 'Only the owner and game masters buy for this character'],
					statusCode: Http::STATUS_FORBIDDEN
				);
			}

			if ($itemId === '') {
				return new JSONResponse(data: ['error' => 'Name the item to buy'], statusCode: Http::STATUS_BAD_REQUEST);
			}

			return new JSONResponse(data: $this->purchases->buy(character: $character, itemId: $itemId));
		} catch (PurchaseRefusedException $refused) {
			return new JSONResponse(data: ['error' => $refused->getMessage()], statusCode: $refused->getStatus());
		}
	}//end buy()
}//end class
