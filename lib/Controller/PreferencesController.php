<?php

/**
 * Larpinq PreferencesController.
 *
 * Generic per-user key/value preferences, backed by Nextcloud IConfig
 * user values. Used by shared @conduction/nextcloud-vue widgets (e.g.
 * CnSupportDialog's "seen" flag) that need to persist a small per-user
 * UI flag cross-device without a bespoke endpoint per feature.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git_id>
 *
 * @link https://github.com/ConductionNL/larpinq
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Per-user preferences controller.
 *
 * @spec openspec/changes/retrofit-2026-05-26-preferences-api/tasks.md#task-1
 */
class PreferencesController extends Controller {
	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param IConfig $config The Nextcloud config (user values).
	 * @param IUserSession $userSession The user session.
	 */
	public function __construct(
		IRequest $request,
		private readonly IConfig $config,
		private readonly IUserSession $userSession,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);

	}//end __construct()

	/**
	 * Read a per-user preference value.
	 *
	 * @param string $key The preference key (kebab/alphanumeric).
	 *
	 * @return JSONResponse `{value: string|null}`.
	 *
	 * @spec openspec/changes/retrofit-2026-05-26-preferences-api/tasks.md#task-1
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getPreference(string $key): JSONResponse {
		$slot = $this->slotFor(key: $key);
		if ($slot instanceof JSONResponse) {
			return $slot;
		}

		$value = $this->config->getUserValue(
			userId: $slot->userId,
			appName: AppIdentity::APP_ID,
			key: $slot->configKey,
			default: ''
		);

		$stored = null;
		if ($value !== '') {
			$stored = $value;
		}

		return new JSONResponse(data: ['value' => $stored]);
	}//end getPreference()

	/**
	 * Write a per-user preference value. An empty value clears it.
	 *
	 * CSRF protection is required — this is a state-mutating PUT endpoint.
	 * Note: @NoCSRFRequired removed to close the CSRF-forgery surface (closes #213).
	 *
	 * @param string $key The preference key (kebab/alphanumeric).
	 * @param string $value The value to store (empty string clears it).
	 *
	 * @return JSONResponse `{value: string|null}`.
	 *
	 * @spec openspec/changes/retrofit-2026-05-26-preferences-api/tasks.md#task-2
	 *
	 * @NoAdminRequired
	 */
	public function setPreference(string $key, string $value = ''): JSONResponse {
		$slot = $this->slotFor(key: $key);
		if ($slot instanceof JSONResponse) {
			return $slot;
		}

		$stored = null;
		if ($value === '') {
			$this->config->deleteUserValue(
				userId: $slot->userId,
				appName: AppIdentity::APP_ID,
				key: $slot->configKey
			);

			return new JSONResponse(data: ['value' => $stored]);
		}

		$this->config->setUserValue(
			userId: $slot->userId,
			appName: AppIdentity::APP_ID,
			key: $slot->configKey,
			value: $value
		);
		$stored = $value;

		return new JSONResponse(data: ['value' => $stored]);
	}//end setPreference()

	/**
	 * The user value a request may use, or the refusal: 401 with nobody
	 * signed in, 400 for a key that sanitises to nothing.
	 *
	 * @param string $key The raw preference key.
	 *
	 * @return PreferenceSlot|JSONResponse The slot, or the refusal response.
	 */
	private function slotFor(string $key): PreferenceSlot|JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['message' => 'Not logged in'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		$safeKey = $this->sanitizeKey(key: $key);
		if ($safeKey === '') {
			return new JSONResponse(data: ['message' => 'Invalid key'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return new PreferenceSlot(userId: $user->getUID(), configKey: 'pref_' . $safeKey);
	}//end slotFor()

	/**
	 * Restrict keys to a safe charset so callers cannot reach arbitrary
	 * IConfig user values outside the `pref_` namespace.
	 *
	 * @param string $key The raw key.
	 *
	 * @return string The sanitised key, or '' when nothing safe remains.
	 */
	private function sanitizeKey(string $key): string {
		$safe = preg_replace(pattern: '/[^a-z0-9-]/', replacement: '', subject: strtolower($key));
		return substr((string)$safe, offset: 0, length: 64);
	}//end sanitizeKey()
}//end class
