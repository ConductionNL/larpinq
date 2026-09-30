<?php

/**
 * Worlds controller for Larpinq.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/setting-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use InvalidArgumentException;
use LengthException;
use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Service\WorldCopyFailedException;
use OCA\Larpinq\Service\WorldCopyService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Copy a world's rules into a new world (worlds-copy-ruleset, design D3).
 *
 * The world endpoints live under `/api/worlds` because `/api/settings` is the
 * app configuration. Every method is for game masters (the
 * `Application::GM_GROUP` group, or a Nextcloud admin); the copy itself runs
 * in WorldCopyService with OpenRegister's RBAC on every write.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/specs/setting-management/spec.md
 */
class WorldsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param WorldCopyService $worldCopy The copy service.
	 * @param IUserSession $userSession The user session.
	 * @param IGroupManager $groupManager The group manager.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly WorldCopyService $worldCopy,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Whether the signed-in user may copy worlds; the world page shows its
	 * "Copy world" action on this answer.
	 *
	 * @return JSONResponse `{allowed: bool}`, or 401.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	#[NoAdminRequired]
	public function access(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		return new JSONResponse(data: ['allowed' => $this->isGameMaster(uid: $user->getUID())]);
	}//end access()

	/**
	 * What a copy of the world would create. Game masters only.
	 *
	 * @param string $id The world UUID.
	 *
	 * @return JSONResponse `{world, counts}`, or 400, 401, 403, 404, 422.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	#[NoAdminRequired]
	public function preview(string $id): JSONResponse {
		$denied = $this->refuseUnlessGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		try {
			return new JSONResponse(data: $this->worldCopy->preview(worldId: $id));
		} catch (InvalidArgumentException | DoesNotExistException | LengthException $e) {
			return $this->refusal(error: $e);
		}
	}//end preview()

	/**
	 * Copy the world under a new name. Game masters only.
	 *
	 * @param string $id The world UUID.
	 * @param string $name The new world's name.
	 *
	 * @return JSONResponse 201 `{world, counts}`, or 400, 401, 403, 404, 422, 500 `{error, leftovers}`.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	#[NoAdminRequired]
	public function copy(string $id, string $name = ''): JSONResponse {
		$denied = $this->refuseUnlessGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		try {
			$result = $this->worldCopy->copy(worldId: $id, name: $name);
		} catch (InvalidArgumentException | DoesNotExistException | LengthException $e) {
			return $this->refusal(error: $e);
		} catch (WorldCopyFailedException $e) {
			$this->logger->error('Copying world {world} failed: {message}', ['world' => $id, 'message' => $e->getMessage(), 'leftovers' => $e->getLeftovers(), 'exception' => $e]);
			return new JSONResponse(
				data: ['error' => $e->getMessage(), 'leftovers' => $e->getLeftovers()],
				statusCode: Http::STATUS_INTERNAL_SERVER_ERROR
			);
		}

		return new JSONResponse(data: $result, statusCode: Http::STATUS_CREATED);
	}//end copy()

	/**
	 * The 401 or 403 response when the user is not a game master, or null.
	 *
	 * @return JSONResponse|null The refusal, or null when allowed.
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	private function refuseUnlessGameMaster(): ?JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		if ($this->isGameMaster(uid: $user->getUID()) === false) {
			return new JSONResponse(data: ['error' => 'Only game masters can copy a world'], statusCode: Http::STATUS_FORBIDDEN);
		}

		return null;
	}//end refuseUnlessGameMaster()

	/**
	 * The response for a refused request: 404, 422 or 400.
	 *
	 * @param InvalidArgumentException|DoesNotExistException|LengthException $error The reason.
	 *
	 * @return JSONResponse The response.
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	private function refusal(InvalidArgumentException|DoesNotExistException|LengthException $error): JSONResponse {
		$status = Http::STATUS_BAD_REQUEST;
		if ($error instanceof DoesNotExistException) {
			$status = Http::STATUS_NOT_FOUND;
		} else if ($error instanceof LengthException) {
			$status = Http::STATUS_UNPROCESSABLE_ENTITY;
		}

		return new JSONResponse(data: ['error' => $error->getMessage()], statusCode: $status);
	}//end refusal()

	/**
	 * Whether a user may act as a game master (the group, or a Nextcloud admin).
	 *
	 * @param string $uid The user id.
	 *
	 * @return bool True for a game master or admin.
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	private function isGameMaster(string $uid): bool {
		return $this->groupManager->isInGroup($uid, Application::GM_GROUP) === true
			|| $this->groupManager->isAdmin($uid) === true;
	}//end isGameMaster()
}//end class
