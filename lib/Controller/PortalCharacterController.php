<?php

/**
 * Larpinq Portal Character Controller
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
 * @spec openspec/specs/portal-contribution/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Kernel\Contracts\PortalAssertionVerifierInterface;
use OCA\Larpinq\Kernel\Contracts\RegisterObjectFetcherInterface;
use OCA\Larpinq\Support\AppIdentity;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\Security\Bruteforce\IThrottler;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Receives portaliq's forwarded `createCharacter` action.
 *
 * A character created through the portal is played by the player who made
 * it, so it is written with BOTH `ownerRef` (what the portal scopes the
 * player's characters by) and `ocName` (the required player relation) set to
 * the player uuid. OpenRegister validates required properties before
 * ObjectCreatingEvent, so the PortalProfileListener fill of `ocName` came too
 * late for a declarative create; this receiver sends the value itself and the
 * listener stays as a fallback.
 *
 * The route is `#[PublicPage]` and `#[NoCSRFRequired]` because the caller is
 * portaliq's backend, not a browser; the verified `X-Portal-Subject`
 * assertion is the only credential. Order, as in the fleet reference
 * receiver: verify (401), audience and claim (403), input (400), act
 * (201/503). The player uuid comes only from the verified claims, never from
 * the request body.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */
class PortalCharacterController extends Controller {

	/**
	 * The only portal audience that may create a character.
	 *
	 * @var string
	 */
	public const AUDIENCE = 'player';

	/**
	 * The assertion claim holding the linked player's uuid (the action's
	 * declared `scopeClaim`, resolved by portaliq from the portal account).
	 *
	 * @var string
	 */
	public const PLAYER_CLAIM = 'ownerRef';

	/**
	 * The longest character name the schema accepts.
	 *
	 * @var int
	 */
	private const MAX_NAME = 255;

	/**
	 * The longest background the schema accepts.
	 *
	 * @var int
	 */
	private const MAX_BACKGROUND = 2555;

	/**
	 * Brute-force throttler action for rejected assertions.
	 *
	 * @var string
	 */
	private const THROTTLE_ACTION = 'larpinq_portal_character';

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The request.
	 * @param PortalAssertionVerifierInterface $verifier Verifies portaliq's assertion.
	 * @param RegisterObjectFetcherInterface $fetcher Writes the character with the app's authority.
	 * @param IThrottler $throttler Counts rejected calls.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		IRequest $request,
		private readonly PortalAssertionVerifierInterface $verifier,
		private readonly RegisterObjectFetcherInterface $fetcher,
		private readonly IThrottler $throttler,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(appName: AppIdentity::APP_ID, request: $request);
	}//end __construct()

	/**
	 * `createCharacter`: create a character played by the asserted player.
	 *
	 * @return JSONResponse 201 with the character's id and name; 401, 403, 400 or 503 otherwise.
	 *
	 * @spec openspec/specs/portal-contribution/spec.md
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 20, period: 60)]
	#[BruteForceProtection(action: self::THROTTLE_ACTION)]
	public function create(): JSONResponse {
		$player = $this->player();
		if ($player instanceof JSONResponse) {
			return $player;
		}

		$name = $this->text(name: 'name');
		$background = $this->text(name: 'background');
		if ($name === '' || mb_strlen($name) > self::MAX_NAME || mb_strlen($background) > self::MAX_BACKGROUND) {
			return new JSONResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
		}

		$data = ['name' => $name, 'ownerRef' => $player, 'ocName' => $player];
		if ($background !== '') {
			$data['background'] = $background;
		}

		try {
			$saved = $this->fetcher->saveObjectWithAppAuthority('character', $data);
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: portal character not saved', ['reason' => $e->getMessage()]);
			return new JSONResponse(['error' => 'unavailable'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		return new JSONResponse(
			['id' => (string)($saved['id'] ?? ($saved['uuid'] ?? '')), 'name' => $name],
			Http::STATUS_CREATED
		);
	}//end create()

	/**
	 * The asserted player's uuid, or the refusal to return.
	 *
	 * @return string|JSONResponse
	 */
	private function player(): string|JSONResponse {
		$claims = $this->verifier->verify((string)$this->request->getHeader(PortalAssertionVerifierInterface::HEADER));
		if ($claims === null) {
			$this->registerRejected();
			return new JSONResponse(['error' => 'unauthorized'], Http::STATUS_UNAUTHORIZED);
		}

		$player = ($claims[self::PLAYER_CLAIM] ?? null);
		if (($claims['audience'] ?? null) !== self::AUDIENCE || is_string($player) === false || trim($player) === '') {
			return new JSONResponse(['error' => 'forbidden'], Http::STATUS_FORBIDDEN);
		}

		return trim($player);
	}//end player()

	/**
	 * A trimmed string parameter, or '' when absent or not a string.
	 *
	 * @param string $name The parameter name.
	 *
	 * @return string
	 */
	private function text(string $name): string {
		$value = $this->request->getParam($name);
		if (is_string($value) === false) {
			return '';
		}

		return trim($value);
	}//end text()

	/**
	 * Count a rejected call with the brute-force throttler.
	 *
	 * @return void
	 */
	private function registerRejected(): void {
		try {
			$this->throttler->registerAttempt(action: self::THROTTLE_ACTION, ip: $this->request->getRemoteAddress());
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: registerAttempt failed: ' . $e->getMessage());
		}
	}//end registerRejected()
}//end class
