<?php

/**
 * Characters controller for Larpinq
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @category  Controller
 * @package   OCA\Larpinq\Controller
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

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\Service\DocuDeskPdfRenderer;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\SkillRequirementService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Controller for handling characters related operations
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-80
 */
class CharactersController extends Controller {
	/**
	 * Constructor for the CharactersController
	 *
	 * CharacterService is intentionally not injected here. The service would
	 * call loadAllEntities() (6 full OR queries) on construction, but
	 * downloadPdf does not call calculateCharacter(). Removing the dep avoids
	 * those unnecessary queries on every PDF request. Closes #211.
	 *
	 * @param string $appName The name of the app
	 * @param IRequest $request The request object
	 * @param RegisterObjectFetcher $objectFetcher The register object fetcher
	 * @param DocuDeskPdfRenderer $pdfRenderer The shared DocuDesk PDF rendering helper
	 * @param CallerAccess $callerAccess The signed-in caller and the admin check
	 * @param SkillRequirementService $requirementService The skill-requirement validation service
	 */
	public function __construct(
		$appName,
		IRequest $request,
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly DocuDeskPdfRenderer $pdfRenderer,
		private readonly CallerAccess $callerAccess,
		private readonly SkillRequirementService $requirementService,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Downloads a character PDF using a specific template.
	 *
	 * Access is the admin / game-master / owner matrix: a Nextcloud admin, a
	 * member of the game-master group, or the player whose uid is the
	 * character's `ownerUid`. The character is fetched before that decision,
	 * and a caller who is none of the three gets 403 whether or not the id
	 * exists, so the id cannot be probed (the #205 contract). A sheet rendered
	 * for its owner leaves out the GM-private notes (`slNotesPrivate`).
	 *
	 * DocuDesk availability, template-id validation, template lookup and the
	 * render itself are all delegated to the shared DocuDeskPdfRenderer — the
	 * same helper the event run-sheet export uses. Returns 424 if DocuDesk
	 * is not installed.
	 *
	 * @param string $id The ID of the character to download as PDF
	 * @param string $template The ID of the template to use for PDF generation
	 *
	 * @return DataDownloadResponse|JSONResponse A response containing the PDF file for download or an error response
	 *
	 * `@NoAdminRequired` because a player may download their own sheet; the
	 * per-object guard is canAccessCharacter() in sheetFor(), after the fetch.
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
	 * @spec openspec/changes/player-character-sheet-access/specs/pdf-export/spec.md
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function downloadPdf(string $id, string $template): DataDownloadResponse|JSONResponse {
		$user = $this->callerAccess->user();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		// Access is admin / game master / owner, decided in sheetFor() once the
		// character is fetched (closes #205: never a 404 for a non-owner).
		return $this->renderCharacterSheet(id: $id, template: $template, uid: $user->getUID());
	}//end downloadPdf()

	/**
	 * Render the sheet of a character: check the template id, then DocuDesk.
	 *
	 * @param string $id       The character UUID.
	 * @param string $template The template id as requested.
	 * @param string $uid      The caller.
	 *
	 * @return DataDownloadResponse|JSONResponse The PDF download or an error.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	private function renderCharacterSheet(string $id, string $template, string $uid): DataDownloadResponse|JSONResponse {
		// Validate the template ID to a UUID before delegating to DocuDesk,
		// preventing path-traversal or injection via a crafted template value.
		//
		// This runs BEFORE the DocuDesk availability probe on purpose. Input
		// validation is a property of the REQUEST and must not depend on which
		// optional apps happen to be installed: a malformed template id is a
		// 400 whether or not DocuDesk is present. With the order reversed, an
		// instance without DocuDesk answered 424 to every request, so a
		// crafted template value was never rejected on its own merits and the
		// endpoint's documented 400 contract was unreachable — which is
		// precisely what the Newman assertion "invalid (non-UUID) template ->
		// 400 not 500" caught on CI, where DocuDesk is not installed.
		$templateId = $this->pdfRenderer->normaliseTemplateId($template);
		if ($templateId === null) {
			return new JSONResponse(data: ['error' => 'Invalid template ID: expected a UUID'], statusCode: Http::STATUS_BAD_REQUEST);
		}

		if ($this->pdfRenderer->isDocuDeskAvailable() === false) {
			return new JSONResponse(data: ['error' => 'PDF generation requires the DocuDesk app to be installed and enabled'], statusCode: 424);
		}

		return $this->sheetFor(id: $id, templateId: $templateId, uid: $uid);
	}//end renderCharacterSheet()

	/**
	 * Render the sheet of a character with a validated template, once DocuDesk is known to be there.
	 *
	 * The character is fetched first and the caller authorised second. A caller
	 * who is not an admin or game master gets 403 for a missing character as
	 * for one they do not own, so the answer says nothing about which ids exist.
	 *
	 * @param string $id         The character UUID.
	 * @param string $templateId The normalised template UUID.
	 * @param string $uid        The caller.
	 *
	 * @return DataDownloadResponse|JSONResponse The PDF download or an error.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 * @spec openspec/changes/player-character-sheet-access/specs/pdf-export/spec.md
	 */
	private function sheetFor(string $id, string $templateId, string $uid): DataDownloadResponse|JSONResponse {
		$character = $this->readCharacter(id: $id);
		$isGameMaster = $this->callerAccess->isGameMasterOrAdmin(uid: $uid);
		if ($character === null && $isGameMaster === true) {
			return new JSONResponse(data: ['error' => 'Character not found'], statusCode: 404);
		}

		if ($character === null) {
			return new JSONResponse(data: ['error' => 'Access denied'], statusCode: Http::STATUS_FORBIDDEN);
		}

		if ($this->canAccessCharacter(character: $character, uid: $uid, isGameMaster: $isGameMaster) === false) {
			return new JSONResponse(data: ['error' => 'Access denied'], statusCode: Http::STATUS_FORBIDDEN);
		}

		// The owner's own sheet never carries the GM-private notes, whatever the template prints.
		if ($isGameMaster === false) {
			$character = self::redactGmPrivateFields(character: $character);
		}

		$templateData = $this->pdfRenderer->getTemplate($templateId);
		if ($templateData === null) {
			return new JSONResponse(data: ['error' => 'Template not found'], statusCode: 404);
		}

		// The renderer logs the full exception server-side and returns null; the
		// generic user-facing message avoids leaking DocuDesk internals — closes #218.
		$pdfString = $this->pdfRenderer->render(templateData: $templateData, context: ['character' => $character, 'template' => $templateData]);

		return $this->characterSheetFile(pdfString: $pdfString, character: $character);
	}//end sheetFor()

	/**
	 * Whether the caller may download this character's sheet: an admin or game
	 * master, or the player whose uid is the character's `ownerUid`. An empty
	 * owner matches nobody.
	 *
	 * @param array<string,mixed> $character    The character.
	 * @param string              $uid          The caller.
	 * @param bool                $isGameMaster Whether the caller is an admin or game master.
	 *
	 * @return bool True when the caller may download it.
	 *
	 * @spec openspec/changes/player-character-sheet-access/specs/pdf-export/spec.md
	 */
	private function canAccessCharacter(array $character, string $uid, bool $isGameMaster): bool {
		if ($isGameMaster === true) {
			return true;
		}

		$owner = $character['ownerUid'] ?? '';

		return is_string($owner) === true && $owner !== '' && $owner === $uid;
	}//end canAccessCharacter()

	/**
	 * The character without the notes only game masters may read.
	 *
	 * `slNotesPublic` stays: it is the player-facing note by schema design.
	 *
	 * @param array<string,mixed> $character The character.
	 *
	 * @return array<string,mixed> The character without `slNotesPrivate`.
	 *
	 * @spec openspec/changes/player-character-sheet-access/specs/pdf-export/spec.md
	 */
	private static function redactGmPrivateFields(array $character): array {
		unset($character['slNotesPrivate']);

		return $character;
	}//end redactGmPrivateFields()

	/**
	 * The character, or null when OpenRegister cannot give it.
	 *
	 * OpenRegister signals an absent or unreadable object with an exception
	 * (DoesNotExistException among them); either way it is a 404, never a 500.
	 *
	 * @param string $id The character UUID.
	 *
	 * @return array<string,mixed>|null The character.
	 */
	private function readCharacter(string $id): ?array {
		try {
			return $this->objectFetcher->getObject(objectType: 'character', id: $id);
		} catch (\Exception $exception) {
			return null;
		}
	}//end readCharacter()

	/**
	 * The sheet download, or the 500 when rendering failed.
	 *
	 * @param string|null         $pdfString The rendered PDF, or null.
	 * @param array<string,mixed> $character The character.
	 *
	 * @return DataDownloadResponse|JSONResponse The PDF download or the 500.
	 */
	private function characterSheetFile(?string $pdfString, array $character): DataDownloadResponse|JSONResponse {
		if ($pdfString === null) {
			return new JSONResponse(data: ['error' => 'PDF generation failed. Please contact your administrator.'], statusCode: 500);
		}

		$fileName = ((string)($character['name'] ?? 'character')) . '_character_sheet.pdf';

		return new DataDownloadResponse($pdfString, $fileName, 'application/pdf');
	}//end characterSheetFile()

	/**
	 * The character-sheet templates for the Download as PDF dialog.
	 *
	 * Administrator only. The header action reads this endpoint as its
	 * visibility predicate, so the action is shown to administrators only, and
	 * hidden when the document app is absent (`available: false`).
	 * downloadPdf() itself also serves game masters and a character's owner
	 * (player-character-sheet-access); showing them the action is a separate
	 * decision about this listing, not made here.
	 *
	 * Not `@NoAdminRequired`.
	 * `#[NoCSRFRequired]` because it is a read-only GET that the header
	 * action's visibility probe fetches, like downloadPdf's GET.
	 *
	 * @return JSONResponse `{available: bool, templates: [{id, name}]}`.
	 *
	 * @spec openspec/specs/pdf-export/spec.md
	 */
	#[NoCSRFRequired]
	public function pdfTemplates(): JSONResponse {
		if ($this->pdfRenderer->isDocuDeskAvailable() === false) {
			return new JSONResponse(data: ['available' => false, 'templates' => []]);
		}

		return new JSONResponse(data: ['available' => true, 'templates' => $this->pdfRenderer->listTemplates()]);
	}//end pdfTemplates()

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
	 * @spec openspec/specs/skill-requirement-enforcement/spec.md
	 *
	 * @no-admin-idor-exempt OR-delegated read via
	 * RegisterObjectFetcher::getObject (ADR-022). The fetch enforces read
	 * access for the current user, and an id the caller may not read raises
	 * DoesNotExistException, which this method translates to 404 — so an
	 * out-of-scope id is indistinguishable from a missing one and nothing is
	 * exposed that the caller could not already read through the OR objects
	 * API.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function requirementReport(string $id): JSONResponse {
		$user = $this->callerAccess->user();
		if ($user === null) {
			return new JSONResponse(data: ['error' => 'Not authenticated'], statusCode: Http::STATUS_UNAUTHORIZED);
		}

		try {
			// Per-object access is OR-delegated: the fetch enforces read access
			// for the current user; a non-readable id yields a not-found. The
			// gate's exemption tag for this lives in the DOCBLOCK above — gate-7
			// reads the docblock, so a tag written down here was invisible to it
			// and the endpoint was reported as unguarded for as long as it sat
			// in the body.
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
