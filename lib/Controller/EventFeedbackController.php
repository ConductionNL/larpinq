<?php

/**
 * EventFeedbackController for Larpinq
 *
 * Who was invited to give feedback on an event and who answered, for the
 * Feedback tab of the event page (events-post-event-survey).
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Service\EventFeedbackService;
use OCA\Larpinq\Service\FeedbackDelivery;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The feedback summary of one event, game masters only.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
 */
class EventFeedbackController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param IRequest             $request      The request.
	 * @param EventFeedbackService $feedback     The feedback round and the event.
	 * @param FeedbackDelivery     $delivery     Builds the link to the form's results.
	 * @param CallerAccess         $callerAccess The caller checks.
	 */
	public function __construct(
		IRequest $request,
		private readonly EventFeedbackService $feedback,
		private readonly FeedbackDelivery $delivery,
		private readonly CallerAccess $callerAccess,
	) {
		parent::__construct(appName: 'larpinq', request: $request);
	}//end __construct()

	/**
	 * The feedback summary of an event: its form and window, how many invited
	 * participants answered, who has not, and the link to the results.
	 *
	 * @param string $id The event id.
	 *
	 * @return JSONResponse 200 with the summary; 401, 403 or 404.
	 *
	 * @spec openspec/changes/events-post-event-survey/specs/event-feedback/spec.md#requirement-game-masters-see-who-answered-req-eps-004
	 */
	#[NoAdminRequired]
	public function summary(string $id): JSONResponse {
		[, $denied] = $this->callerAccess->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		$event = $this->feedback->event(id: $id);
		if ($event === null) {
			return new JSONResponse(data: ['error' => 'Event not found'], statusCode: Http::STATUS_NOT_FOUND);
		}

		$summary = $this->feedback->summary(event: $event);
		$summary['resultsLink'] = null;
		if ($summary['formId'] > 0) {
			$summary['resultsLink'] = $this->delivery->resultsLink(formId: $summary['formId']);
		}

		return new JSONResponse(data: $summary);
	}//end summary()
}//end class
