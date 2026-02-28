<?php

namespace OCA\LarpingApp\Controller;

use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\CharacterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IRequest;

/**
 * Controller for handling characters related operations
 */
class CharactersController extends Controller
{
    const objectType = 'character';

    /**
     * Constructor for the CharactersController
     *
     * @param string $appName The name of the app
     * @param IRequest $request The request object
     * @param ObjectService $objectService The object service object
     * @param CharacterService $characterService The character service object
     */
    public function __construct(
        $appName,
        IRequest $request,
		private readonly ObjectService $objectService,
        private readonly CharacterService $characterService
    )
    {
        parent::__construct($appName, $request);
    }
    
    /**
     * Downloads a character PDF using a specific template
     * 
     * This method generates and downloads a PDF for a specific character using the provided template.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the character to download as PDF
     * @param string $template The ID of the template to use for PDF generation
     * @return DataDownloadResponse|JSONResponse A response containing the PDF file for download or an error response
     */
    public function downloadPdf(string $id, string $template): DataDownloadResponse|JSONResponse
    {
        try {
            // Fetch the character object by its ID
            $character = $this->objectService->getObject('character', $id);
            $template  = $this->objectService->getObject('template', $template);
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'Character or template not found'], statusCode: 404);
        }

        // Generate PDF using the specified template
        $pdfContent = $this->characterService->createCharacterPdf($character, $template);

        // Get PDF as string and return via Nextcloud response framework
        $pdfString = $pdfContent->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        $fileName = ($character['name'] ?? 'character') . '_character_sheet.pdf';

        return new DataDownloadResponse(
            $pdfString,
            $fileName,
            'application/pdf'
        );
    }
}