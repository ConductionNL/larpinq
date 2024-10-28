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
	 * Return (and search) all objects
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function index(): JSONResponse
	{
		 // Retrieve all request parameters
		 $requestParams = $this->request->getParams();

		 // Fetch catalog objects based on filters and order
		 $data = $this->objectService->getResultArrayForRequest(self::objectType, $requestParams);
 
		 // Return JSON response
		 return new JSONResponse($data);
	}

	/**
	 * Read a single object
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function show(string $id): JSONResponse
	{
        // Fetch the catalog object by its ID
        $object = $this->objectService->getObject(self::objectType, $id);

        // Return the catalog as a JSON response
        return new JSONResponse($object);
	}
    // 
    
    /**
	 * Create an object
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function create(): JSONResponse
	{
        // Get all parameters from the request
        $data = $this->request->getParams();

        // Remove the 'id' field if it exists, as we're creating a new object
        unset($data['id']);

        // Save the new catalog object
        $data = $this->characterService->calculateCharacter($data);
        $object = $this->objectService->saveObject(self::objectType, $data);
        
        // Return the created object as a JSON response
        return new JSONResponse($calculatedCharacter);
	}

	/**
	 * Update an object
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function update(string $id): JSONResponse
	{
        // Get all parameters from the request
        $data = $this->request->getParams();

        // Save the new catalog object
        $data = $this->characterService->calculateCharacter($data);
        $object = $this->objectService->saveObject(self::objectType, $data);
        
        // Return the created object as a JSON response
        return new JSONResponse($object);
	}

	/**
	 * Delete an object
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function destroy(string $id): JSONResponse
	{
        // Delete the catalog object
        $result = $this->objectService->deleteObject(self::objectType, $id);

        // Return the result as a JSON response
		return new JSONResponse(['success' => $result], $result === true ? '200' : '404');
	}

	/**
     * Get audit trail for a specific object
     *
     * @NoAdminRequired
     * @NoCSRFRequired
	 *
	 * @return JSONResponse
     */
    public function getAuditTrail(string $id): JSONResponse
    {
        $auditTrail = $this->objectService->getAuditTrail(self::objectType, $id);
        return new JSONResponse($auditTrail);
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