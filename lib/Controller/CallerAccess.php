<?php

/**
 * CallerAccess for Larpinq
 *
 * Who is calling and what they may do: the signed-in user, whether they are
 * an administrator, and whether they are a game master or an administrator.
 * Controllers take their caller checks from here instead of each reading the
 * user session and the group manager.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-builds/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

/**
 * The signed-in caller and its admin and game master checks.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-builds/spec.md
 */
class CallerAccess {

	/**
	 * Constructor.
	 *
	 * @param IUserSession $userSession The user session.
	 * @param IGroupManager $groupManager The group manager.
	 */
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * The signed-in user.
	 *
	 * @return IUser|null The user, or null when nobody is signed in.
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 */
	public function user(): ?IUser {
		return $this->userSession->getUser();
	}//end user()

	/**
	 * Whether a user is a Nextcloud administrator.
	 *
	 * @param string $uid The user id.
	 *
	 * @return bool True for an administrator.
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 */
	public function isAdmin(string $uid): bool {
		return $this->groupManager->isAdmin($uid);
	}//end isAdmin()

	/**
	 * Whether a user is in the game master group or is an administrator.
	 *
	 * @param string $uid The user id.
	 *
	 * @return bool True for a game master or an administrator.
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 */
	public function isGameMasterOrAdmin(string $uid): bool {
		return $this->groupManager->isInGroup($uid, AppIdentity::GM_GROUP) === true
			|| $this->groupManager->isAdmin($uid) === true;
	}//end isGameMasterOrAdmin()

	/**
	 * Whether the signed-in user is an administrator; false when nobody is signed in.
	 *
	 * @return bool True for a signed-in administrator.
	 *
	 * @spec openspec/specs/character-builds/spec.md
	 */
	public function currentUserIsAdmin(): bool {
		$user = $this->userSession->getUser();
		return $user !== null && $this->groupManager->isAdmin($user->getUID());
	}//end currentUserIsAdmin()
	/**
	 * Resolve the acting game master, or the refusal response to return.
	 *
	 * Collapses the authenticate-then-authorize pair into one decision so the
	 * endpoints carry a single guard branch and receive a non-empty uid.
	 *
	 * @return array{0: string, 1: JSONResponse|null} The [actingUid, refusal] pair;
	 *                                                the uid is empty when refused.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	public function resolveGameMaster(): array {
		$user = $this->user();
		if ($user === null) {
			return ['', new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED)];
		}

		$uid = $user->getUID();
		if ($this->isGameMasterOrAdmin(uid: $uid) === false) {
			return ['', new JSONResponse(data: ['error' => 'Access denied'], statusCode: Http::STATUS_FORBIDDEN)];
		}

		return [$uid, null];
	}//end resolveGameMaster()

	/**
	 * Whether the signed-in user is a game master or an administrator, as the
	 * `{allowed: bool}` answer a page shows its game-master actions on.
	 *
	 * @return JSONResponse `{allowed: bool}`, or 401 when nobody is signed in.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public function gameMasterAnswer(): JSONResponse {
		$user = $this->user();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(data: ['allowed' => $this->isGameMasterOrAdmin(uid: $user->getUID())]);
	}//end gameMasterAnswer()
}//end class
