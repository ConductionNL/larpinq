<?php
/**
 * Characters controller for LarpingApp
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Controller;

use OCA\LarpingApp\Service\RegisterObjectFetcher;
use OCA\LarpingApp\Service\CharacterService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IRequest;
use Psr\Container\ContainerInterface;

/**
 * Controller for handling characters related operations
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 */
class CharactersController extends Controller
{
    /**
     * Constructor for the CharactersController
     *
     * @param string                $appName          The name of the app
     * @param IRequest              $request          The request object
     * @param RegisterObjectFetcher $objectFetcher    The register object fetcher
     * @param CharacterService      $characterService The character service object
     * @param IAppManager           $appManager       The app manager for checking installed apps
     * @param ContainerInterface    $container        The DI container for resolving cross-app services
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly RegisterObjectFetcher $objectFetcher,
        private readonly CharacterService $characterService,
        private readonly IAppManager $appManager,
        private readonly ContainerInterface $container
    ) {
        parent::__construct(appName: $appName, request: $request);
    }//end __construct()

    /**
     * Downloads a character PDF using a specific template
     *
     * Delegates PDF generation to DocuDesk's PdfService and template
     * lookup to DocuDesk's TemplateService. Returns 424 if DocuDesk
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
     */
    public function downloadPdf(string $id, string $template): DataDownloadResponse|JSONResponse
    {
        if ($this->appManager->isEnabledForUser(appId: 'docudesk') === false) {
            return new JSONResponse(
                data: ['error' => 'PDF generation requires the DocuDesk app to be installed and enabled'],
                statusCode: 424
            );
        }

        try {
            $character = $this->objectFetcher->getObject(objectType: 'character', id: $id);
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'Character not found'], statusCode: 404);
        }

        try {
            // @var object $templateService
            $templateService = $this->container->get('OCA\DocuDesk\Service\TemplateService');

            // @psalm-suppress MixedMethodCall DocuDesk is an optional cross-app dependency.
            // @var array<string,mixed> $templateData
            $templateData = $templateService->getTemplate(id: $template);
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'Template not found'], statusCode: 404);
        }

        try {
            // @var object $pdfService
            $pdfService = $this->container->get('OCA\DocuDesk\Service\PdfService');

            // @psalm-suppress MixedMethodCall DocuDesk is an optional cross-app dependency.
            // @var string $pdfString
            $pdfString = $pdfService->renderPdf(
                templateContent: (string) ($templateData['content'] ?? ''),
                data: ['character' => $character, 'template' => $templateData],
                options: [
                    'format'      => (string) ($templateData['format'] ?? 'A4'),
                    'orientation' => (string) ($templateData['orientation'] ?? 'P'),
                ]
            );
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'PDF generation failed: '.$exception->getMessage()], statusCode: 500);
        }

        $fileName = ((string) ($character['name'] ?? 'character')).'_character_sheet.pdf';

        return new DataDownloadResponse(
            $pdfString,
            $fileName,
            'application/pdf'
        );
    }//end downloadPdf()
}//end class
