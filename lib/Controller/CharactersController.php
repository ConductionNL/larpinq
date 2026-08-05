<?php
/**
 * Characters controller for LarpingApp
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-80
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-81
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-82
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-83
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-84
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-85
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-86
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-87
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-88
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-89
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-90
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-91
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-92
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-93
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-94
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-95
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-96
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-97
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Controller;

use OCA\LarpingApp\Service\DocuDeskPdfRenderer;
use OCA\LarpingApp\Service\RegisterObjectFetcher;
use OCA\LarpingApp\Service\SkillRequirementService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Controller for handling characters related operations
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-80
 */
class CharactersController extends Controller
{
    /**
     * Constructor for the CharactersController
     *
     * CharacterService is intentionally not injected here. The service would
     * call loadAllEntities() (6 full OR queries) on construction, but
     * downloadPdf does not call calculateCharacter(). Removing the dep avoids
     * those unnecessary queries on every PDF request. Closes #211.
     *
     * @param string                  $appName            The name of the app
     * @param IRequest                $request            The request object
     * @param RegisterObjectFetcher   $objectFetcher      The register object fetcher
     * @param DocuDeskPdfRenderer     $pdfRenderer        The shared DocuDesk PDF rendering helper
     * @param IUserSession            $userSession        The user session for authentication checks
     * @param IGroupManager           $groupManager       The group manager for permission checks
     * @param SkillRequirementService $requirementService The skill-requirement validation service
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly RegisterObjectFetcher $objectFetcher,
        private readonly DocuDeskPdfRenderer $pdfRenderer,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
        private readonly SkillRequirementService $requirementService
    ) {
        parent::__construct(appName: $appName, request: $request);
    }//end __construct()

    /**
     * Downloads a character PDF using a specific template.
     *
     * Only administrators (GMs) may download character sheets. Per-player access
     * requires the character schema to gain a `player` ownership field — tracked
     * as a follow-up. This guard prevents unauthenticated users and non-admin
     * NC users from reading GM-private notes (closes #205).
     *
     * DocuDesk availability, template-id validation, template lookup and the
     * render itself are all delegated to the shared DocuDeskPdfRenderer — the
     * same helper the event run-sheet export uses. Returns 424 if DocuDesk
     * is not installed.
     *
     * @param string $id       The ID of the character to download as PDF
     * @param string $template The ID of the template to use for PDF generation
     *
     * @return DataDownloadResponse|JSONResponse A response containing the PDF file for download or an error response
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-80
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-81
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-82
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-83
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-84
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-85
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-86
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-87
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-88
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-89
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-90
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-91
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-92
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-93
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-94
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-95
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-96
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-97
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function downloadPdf(string $id, string $template): DataDownloadResponse|JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
        }

        // Only admins (GMs) may download character sheets that include GM-private fields.
        // Per-player self-access requires the character schema to include a `player` ownership
        // field so the controller can verify ownership — that is a follow-up schema change.
        // Closes #205 (Character PDF IDOR).
        if ($this->groupManager->isAdmin($user->getUID()) === false) {
            return new JSONResponse(data: ['error' => 'Access denied'], statusCode: Http::STATUS_FORBIDDEN);
        }

        if ($this->pdfRenderer->isDocuDeskAvailable() === false) {
            return new JSONResponse(
                data: ['error' => 'PDF generation requires the DocuDesk app to be installed and enabled'],
                statusCode: 424
            );
        }

        // Validate the template ID to a UUID before delegating to DocuDesk,
        // preventing path-traversal or injection via a crafted template value.
        $templateId = $this->pdfRenderer->normaliseTemplateId($template);
        if ($templateId === null) {
            return new JSONResponse(data: ['error' => 'Invalid template ID: expected a UUID'], statusCode: Http::STATUS_BAD_REQUEST);
        }

        try {
            $character = $this->objectFetcher->getObject(objectType: 'character', id: $id);
        } catch (DoesNotExistException $exception) {
            // OpenRegister signals an absent/unreadable object with this exception;
            // translate it rather than letting it surface as a 500.
            return new JSONResponse(data: ['error' => 'Character not found'], statusCode: 404);
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'Character not found'], statusCode: 404);
        }

        $templateData = $this->pdfRenderer->getTemplate($templateId);
        if ($templateData === null) {
            return new JSONResponse(data: ['error' => 'Template not found'], statusCode: 404);
        }

        // The renderer logs the full exception server-side and returns null; the
        // generic user-facing message avoids leaking DocuDesk internals — closes #218.
        $pdfString = $this->pdfRenderer->render(
            templateData: $templateData,
            context: ['character' => $character, 'template' => $templateData]
        );
        if ($pdfString === null) {
            return new JSONResponse(data: ['error' => 'PDF generation failed. Please contact your administrator.'], statusCode: 500);
        }

        $fileName = ((string) ($character['name'] ?? 'character')).'_character_sheet.pdf';

        return new DataDownloadResponse(
            $pdfString,
            $fileName,
            'application/pdf'
        );
    }//end downloadPdf()

    /**
     * Recompute the skill-requirement report for one character on demand.
     *
     * Returns the structured validation result (unmet prerequisites, overridden
     * entries, dependent-now-unmet skills from past removals/global edits, the
     * XP budget block). Read-only: it never writes. Authorization is delegated
     * to OpenRegister via the fetch — a user who cannot read the character via
     * the OR-backed fetch gets a 404, so this endpoint exposes nothing the
     * caller could not already read through the OR objects API.
     *
     * @param string $id The character UUID.
     *
     * @return JSONResponse The requirement report, or an error response.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     *
     * @spec openspec/changes/skill-requirement-enforcement/specs/skill-requirement-enforcement/spec.md
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function requirementReport(string $id): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
        }

        try {
            // Per-object access is OR-delegated: the fetch enforces read access
            // for the current user; a non-readable id yields a not-found.
            // @no-admin-idor-exempt OR-delegated read via RegisterObjectFetcher::getObject (ADR-022).
            $character = $this->objectFetcher->getObject(objectType: 'character', id: $id);
        } catch (DoesNotExistException $exception) {
            // OpenRegister signals an absent/unreadable object with this exception;
            // translate it rather than letting it surface as a 500.
            return new JSONResponse(data: ['error' => 'Character not found'], statusCode: 404);
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'Character not found'], statusCode: 404);
        }

        // Treat the persisted state as both old and candidate: this surfaces
        // dependents and budget drift for the character as it currently stands.
        $report = $this->requirementService->validate(candidate: $character, oldCharacter: $character);

        return new JSONResponse(data: $report);
    }//end requirementReport()
}//end class
