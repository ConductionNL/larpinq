<?php

/**
 * Character stats controller for Larpinq
 *
 * Serves the stat sheet of one character: each ability with its sources and
 * the XP line. A separate controller keeps CharactersController's constructor
 * (and every test that builds it) unchanged.
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Service\CharacterStatSheet;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * GET /api/characters/{id}/stats.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */
class CharacterStatsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param RegisterObjectFetcher $objectFetcher Reads the character as the calling user.
	 * @param CharacterStatSheet $statSheet Computes and shapes the sheet.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly CharacterStatSheet $statSheet,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * The stat sheet of one character.
	 *
	 * @param string $id The character UUID.
	 *
	 * @return JSONResponse `{abilities: [...], xp: {...}|null}`, or 404.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @SuppressWarnings(PHPMD.ShortVariable)
	 *
	 * @spec openspec/specs/character-management/spec.md
	 *
	 * @no-admin-idor-exempt OR-delegated read via
	 * RegisterObjectFetcher::getObject (ADR-022), which reads with RBAC on as
	 * the calling user. An id the caller may not read raises an exception this
	 * method answers with 404, so an out-of-scope id is indistinguishable from
	 * a missing one, and fields the caller may not read are stripped before the
	 * engine sees them.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(string $id): JSONResponse {
		try {
			$character = $this->objectFetcher->getObject(objectType: 'character', id: $id);
		} catch (\Exception $exception) {
			return new JSONResponse(data: ['error' => 'Character not found'], statusCode: 404);
		}

		return new JSONResponse(data: $this->statSheet->present(stats: $this->statSheet->stats(character: $character)));
	}//end show()
}//end class
