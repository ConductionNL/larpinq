<?php

/**
 * Events controller for LarpingApp
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/event-runsheet-export/specs/pdf-export/spec.md
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\LarpingApp\Service\DocuDeskPdfRenderer;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
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
 * Controller for event-level operations — the GM run-sheet / cast-list export.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 * @spec openspec/changes/event-runsheet-export/specs/pdf-export/spec.md
 */
class EventsController extends Controller
{

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
     * @param string                $appName       The app name.
     * @param IRequest              $request       The request object.
     * @param RegisterObjectFetcher $objectFetcher The register object fetcher.
     * @param DocuDeskPdfRenderer   $pdfRenderer   The shared DocuDesk PDF helper.
     * @param IUserSession          $userSession   The user session.
     * @param IGroupManager         $groupManager  The group manager.
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly RegisterObjectFetcher $objectFetcher,
        private readonly DocuDeskPdfRenderer $pdfRenderer,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager
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
     * @param string $id       The event UUID.
     * @param string $template The run-sheet template UUID.
     *
     * @return DataDownloadResponse|JSONResponse The PDF download or an error.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     *
     * @spec openspec/changes/event-runsheet-export/specs/pdf-export/spec.md
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function downloadRunsheet(string $id, string $template): DataDownloadResponse|JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
        }

        // GM-only: the run-sheet exposes approval status and GM-private notes.
        $isGm = $this->groupManager->isInGroup($user->getUID(), self::GM_GROUP);
        if ($isGm === false && $this->groupManager->isAdmin($user->getUID()) === false) {
            return new JSONResponse(data: ['error' => 'Access denied'], statusCode: Http::STATUS_FORBIDDEN);
        }

        if ($this->pdfRenderer->isDocuDeskAvailable() === false) {
            return new JSONResponse(
                data: ['error' => 'PDF generation requires the DocuDesk app to be installed and enabled'],
                statusCode: 424
            );
        }

        $templateId = $this->pdfRenderer->normaliseTemplateId($template);
        if ($templateId === null) {
            return new JSONResponse(data: ['error' => 'Invalid template ID: expected a UUID'], statusCode: Http::STATUS_BAD_REQUEST);
        }

        try {
            $event = $this->objectFetcher->getObject(objectType: 'event', id: $id);
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'Event not found'], statusCode: 404);
        }

        $templateData = $this->pdfRenderer->getTemplate($templateId);
        if ($templateData === null) {
            return new JSONResponse(data: ['error' => 'Template not found'], statusCode: 404);
        }

        $context   = $this->buildRunsheetContext(event: $event, eventId: $id);
        $pdfString = $this->pdfRenderer->render(templateData: $templateData, context: $context);
        if ($pdfString === null) {
            return new JSONResponse(data: ['error' => 'PDF generation failed. Please contact your administrator.'], statusCode: 500);
        }

        $eventName = (string) ($event['name'] ?? '');
        if ($eventName === '') {
            $eventName = 'event';
        }

        $fileName = $eventName.'_runsheet.pdf';

        return new DataDownloadResponse($pdfString, $fileName, 'application/pdf');
    }//end downloadRunsheet()

    /**
     * Read the check-in roster for an event.
     *
     * Lists every confirmed participant (a character whose `events[]` references
     * this event) with the player name, character type and current attendance
     * status. Read access is open to any authenticated app user — the roster is
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
     * @SuppressWarnings(PHPMD.ShortVariable)
     *
     * @spec openspec/changes/event-checkin-roster/specs/event-checkin-roster/spec.md
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function roster(string $id): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
        }

        try {
            // Fetched to enforce existence + read access; a missing event 404s.
            $this->objectFetcher->getObject(objectType: 'event', id: $id);
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'Event not found'], statusCode: 404);
        }

        $isGm = $this->isGameMaster(uid: $user->getUID());

        [$attendance, $attendanceAvailable] = $this->loadAttendance(eventId: $id);
        $players      = $this->indexPlayers();
        $participants = [];

        try {
            $characters = $this->objectFetcher->getObjects('character');
        } catch (\Exception $exception) {
            $characters = [];
        }

        foreach ($characters as $character) {
            if (is_array($character) === false) {
                continue;
            }

            $events = $character['events'] ?? [];
            if (is_array($events) === false || in_array($id, array_map('strval', $events), true) === false) {
                continue;
            }

            $characterId = (string) ($character['id'] ?? '');
            $record      = $attendance[$characterId] ?? [];

            $participants[] = [
                'character'   => $characterId,
                'name'        => (string) ($character['name'] ?? ''),
                'type'        => (string) ($character['type'] ?? ''),
                'playerName'  => $this->resolvePlayerName(character: $character, players: $players),
                'status'      => (string) ($record['status'] ?? 'registered'),
                'checkedInAt' => (string) ($record['checkedInAt'] ?? ''),
                'checkedInBy' => (string) ($record['checkedInBy'] ?? ''),
            ];
        }//end foreach

        usort(
            $participants,
            static function (array $a, array $b): int {
                return strcasecmp($a['name'], $b['name']);
            }
        );

        return new JSONResponse(
            data: [
                'participants'        => $participants,
                'attendanceAvailable' => $attendanceAvailable,
                'isGm'                => $isGm,
            ]
        );
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
     * @SuppressWarnings(PHPMD.ShortVariable)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     *
     * @spec openspec/changes/event-checkin-roster/specs/event-checkin-roster/spec.md
     */
    #[NoAdminRequired]
    public function recordAttendance(string $id): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
        }

        // GM-only: recording attendance is a game-master act at the door.
        if ($this->isGameMaster(uid: $user->getUID()) === false) {
            return new JSONResponse(data: ['error' => 'Access denied'], statusCode: Http::STATUS_FORBIDDEN);
        }

        $characterId = (string) ($this->request->getParam('character', ''));
        $status      = (string) ($this->request->getParam('status', 'checked-in'));

        if ($characterId === '') {
            return new JSONResponse(data: ['error' => 'A character is required'], statusCode: Http::STATUS_BAD_REQUEST);
        }

        if (in_array($status, self::ATTENDANCE_STATUSES, true) === false) {
            return new JSONResponse(data: ['error' => 'Invalid attendance status'], statusCode: Http::STATUS_BAD_REQUEST);
        }

        try {
            $event = $this->objectFetcher->getObject(objectType: 'event', id: $id);
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'Event not found'], statusCode: 404);
        }

        if ($this->isParticipant(eventId: $id, characterId: $characterId, event: $event) === false) {
            return new JSONResponse(
                data: ['error' => 'Character is not a confirmed participant of this event'],
                statusCode: 422
            );
        }

        // Server-stamped provenance — any client-supplied checkedInAt/checkedInBy
        // in the body is discarded here (never read).
        $payload = [
            'event'       => $id,
            'character'   => $characterId,
            'status'      => $status,
            'checkedInAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'checkedInBy' => $user->getUID(),
        ];

        try {
            [$existing, $available] = $this->loadAttendance(eventId: $id);
            if ($available === false) {
                return new JSONResponse(
                    data: ['error' => 'Attendance storage is not available', 'attendanceAvailable' => false],
                    statusCode: 424
                );
            }

            $uuid  = null;
            $prior = $existing[$characterId] ?? null;
            if (is_array($prior) === true && isset($prior['id']) === true) {
                $uuid = (string) $prior['id'];
            }

            $saved = $this->objectFetcher->saveObject(objectType: 'attendance', data: $payload, uuid: $uuid);
        } catch (\Exception $exception) {
            return new JSONResponse(
                data: ['error' => 'Attendance storage is not available', 'attendanceAvailable' => false],
                statusCode: 424
            );
        }//end try

        return new JSONResponse(data: $saved);
    }//end recordAttendance()

    /**
     * Build the run-sheet render context: event header + cast list.
     *
     * The cast is every character whose `events[]` references this event, sorted
     * by character name. Each cast entry carries the character name, type,
     * approval status, linked player name (when available), stored computed
     * stats (no recalculation here), condition/item references, and GM notes.
     *
     * @param array<string,mixed> $event   The event object.
     * @param string              $eventId The event UUID (for the membership test).
     *
     * @return array<string,mixed> The render context.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     *
     * @spec openspec/changes/event-runsheet-export/specs/pdf-export/spec.md
     */
    private function buildRunsheetContext(array $event, string $eventId): array
    {
        $characters = [];
        try {
            $characters = $this->objectFetcher->getObjects('character');
        } catch (\Exception $exception) {
            $characters = [];
        }

        $players = $this->indexPlayers();

        // Attendance is additive: when the schema is absent the cast list is
        // unchanged (each entry's attendanceStatus stays empty).
        [$attendance] = $this->loadAttendance(eventId: $eventId);

        $cast        = [];
        $uniqueItems = [];
        foreach ($characters as $character) {
            if (is_array($character) === false) {
                continue;
            }

            $events = $character['events'] ?? [];
            if (is_array($events) === false || in_array($eventId, array_map('strval', $events), true) === false) {
                continue;
            }

            // OcName carries the player's name as fallback in this data model.
            $playerName = (string) ($character['ocName'] ?? '');
            $playerId   = (string) ($character['player'] ?? ($character['ocName'] ?? ''));
            if ($playerId !== '' && isset($players[$playerId]) === true) {
                $playerName = (string) ($players[$playerId]['name'] ?? '');
            }

            $attendanceStatus = (string) (($attendance[(string) ($character['id'] ?? '')] ?? [])['status'] ?? '');

            $cast[] = [
                'name'             => (string) ($character['name'] ?? ''),
                'type'             => (string) ($character['type'] ?? ''),
                'approved'         => (string) ($character['approved'] ?? ''),
                'playerName'       => $playerName,
                'stats'            => ($character['stats'] ?? []),
                'conditions'       => ($character['conditions'] ?? []),
                'items'            => ($character['items'] ?? []),
                'attendanceStatus' => $attendanceStatus,
                'slNotesPublic'    => (string) ($character['slNotesPublic'] ?? ''),
                'slNotesPrivate'   => (string) ($character['slNotesPrivate'] ?? ''),
            ];

            $items = $character['items'] ?? [];
            if (is_array($items) === true) {
                foreach ($items as $itemId) {
                    $uniqueItems[(string) $itemId] = true;
                }
            }
        }//end foreach

        usort(
            $cast,
            static function (array $a, array $b): int {
                return strcasecmp($a['name'], $b['name']);
            }
        );

        return [
            'event'             => [
                'name'        => (string) ($event['name'] ?? ''),
                'description' => (string) ($event['description'] ?? ''),
                'startDate'   => (string) ($event['startDate'] ?? ''),
                'endDate'     => (string) ($event['endDate'] ?? ''),
                'location'    => (string) ($event['location'] ?? ''),
                'effects'     => ($event['effects'] ?? []),
                'castCount'   => count($cast),
            ],
            'cast'              => $cast,
            'castCount'         => count($cast),
            'uniqueItemsInPlay' => array_keys($uniqueItems),
            'template'          => [],
        ];
    }//end buildRunsheetContext()

    /**
     * Index players by their id for cast player-name resolution.
     *
     * @return array<string,array<string,mixed>> Players indexed by id.
     */
    private function indexPlayers(): array
    {
        $indexed = [];
        try {
            $players = $this->objectFetcher->getObjects('player');
        } catch (\Exception $exception) {
            return $indexed;
        }

        foreach ($players as $player) {
            if (is_array($player) === true && isset($player['id']) === true) {
                $indexed[(string) $player['id']] = $player;
            }
        }

        return $indexed;
    }//end indexPlayers()

    /**
     * Whether a user may act as a game master (GM group or Nextcloud admin).
     *
     * @param string $uid The acting user's uid.
     *
     * @return bool True when the user is a GM or admin.
     *
     * @spec openspec/changes/event-checkin-roster/specs/event-checkin-roster/spec.md
     */
    private function isGameMaster(string $uid): bool
    {
        return $this->groupManager->isInGroup($uid, self::GM_GROUP) === true
            || $this->groupManager->isAdmin($uid) === true;
    }//end isGameMaster()

    /**
     * Load the attendance records for an event, indexed by character id.
     *
     * Degrades gracefully: when the `larping_attendance` schema (or OpenRegister)
     * is unavailable this returns an empty map and `false` availability rather
     * than throwing, so both the roster read and the run-sheet keep working.
     *
     * @param string $eventId The event UUID.
     *
     * @return array{0: array<string,array<string,mixed>>, 1: bool} The [records-by-character, available] tuple.
     *
     * @spec openspec/changes/event-checkin-roster/specs/event-checkin-roster/spec.md
     */
    private function loadAttendance(string $eventId): array
    {
        try {
            $records = $this->objectFetcher->getObjects(
                objectType: 'attendance',
                filters: ['event' => $eventId]
            );
        } catch (\Exception $exception) {
            return [[], false];
        }

        $byCharacter = [];
        foreach ($records as $record) {
            if (is_array($record) === false) {
                continue;
            }

            $characterId = (string) ($record['character'] ?? '');
            if ($characterId !== '') {
                $byCharacter[$characterId] = $record;
            }
        }

        return [$byCharacter, true];
    }//end loadAttendance()

    /**
     * Whether a character is a confirmed participant of an event.
     *
     * A participant is a character present in the Event `players[]` OR one whose
     * `character.events[]` references the event. A GM cannot check in a
     * character that is not part of the event.
     *
     * @param string              $eventId     The event UUID.
     * @param string              $characterId The character UUID.
     * @param array<string,mixed> $event       The event object.
     *
     * @return bool True when the character participates in the event.
     *
     * @spec openspec/changes/event-checkin-roster/specs/event-checkin-roster/spec.md
     */
    private function isParticipant(string $eventId, string $characterId, array $event): bool
    {
        $players = $event['players'] ?? [];
        if (is_array($players) === true && in_array($characterId, array_map('strval', $players), true) === true) {
            return true;
        }

        try {
            $character = $this->objectFetcher->getObject(objectType: 'character', id: $characterId);
        } catch (\Exception $exception) {
            return false;
        }

        $events = $character['events'] ?? [];
        return is_array($events) === true
            && in_array($eventId, array_map('strval', $events), true) === true;
    }//end isParticipant()

    /**
     * Resolve a character's player display name.
     *
     * Falls back to the character's `ocName` (the player's name in this data
     * model) when the linked player object cannot be resolved.
     *
     * @param array<string,mixed>               $character The character object.
     * @param array<string,array<string,mixed>> $players   Players indexed by id.
     *
     * @return string The player display name.
     *
     * @spec openspec/changes/event-checkin-roster/specs/event-checkin-roster/spec.md
     */
    private function resolvePlayerName(array $character, array $players): string
    {
        $playerName = (string) ($character['ocName'] ?? '');
        $playerId   = (string) ($character['player'] ?? ($character['ocName'] ?? ''));
        if ($playerId !== '' && isset($players[$playerId]) === true) {
            $playerName = (string) ($players[$playerId]['name'] ?? '');
        }

        return $playerName;
    }//end resolvePlayerName()
}//end class
