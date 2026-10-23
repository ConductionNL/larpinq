<?php

/**
 * Larpinq Registration Changes Controller
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
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Service\RegistrationChangeRefusedException;
use OCA\Larpinq\Service\RegistrationChangeService;
use OCA\Larpinq\Service\TransferOffers;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Cancelling a registration, adding a participant to a booking, and handing a
 * registration to another player (registration-cancel-transfer-refund). Every
 * action names the registration; the services check that the signed-in user is
 * its player, its booker, the player it is offered to, or a game master, before
 * anything is read back or written.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationChangesController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param RegistrationChangeService $changes Cancelling and booking.
	 * @param TransferOffers $transfers Handing over.
	 * @param IUserSession $userSession The user session.
	 * @param IL10N $l10n Translations for refusals.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RegistrationChangeService $changes,
		private readonly TransferOffers $transfers,
		private readonly IUserSession $userSession,
		private readonly IL10N $l10n,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * What the signed-in user may change on a registration now.
	 *
	 * @param string $id The registration UUID.
	 *
	 * @return JSONResponse The answers, or 401 or 404.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function changes(string $id): JSONResponse {
		return $this->answer(action: fn (string $uid): array => $this->changes->whatMayChange(registrationId: $id, actingUid: $uid));
	}//end changes()

	/**
	 * Cancel a registration; `settlement` is the player's choice when the event lets them choose.
	 *
	 * @param string $id The registration UUID.
	 * @param string $settlement `refund`, `credit` or ''.
	 *
	 * @return JSONResponse The registration, or 401, 403, 404 or 409 with the reason.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function cancel(string $id, string $settlement = ''): JSONResponse {
		return $this->answer(action: fn (string $uid): array => $this->changes->cancel(registrationId: $id, actingUid: $uid, choice: $settlement));
	}//end cancel()

	/**
	 * Add a participant to the booking of the caller's registration.
	 *
	 * @param string $id The caller's registration UUID.
	 * @param string $name A new participant's name.
	 * @param string $player A player the caller booked before.
	 *
	 * @return JSONResponse The participant's registration, or 401, 403, 404 or 422 with the reason.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function addParticipant(string $id, string $name = '', string $player = ''): JSONResponse {
		return $this->answer(
			action: fn (string $uid): array => $this->changes->addParticipant(registrationId: $id, actingUid: $uid, name: $name, playerId: $player)
		);
	}//end addParticipant()

	/**
	 * Offer a registration to another player's account.
	 *
	 * @param string $id The registration UUID.
	 * @param string $account The other player's account.
	 *
	 * @return JSONResponse The registration, or 401, 403, 404 or 409 with the reason.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function offerTransfer(string $id, string $account = ''): JSONResponse {
		return $this->answer(action: fn (string $uid): array => $this->transfers->offer(registrationId: $id, actingUid: $uid, recipientUid: $account));
	}//end offerTransfer()

	/**
	 * Accept the registration offered to the caller.
	 *
	 * @param string $id The registration UUID.
	 *
	 * @return JSONResponse The registration, or 401, 403, 404 or 409 with the reason.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function acceptTransfer(string $id): JSONResponse {
		return $this->answer(action: fn (string $uid): array => $this->transfers->accept(registrationId: $id, actingUid: $uid));
	}//end acceptTransfer()

	/**
	 * Withdraw an open offer.
	 *
	 * @param string $id The registration UUID.
	 *
	 * @return JSONResponse The registration, or 401, 403, 404 or 409 with the reason.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function withdrawTransfer(string $id): JSONResponse {
		return $this->answer(action: fn (string $uid): array => $this->transfers->withdraw(registrationId: $id, actingUid: $uid));
	}//end withdrawTransfer()

	/**
	 * Run an action as the signed-in user and answer with its result or its refusal.
	 *
	 * @param callable(string): array<string, mixed> $action The action, given the user id.
	 *
	 * @return JSONResponse The answer.
	 */
	private function answer(callable $action): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			return new JSONResponse(data: $action($user->getUID()));
		} catch (RegistrationChangeRefusedException $e) {
			return new JSONResponse(data: ['error' => $this->l10n->t($e->getMessage())], statusCode: $e->getStatus());
		}
	}//end answer()
}//end class
