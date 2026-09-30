<?php

/**
 * Dashboard controller for Larpinq
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-98
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-99
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-100
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-101
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Dashboard controller for Larpinq main page
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-98
 */
class DashboardController extends Controller {
	/**
	 * Constructor for DashboardController
	 *
	 * @param string $appName Application name
	 * @param IRequest $request HTTP request object
	 * @param IInitialState $initialState The initial state handed to the frontend
	 * @param IUserSession $userSession The user session
	 * @param IGroupManager $groupManager The group manager
	 * @param IConfig       $config       The per-user preferences store
	 */
	public function __construct(
		$appName,
		IRequest $request,
		private readonly IInitialState $initialState,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly IConfig $config,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * This returns the template of the main app's page
	 * It adds some data to the template (app version)
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-98
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-99
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-100
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-101
	 * @spec openspec/specs/data-portability/spec.md
	 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
	 */
	public function page(): TemplateResponse {
		// The frontend offers the imports to game masters too: the larpinq
		// register grants them `manage`, which OpenRegister's import asks for.
		$this->initialState->provideInitialState('isGameMaster', $this->isGameMaster());
		// The world the user last chose, so lists are narrowed before their
		// first fetch; the frontend falls back to all worlds when it is gone.
		$this->initialState->provideInitialState('activeWorld', $this->activeWorld());

		return new TemplateResponse(
			Application::APP_ID,
			'index',
			[]
		);
	}//end page()

	/**
	 * Serve the SPA for deep links (Vue history mode). Delegates to {@see page()}.
	 *
	 * Without this the server has no handler for `/apps/larpinq/<route>`, so a
	 * deep link or a RELOAD on any sub-path 404s before the SPA ever loads —
	 * which is why this app was the one of the seven still unable to move off
	 * hash routing. Measured before this change: /apps/larpinq/characters and
	 * /events both returned 404, while every other hash-mode app answered 200.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 *
	 * @spec exclude Vue history-mode fallback — delegates to page(); pure framework plumbing, no domain logic.
	 */
	public function catchAll(): TemplateResponse {
		return $this->page();
	}//end catchAll()

	/**
	 * The signed-in user's active world, stored by the preferences API under
	 * `active-world`; an empty string means all worlds.
	 *
	 * @return string The world UUID, or ''.
	 *
	 * @spec openspec/changes/events-world-scope-and-upcoming/specs/setting-management/spec.md
	 */
	private function activeWorld(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return '';
		}

		return (string)$this->config->getUserValue(
			userId: $user->getUID(),
			appName: Application::APP_ID,
			key: 'pref_active-world',
			default: ''
		);
	}//end activeWorld()

	/**
	 * Whether the signed-in user is a game master (the group, or an admin).
	 *
	 * @return bool True for a game master or admin.
	 *
	 * @spec openspec/specs/data-portability/spec.md
	 */
	private function isGameMaster(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		$uid = $user->getUID();
		return $this->groupManager->isInGroup($uid, Application::GM_GROUP) === true
			|| $this->groupManager->isAdmin($uid) === true;
	}//end isGameMaster()
}//end class
