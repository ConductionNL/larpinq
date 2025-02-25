<?php

namespace OCA\LarpingApp\Controller;

use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\CharacterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Exception;
use DateTime;

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
	 * @param string $objectType The type of object to retrieve
	 * @param string $id The ID of the object to retrieve
	 * @return JSONResponse
	 */
	public function show(string $objectType, string $id): JSONResponse
	{
        try {
            // Get all parameters from the request
            $requestParams = $this->request->getParams();
            
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
            
            // Check if we need to recalculate characters based on the object type
            $objectTypesThatAffectCharacters = ['ability'];
            if (in_array($objectType, $objectTypesThatAffectCharacters)) {
                // Recalculate all characters when a relevant object is updated
                $this->characterService->calculateAllCharacters();
            }
            
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
	 * @param string $objectType The type of object to update
	 * @param string $id The ID of the object to update
	 * @return JSONResponse The updated object as a JSON response
	 * @throws Exception If the update fails
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
            
            // Check if we need to recalculate characters based on the object type
            $objectTypesThatAffectCharacters = ['item', 'event', 'condition', 'effect', 'skill', 'ability'];
            if (in_array($objectType, $objectTypesThatAffectCharacters)) {
                // Recalculate all characters when a relevant object is updated
                $this->characterService->calculateAllCharacters();
            }
            
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

    /**
     * Lock an object to prevent concurrent modifications
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @param string $objectType The type of object to lock
     * @param string $id The ID of the object to lock
     * @return JSONResponse
     */
    public function lock(string $objectType, string $id): JSONResponse 
    {
        try {
            // Get request parameters
            $params = $this->request->getParams();
            
            // Extract optional parameters
            $process = $params['process'] ?? null;
            $duration = isset($params['duration']) ? (int)$params['duration'] : null;

            // Attempt to lock the object
            $lockedObject = $this->objectService->lockObject($objectType, $id, $process, $duration);
            
            return new JSONResponse($lockedObject);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    /**
     * Unlock a previously locked object
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @param string $objectType The type of object to unlock
     * @param string $id The ID of the object to unlock
     * @return JSONResponse
     */
    public function unlock(string $objectType, string $id): JSONResponse 
    {
        try {
            $unlockedObject = $this->objectService->unlockObject($objectType, $id);
            return new JSONResponse($unlockedObject);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    /**
     * Check if an object is currently locked
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @param string $objectType The type of object to check
     * @param string $id The ID of the object to check
     * @return JSONResponse
     */
    public function isLocked(string $objectType, string $id): JSONResponse 
    {
        try {
            $isLocked = $this->objectService->isLocked($objectType, $id);
            return new JSONResponse(['locked' => $isLocked]);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    /**
     * Revert an object to a previous state
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @param string $objectType The type of object to revert
     * @param string $id The ID of the object to revert
     * @return JSONResponse
     */
    public function revert(string $objectType, string $id): JSONResponse 
    {
        try {
            // Get request parameters
            $params = $this->request->getParams();
            
            // Extract revert parameters
            $until = null;
            if (isset($params['until'])) {
                // Handle both DateTime and audit trail ID cases
                $until = $params['until'];
                if (strtotime($until) !== false) {
                    $until = new DateTime($until);
                }
            }
            
            $overwriteVersion = $params['overwriteVersion'] ?? false;

            // Attempt to revert the object
            $revertedObject = $this->objectService->revertObject(
                $objectType, 
                $id, 
                $until, 
                $overwriteVersion
            );
            
            return new JSONResponse($revertedObject);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }
}
