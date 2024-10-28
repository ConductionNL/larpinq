<?php

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Db\Event;
use OCA\LarpingApp\Db\EventMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class EventsController extends Controller
{
   
    const objectType = 'event';

    public function __construct(
		$appName,
		IRequest $request,
		private readonly ObjectService $objectService
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
        $object = $this->objectService->saveObject(self::objectType, $data);
        
        // Return the created object as a JSON response
        return new JSONResponse($object);
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
}