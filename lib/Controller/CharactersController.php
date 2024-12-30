<?php

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Service\CharacterService;
use OCA\LarpingApp\Db\Character;
use OCA\LarpingApp\Db\CharacterMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IAppConfig;
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
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Character Not Found'], statusCode: 404);
        } 
            
        // Generate PDF using the specified template
        $pdfContent = $this->characterService->createCharacterPdf($character, $template);
        
        // Other code
        $pdfContent->Output();
        
        //return new DataDownloadResponse(
        //    $pdfContent,
        //    $character->getName() . '_character_sheet.pdf',
        //    'application/pdf'
        //);
    }
}