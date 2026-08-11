<?php

/**
 * DocuDeskPdfRenderer for LarpingApp
 *
 * Shared helper around DocuDesk's optional PdfService/TemplateService. Both the
 * character-sheet export (CharactersController::downloadPdf) and the event
 * run-sheet export (EventsController::downloadRunsheet) render PDFs through the
 * exact same pipeline: dependency check, UUID validation, template lookup,
 * render, with graceful 424 degradation when DocuDesk is absent.
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/pdf-export/spec.md
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Renders a PDF through DocuDesk for a given template and data context.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UndefinedClass, UndefinedDocblockClass DocuDesk is optional.
 *
 * @spec openspec/specs/pdf-export/spec.md
 */
class DocuDeskPdfRenderer
{
    /**
     * Constructor for DocuDeskPdfRenderer.
     *
     * @param IAppManager        $appManager The app manager (DocuDesk presence check).
     * @param ContainerInterface $container  The DI container (cross-app resolution).
     * @param LoggerInterface    $logger     The logger.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly IAppManager $appManager,
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger
    ) {
    }//end __construct()

    /**
     * Whether the DocuDesk app is installed and enabled for the current user.
     *
     * @return bool True when DocuDesk is available.
     *
     * @spec openspec/specs/pdf-export/spec.md
     */
    public function isDocuDeskAvailable(): bool
    {
        return $this->appManager->isEnabledForUser(appId: 'docudesk');
    }//end isDocuDeskAvailable()

    /**
     * Validate that a template id is a UUID (prevents path traversal/injection).
     *
     * @param string $template The candidate template id.
     *
     * @return string|null The lower-cased UUID, or null when not a UUID.
     *
     * @spec openspec/specs/pdf-export/spec.md
     */
    public function normaliseTemplateId(string $template): ?string
    {
        $templateLower = strtolower($template);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $templateLower) !== 1) {
            return null;
        }

        return $templateLower;
    }//end normaliseTemplateId()

    /**
     * Fetch a template definition from DocuDesk's TemplateService.
     *
     * @param string $templateId The UUID-validated template id.
     *
     * @return array<string,mixed>|null The template data, or null when not found.
     *
     * @psalm-suppress MixedMethodCall    DocuDesk is an optional cross-app dependency.
     * @psalm-suppress MixedReturnStatement DocuDesk is an optional cross-app dependency.
     * @psalm-suppress MixedInferredReturnType DocuDesk is an optional cross-app dependency.
     *
     * @spec openspec/specs/pdf-export/spec.md
     */
    public function getTemplate(string $templateId): ?array
    {
        try {
            // @var object $templateService
            $templateService = $this->container->get('OCA\DocuDesk\Service\TemplateService');

            // @var array<string,mixed> $templateData
            $templateData = $templateService->getTemplate($templateId);
            return $templateData;
        } catch (\Exception $exception) {
            return null;
        }
    }//end getTemplate()

    /**
     * Render a PDF for a template and data context via DocuDesk's PdfService.
     *
     * Returns the PDF bytes, or null on a render error (logged server-side; the
     * caller maps null to a generic 500 to avoid leaking DocuDesk internals).
     *
     * @param array<string,mixed> $templateData The template definition.
     * @param array<string,mixed> $context      The render data context.
     *
     * @return string|null The rendered PDF bytes, or null on failure.
     *
     * @psalm-suppress MixedMethodCall DocuDesk is an optional cross-app dependency.
     *
     * @spec openspec/specs/pdf-export/spec.md
     */
    public function render(array $templateData, array $context): ?string
    {
        try {
            // @var object $pdfService
            $pdfService = $this->container->get('OCA\DocuDesk\Service\PdfService');

            // @var string $pdfString
            $pdfString = $pdfService->renderPdf(
                (string) ($templateData['content'] ?? ''),
                $context,
                [
                    'format'      => (string) ($templateData['format'] ?? 'A4'),
                    'orientation' => (string) ($templateData['orientation'] ?? 'P'),
                ]
            );
            return $pdfString;
        } catch (\Exception $exception) {
            // Log full exception server-side; the caller returns a generic
            // message to avoid leaking DocuDesk internals (paths/traces).
            $this->logger->error('PDF generation failed', ['exception' => $exception]);
            return null;
        }
    }//end render()
}//end class
