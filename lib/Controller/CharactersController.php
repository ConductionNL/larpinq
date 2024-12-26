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
     * Downloads a character PDF
     * 
     * This method generates and downloads a PDF for a specific character.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the character to download as PDF
     * @return DataDownloadResponse|JSONResponse A response containing the PDF file for download or an error response
     */
    public function downloadPdf(int $id): DataDownloadResponse|JSONResponse
    {
        try {
            // Fetch the catalog object by its ID
            $character = $this->objectService->getObject(self::objectType, $id);
            $pdfContent = $this->characterService->createCharacterPdf($character);
            
            return new DataDownloadResponse(
                $pdfContent,
                $character->getName() . '_character_sheet.pdf',
                'application/pdf'
            );
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Character Not Found'], statusCode: 404);
        } catch (\Exception $exception) {
            return new JSONResponse(data: ['error' => 'PDF Generation Failed'], statusCode: 500);
        }
    }
}