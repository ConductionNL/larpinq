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
    /**
     * Constructor for the CharactersController
     *
     * @param string $appName The name of the app
     * @param IRequest $request The request object
     * @param IAppConfig $config The app configuration object
     * @param CharacterMapper $characterMapper The character mapper object
     * @param CharacterService $characterService The character service object
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly IAppConfig $config,
        private readonly CharacterMapper $characterMapper,
        private readonly CharacterService $characterService
    )
    {
        parent::__construct($appName, $request);
    }

    /**
     * Returns the template of the main app's page
     * 
     * This method renders the main page of the application, adding any necessary data to the template.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return TemplateResponse The rendered template response
     */
    public function page(): TemplateResponse
    {           
        return new TemplateResponse(
            'larpingapp',
            'index',
            []
        );
    }
    
    /**
     * Retrieves a list of all characters
     * 
     * This method returns a JSON response containing an array of all characters in the system.
     * Currently, it uses a test array instead of fetching from a database.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of characters
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch:  $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->characterMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);

    }

    /**
     * Retrieves a single character by its ID
     * 
     * This method returns a JSON response containing the details of a specific character.
     * Currently, it fetches the character from a test array instead of a database.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the character to retrieve
     * @return JSONResponse A JSON response containing the character details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->characterMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new character
     * 
     * This method creates a new character based on POST data and calculates its attributes using the CharacterService.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the newly created character
     */
    public function create(): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        
        if (isset($data['id'])) {
            unset($data['id']);
        }
        
        $character = $this->characterMapper->createFromArray(object: $data);
        $calculatedCharacter = $this->characterService->calculateCharacter($character);
        
        return new JSONResponse($calculatedCharacter);
    }

    /**
     * Updates an existing character
     * 
     * This method updates an existing character based on its ID and recalculates its attributes using the CharacterService.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the character to update
     * @return JSONResponse A JSON response containing the updated character details
     */
    public function update(int $id): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        if (isset($data['id'])) {
            unset($data['id']);
        }
        $updatedCharacter = $this->characterMapper->updateFromArray(id: (int) $id, object: $data);
        $calculatedCharacter = $this->characterService->calculateCharacter($updatedCharacter);
        
        return new JSONResponse($calculatedCharacter);
    }

    /**
     * Deletes a character
     * 
     * This method is intended to delete a character based on its ID.
     * Currently, it returns an empty JSON response as a placeholder.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the character to delete
     * @return JSONResponse An empty JSON response (placeholder)
     */
    public function destroy(int $id): JSONResponse
    {
        $this->characterMapper->delete($this->characterMapper->find((int) $id));

        return new JSONResponse([]);
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
            $character = $this->characterMapper->find((int) $id);
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