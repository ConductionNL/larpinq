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

            $playerName = '';
            $playerId   = (string) ($character['player'] ?? ($character['ocName'] ?? ''));
            if ($playerId !== '' && isset($players[$playerId]) === true) {
                $playerName = (string) ($players[$playerId]['name'] ?? '');
            } else {
                // OcName already carries the player's name in this data model.
                $playerName = (string) ($character['ocName'] ?? '');
            }

            $cast[] = [
                'name'           => (string) ($character['name'] ?? ''),
                'type'           => (string) ($character['type'] ?? ''),
                'approved'       => (string) ($character['approved'] ?? ''),
                'playerName'     => $playerName,
                'stats'          => ($character['stats'] ?? []),
                'conditions'     => ($character['conditions'] ?? []),
                'items'          => ($character['items'] ?? []),
                'slNotesPublic'  => (string) ($character['slNotesPublic'] ?? ''),
                'slNotesPrivate' => (string) ($character['slNotesPrivate'] ?? ''),
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
}//end class
