<?php

declare(strict_types=1);

/**
 * Objects controller implementation
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @version   Release: 0.1.0
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

namespace OCA\LarpingApp\Controller;

use Exception;
use DateTime;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\CharacterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Controller for handling generic object operations
 *
 * This controller provides endpoints for CRUD operations on various object types
 * and additional functionality like audit trails, relations, and locking.
 *
 * @category Controller
 * @package  OCA\LarpingApp\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 */
class ObjectsController extends Controller
{
    /**
     * Constructor for ObjectsController
     *
     * @param string           $appName          The name of the app
     * @param IRequest         $request          The request object
     * @param ObjectService    $objectService    The object service
     * @param CharacterService $characterService The character service
     * 
     * @return void
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly ObjectService $objectService,
        private readonly CharacterService $characterService
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Return (and search) all objects of a specific type
     *
     * Retrieves objects with optional filtering, sorting, and pagination.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @param string $objectType The type of object to return
     *
     * @return JSONResponse The objects data or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
     */
    public function index(string $objectType): JSONResponse
    {
        // Retrieve all request parameters
        $requestParams = $this->request->getParams();

        // Remove route parameters
        unset($requestParams['_route']);
        unset($requestParams['objectType']); // Nextcloud automatically adds this from the route so we need to remove it

        // Fetch catalog objects based on filters and order
        $data = $this->objectService->getResultArrayForRequest($objectType, $requestParams);

        // Return JSON response
        return new JSONResponse($data);
    }

    /**
     * Read a single object by type and ID
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $objectType The type of object to retrieve
     * @param string $id         The ID of the object to retrieve
     * 
     * @return JSONResponse The object data or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
     */
    public function show(string $objectType, string $id): JSONResponse
    {
        try {
            // Get extend parameter if present
            $extend = $this->request->getParam('extend', []);
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
     * Create a new object
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $objectType The type of object to create
     * 
     * @return JSONResponse The created object or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
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
     * Update an existing object
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $objectType The type of object to update
     * @param string $id         The ID of the object to update
     * 
     * @return JSONResponse The updated object or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
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
     * @param string $objectType The type of object to delete
     * @param string $id         The ID of the object to delete
     * 
     * @return JSONResponse Success status or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
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
     * Retrieves the history of changes for the specified object.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $objectType The type of object to get audit trail for
     * @param string $id         The ID of the object to get audit trail for
     * 
     * @return JSONResponse The audit trail or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
     */
    public function getAuditTrail(string $objectType, string $id): JSONResponse
    {
        try {
            // Get audit trail for the object
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
     * Retrieves all objects that have a relationship with the specified object.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $objectType The type of object to get relations for
     * @param string $id         The ID of the object to get relations for
     * 
     * @return JSONResponse The relations or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
     */
    public function getRelations(string $objectType, string $id): JSONResponse
    {
        try {
            // Get relations for the object
            $relations = $this->objectService->getRelations($objectType, $id);

            // Return the relations as a JSON response
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
     * Retrieves all objects that use the specified object.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $objectType The type of object to get uses for
     * @param string $id         The ID of the object to get uses for
     * 
     * @return JSONResponse The uses or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
     */
    public function getUses(string $objectType, string $id): JSONResponse
    {
        try {
            // Get uses for the object
            $uses = $this->objectService->getUses($objectType, $id);
            
            return new JSONResponse($uses);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    /**
     * Lock an object to prevent concurrent modifications
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @param string $objectType The type of object to lock
     * @param string $id         The ID of the object to lock
     * 
     * @return JSONResponse The locked object or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
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
     * @param string $id         The ID of the object to unlock
     * 
     * @return JSONResponse The unlocked object or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
     */
    public function unlock(string $objectType, string $id): JSONResponse 
    {
        try {
            // Unlock the object
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
     * @param string $id         The ID of the object to check
     * 
     * @return JSONResponse Lock status or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
     */
    public function isLocked(string $objectType, string $id): JSONResponse 
    {
        try {
            // Check if the object is locked
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
     * @param string $id         The ID of the object to revert
     * 
     * @return JSONResponse The reverted object or error response
     * 
     * @psalm-return   JSONResponse
     * @phpstan-return JSONResponse
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
