<?php

/**
 * Larpinq Registration Payments Controller
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
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Service\PaymentRequestRefusedException;
use OCA\Larpinq\Service\RegistrationPaymentService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * The game masters' "Request payment" action on a registration whose payment
 * waits (registration-payments-through-shillinq), and whether the signed-in
 * user sees it. Only game masters raise a request: the service checks the
 * group before it reads the registration.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class RegistrationPaymentsController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param RegistrationPaymentService $payments Raises the request.
	 * @param IUserSession $userSession The user session.
	 * @param IL10N $l10n Translations for refusals.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RegistrationPaymentService $payments,
		private readonly IUserSession $userSession,
		private readonly IL10N $l10n,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Whether the signed-in user may request payments.
	 *
	 * @return JSONResponse `{allowed: bool}`.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function access(): JSONResponse {
		$uid = (string)$this->userSession->getUser()?->getUID();

		return new JSONResponse(data: ['allowed' => $this->payments->isGameMaster(uid: $uid)]);
	}//end access()

	/**
	 * Raise the payment request of a registration whose payment waits.
	 *
	 * @param string $id The registration UUID.
	 *
	 * @return JSONResponse The registration, or 401, 403, 404, 409, 502 with the reason.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	#[NoAdminRequired]
	public function request(string $id): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			return new JSONResponse(data: $this->payments->request(registrationId: $id, actingUid: $user->getUID()));
		} catch (PaymentRequestRefusedException $e) {
			return new JSONResponse(data: ['error' => $this->l10n->t($e->getMessage())], statusCode: $e->getStatus());
		}
	}//end request()
}//end class
