<?php

namespace OCA\LarpingApp\Controller;

use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\CharacterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Exception;

/**
 * Controller class for handling object-related operations
 */
class ObjectsController extends Controller
{
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
     * @param string $objectType The type of object to return
	 *
	 * @return JSONResponse
	 */
	public function index(string $objectType): JSONResponse
	{
        // Retrieve all request parameters
        $requestParams = $this->request->getParams();

        unset($requestParams['_route']);
        unset($requestParams['objectType']); // Nextcloud automatically adds this from the route so we need to remove it

        // Fetch catalog objects based on filters and order
        $data = $this->objectService->getResultArrayForRequest($objectType, $requestParams);

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
	public function show(string $objectType, string $id): JSONResponse
	{
        try {
            // Get extend parameter if present
            $extend = $requestParams['extend'] ?? $requestParams['_extend'] ?? [];
            if (is_string($extend)) {
                $extend = array_map('trim', explode(',', $extend));
            }

            // Fetch the object by its ID
            $object = $this->objectService->getObject($objectType, $id, $extend);

            // Return the object as a JSON response
            return new JSONResponse($object);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
	}

	/**
	 * Create an object
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function create(string $objectType): JSONResponse
	{
        try {
            // Get all parameters from the request
            $data = $this->request->getParams();
            
            // Get extend parameter if present
            $extend = $data['extend'] ?? $data['_extend'] ?? [];
            if (is_string($extend)) {
                $extend = array_map('trim', explode(',', $extend));
            }


            // Remove the 'id' field if it exists, as we're creating a new object
            unset($data['id']);

            // Small bit of custom logic for characters
            if ($objectType === 'character') {
                $data = $this->characterService->calculateCharacter($data);
            }

            // Save the new object
            $object = $this->objectService->saveObject(objectType: $objectType, object: $data, extend: $extend);
            
            // Return the created object as a JSON response
            return new JSONResponse($object);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
	}

	/**
	 * Update an object
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function update(string $objectType, string $id): JSONResponse
	{
        try {
            // Get all parameters from the request
            $data = $this->request->getParams();
            
            // Get extend parameter if present
            $extend = $data['extend'] ?? $data['_extend'] ?? [];
            if (is_string($extend)) {
                $extend = array_map('trim', explode(',', $extend));
            }

            // Ensure ID in data matches URL parameter
            $data['id'] = $id;

            // Small bit of custom logic for characters
            if ($objectType === 'character') {
                $data = $this->characterService->calculateCharacter($data);
            }    

            // Save the updated object
            $object = $this->objectService->saveObject(objectType: $objectType, object: $data, extend: $extend);
            
            // Return the updated object as a JSON response
            return new JSONResponse($object);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
	}

	/**
	 * Delete an object
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function destroy(string $objectType, string $id): JSONResponse
	{
        try {
            // Delete the object
            $result = $this->objectService->deleteObject($objectType, $id);

            // Return the result as a JSON response
            return new JSONResponse(['success' => $result], $result === true ? 200 : 404);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
	}

	/**
     * Get audit trail for a specific object
     *
     * @NoAdminRequired
     * @NoCSRFRequired
	 *
	 * @return JSONResponse
     */
    public function getAuditTrail(string $objectType, string $id): JSONResponse
    {
        try {

            $auditTrail = $this->objectService->getAuditTrail($objectType, $id);
            return new JSONResponse($auditTrail);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    /**
     * Get all relations for a specific object
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function getRelations(string $objectType, string $id): JSONResponse
    {
        try {
            // Fetch the object by its ID
            $relations = $this->objectService->getRelations($objectType, $id);

            // Return the object as a JSON response
            return new JSONResponse($relations);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        } 
    }

    /**
     * Get all uses for a specific object
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function getUses(string $objectType, string $id): JSONResponse
    {
        $uses = $this->objectService->getUses($objectType, $id);
        return new JSONResponse($uses);
    }
}
