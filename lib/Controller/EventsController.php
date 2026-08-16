<?php

/**
 * Events controller for LarpingApp
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/pdf-export/spec.md
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Controller;

use OCA\LarpingApp\Service\DocuDeskPdfRenderer;
use OCA\LarpingApp\Service\EventRosterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller for event-level operations — the GM run-sheet / cast-list export
 * and the check-in roster.
 *
 * A thin HTTP boundary: authentication and the GM authorization decision live
 * here; every participation, attendance and render-context rule lives in
 * EventRosterService, and persistence is OR-delegated (ADR-022).
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/specs/pdf-export/spec.md
 */
class EventsController extends Controller {

	/**
	 * The GM group allowed to download run-sheets (GM-facing material).
	 *
	 * @var string
	 */
	private const GM_GROUP = 'gamemasters';

	/**
	 * The valid attendance status values (mirrors the larping_attendance enum).
	 *
	 * @var string[]
	 */
	private const ATTENDANCE_STATUSES = [
		'registered',
		'checked-in',
		'no-show',
	];

	/**
	 * Constructor for the EventsController.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param EventRosterService $rosterService The event participation domain service.
	 * @param DocuDeskPdfRenderer $pdfRenderer The shared DocuDesk PDF helper.
	 * @param IUserSession $userSession The user session.
	 * @param IGroupManager $groupManager The group manager.
	 */
	public function __construct(
		$appName,
		IRequest $request,
		private readonly EventRosterService $rosterService,
		private readonly DocuDeskPdfRenderer $pdfRenderer,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Download a per-event GM run-sheet / cast list as a PDF.
	 *
	 * Aggregates GM-facing material (approval status, GM notes, whole-cast
	 * overview), so the endpoint is restricted server-side to the GM group.
	 * Renders via DocuDesk on the same pipeline as the character sheet; returns
	 * 424 when DocuDesk is absent.
	 *
	 * @param string $id The event UUID.
	 * @param string $template The run-sheet template UUID.
	 *
	 * @return DataDownloadResponse|JSONResponse The PDF download or an error.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function downloadRunsheet(string $id, string $template): DataDownloadResponse|JSONResponse {
		// GM-only: the run-sheet exposes approval status and GM-private notes.
		[, $denied] = $this->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		// Validate the template ID to a UUID before probing for DocuDesk, for
		// the same reason as CharactersController::downloadPdf(): input
		// validation is a property of the REQUEST and must not depend on which
		// optional apps are installed. A malformed template id is a 400
		// whether or not DocuDesk is present.
		$templateId = $this->pdfRenderer->normaliseTemplateId($template);
		if ($templateId === null) {
			return new JSONResponse(data: ['error' => 'Invalid template ID: expected a UUID'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		if ($this->pdfRenderer->isDocuDeskAvailable() === false) {
			return new JSONResponse(
				data: ['error' => 'PDF generation requires the DocuDesk app to be installed and enabled'],
				statusCode: 424
			);
		}

		$event = $this->rosterService->getEvent(eventId: $id);
		if ($event === null) {
			return new JSONResponse(data: ['error' => 'Event not found'], statusCode: 404);
		}

		$templateData = $this->pdfRenderer->getTemplate($templateId);
		if ($templateData === null) {
			return new JSONResponse(data: ['error' => 'Template not found'], statusCode: 404);
		}

		$context = $this->rosterService->buildRunsheetContext(event: $event, eventId: $id);
		$pdfString = $this->pdfRenderer->render(templateData: $templateData, context: $context);
		if ($pdfString === null) {
			return new JSONResponse(data: ['error' => 'PDF generation failed. Please contact your administrator.'], statusCode: 500);
		}

		return new DataDownloadResponse($pdfString, $this->runsheetFileName(event: $event), 'application/pdf');
	}//end downloadRunsheet()

	/**
	 * Read the check-in roster for an event.
	 *
	 * Read access is open to any authenticated app user — the roster is
	 * read-only for players; the `isGm` flag tells the client whether to render
	 * the check-in controls. Degrades gracefully: when the `larping_attendance`
	 * schema (or OpenRegister) is unavailable the participant list is still
	 * returned with `attendanceAvailable=false` and no per-row status, so the
	 * event page never breaks (DocuDesk / Forms-leaf degradation pattern).
	 *
	 * @param string $id The event UUID.
	 *
	 * @return JSONResponse The roster payload, or an error response.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function roster(string $id): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		// Fetched to enforce existence + read access; a missing event 404s.
		if ($this->rosterService->getEvent(eventId: $id) === null) {
			return new JSONResponse(data: ['error' => 'Event not found'], statusCode: 404);
		}

		$roster = $this->rosterService->buildRoster(eventId: $id);
		$roster['isGm'] = $this->isGameMaster(uid: $user->getUID());

		return new JSONResponse(data: $roster);
	}//end roster()

	/**
	 * Record or update a participant's attendance for an event (GM-only).
	 *
	 * Server-authoritative: the acting user MUST be in the `gamemasters` group
	 * or a Nextcloud admin, the `(event, character)` pair MUST be a confirmed
	 * participant, and `checkedInAt` / `checkedInBy` are stamped from the server
	 * clock and session — never read from the request body (anti-forgery, the
	 * rule shared with `xpAward.awardedAt` / `awardedBy`). Persistence and the
	 * schema-level RBAC are OR-delegated (ADR-022); this controller adds no
	 * parallel attendance-write auth path. Degrades to a 424 when the
	 * `larping_attendance` schema is absent rather than throwing.
	 *
	 * @param string $id The event UUID.
	 *
	 * @return JSONResponse The persisted attendance record, or an error.
	 *
	 * @NoAdminRequired
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	#[NoAdminRequired]
	public function recordAttendance(string $id): JSONResponse {
		// GM-only: recording attendance is a game-master act at the door.
		[$actingUid, $denied] = $this->resolveGameMaster();
		if ($denied !== null) {
			return $denied;
		}

		// Only the character and status are read from the body; any
		// client-supplied checkedInAt/checkedInBy is discarded (never read).
		$characterId = (string)($this->request->getParam('character', ''));
		$status = (string)($this->request->getParam('status', 'checked-in'));

		$invalid = $this->validateAttendanceInput(characterId: $characterId, status: $status);
		if ($invalid !== null) {
			return $invalid;
		}

		$event = $this->rosterService->getEvent(eventId: $id);
		if ($event === null) {
			return new JSONResponse(data: ['error' => 'Event not found'], statusCode: 404);
		}

		if ($this->rosterService->isParticipant(eventId: $id, characterId: $characterId, event: $event) === false) {
			return new JSONResponse(
				data: ['error' => 'Character is not a confirmed participant of this event'],
				statusCode: 422
			);
		}

		$saved = $this->rosterService->recordAttendance(
			eventId: $id,
			characterId: $characterId,
			status: $status,
			actingUid: $actingUid
		);

		if ($saved === null) {
			return new JSONResponse(
				data: ['error' => 'Attendance storage is not available', 'attendanceAvailable' => false],
				statusCode: 424
			);
		}

		return new JSONResponse(data: $saved);
	}//end recordAttendance()

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
	private function resolveGameMaster(): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return [
				'',
				new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED),
			];
		}

		$uid = $user->getUID();
		if ($this->isGameMaster(uid: $uid) === false) {
			return [
				'',
				new JSONResponse(data: ['error' => 'Access denied'], statusCode: Http::STATUS_FORBIDDEN),
			];
		}

		return [$uid, null];
	}//end resolveGameMaster()

	/**
	 * Validate the attendance request body, or null when it is acceptable.
	 *
	 * @param string $characterId The requested character UUID.
	 * @param string $status The requested attendance status.
	 *
	 * @return JSONResponse|null The 400 response, or null when valid.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	private function validateAttendanceInput(string $characterId, string $status): ?JSONResponse {
		if ($characterId === '') {
			return new JSONResponse(data: ['error' => 'A character is required'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		if (in_array($status, self::ATTENDANCE_STATUSES, true) === false) {
			return new JSONResponse(data: ['error' => 'Invalid attendance status'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		return null;
	}//end validateAttendanceInput()

	/**
	 * Derive the run-sheet download filename from the event.
	 *
	 * @param array<string,mixed> $event The event object.
	 *
	 * @return string The download filename.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	private function runsheetFileName(array $event): string {
		$eventName = (string)($event['name'] ?? '');
		if ($eventName === '') {
			$eventName = 'event';
		}

		return $eventName . '_runsheet.pdf';
	}//end runsheetFileName()

	/**
	 * Whether a user may act as a game master (GM group or Nextcloud admin).
	 *
	 * @param string $uid The acting user's uid.
	 *
	 * @return bool True when the user is a GM or admin.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	private function isGameMaster(string $uid): bool {
		return $this->groupManager->isInGroup($uid, self::GM_GROUP) === true
			|| $this->groupManager->isAdmin($uid) === true;
	}//end isGameMaster()
}//end class
