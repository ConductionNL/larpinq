<?php

/**
 * ObjectsController for LarpingApp
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Controller;

use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\CharacterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Exception;
use DateTime;

/**
 * Controller class for handling object-related operations.
 *
 * @psalm-suppress UnusedClass Instantiated by Nextcloud routing (appinfo/routes.php).
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class ObjectsController extends Controller
{
    /**
     * Constructor for ObjectsController.
     *
     * @param string           $appName          The app name.
     * @param IRequest         $request          The request object.
     * @param ObjectService    $objectService    The object service.
     * @param CharacterService $characterService The character service.
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly ObjectService $objectService,
        private readonly CharacterService $characterService
    ) {
        parent::__construct(appName: $appName, request: $request);
    }//end __construct()

    /**
     * Return (and search) all objects.
     *
     * @param string $objectType The type of object to return.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function index(string $objectType): JSONResponse
    {
        // Retrieve all request parameters.
        $requestParams = $this->request->getParams();

        unset($requestParams['_route']);
        unset($requestParams['objectType']);
        // Nextcloud automatically adds this from the route so we need to remove it.
        // Fetch catalog objects based on filters and order.
        $data = $this->objectService->getResultArrayForRequest(objectType: $objectType, requestParams: $requestParams);

        // Return JSON response.
        return new JSONResponse($data);
    }//end index()

    /**
     * Read a single object.
     *
     * @param string $objectType The type of object to retrieve.
     * @param string $id         The ID of the object to retrieve.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function show(string $objectType, string $id): JSONResponse
    {
        try {
            // Get extend parameter if present.
            $requestParams = $this->request->getParams();
            /** @var array<string>|string $extend */
            $extend        = $requestParams['extend'] ?? $requestParams['_extend'] ?? [];
            if (is_string($extend) === true) {
                $extend = array_map('trim', explode(',', $extend));
            }

            // Fetch the object by its ID.
            $object = $this->objectService->getObject(objectType: $objectType, id: $id, extend: $extend);

            // Return the object as a JSON response.
            return new JSONResponse($object);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }//end show()

    /**
     * Create an object.
     *
     * @param string $objectType The type of object to create.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function create(string $objectType): JSONResponse
    {
        try {
            // Get all parameters from the request.
            $data = $this->request->getParams();

            // Get extend parameter if present.
            /** @var array<string>|string $extend */
            $extend = $data['extend'] ?? $data['_extend'] ?? [];
            if (is_string($extend) === true) {
                $extend = array_map('trim', explode(',', $extend));
            }

            // Remove the 'id' field if it exists, as we're creating a new object.
            unset($data['id']);

            // Small bit of custom logic for characters.
            if ($objectType === 'character') {
                $data = $this->characterService->calculateCharacter($data);
            }

            // Save the new object.
            /** @var array<string,mixed> $object */
            $object = $this->objectService->saveObject(objectType: $objectType, object: $data, extend: $extend);

            // Return the created object as a JSON response.
            return new JSONResponse($object);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }//end try
    }//end create()

    /**
     * Update an object.
     *
     * @param string $objectType The type of object to update.
     * @param string $id         The ID of the object to update.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function update(string $objectType, string $id): JSONResponse
    {
        try {
            // Get all parameters from the request.
            $data = $this->request->getParams();

            // Get extend parameter if present.
            /** @var array<string>|string $extend */
            $extend = $data['extend'] ?? $data['_extend'] ?? [];
            if (is_string($extend) === true) {
                $extend = array_map('trim', explode(',', $extend));
            }

            // Ensure ID in data matches URL parameter.
            $data['id'] = $id;

            // Small bit of custom logic for characters.
            if ($objectType === 'character') {
                $data = $this->characterService->calculateCharacter($data);
            }

            // Save the updated object.
            /** @var array<string,mixed> $object */
            $object = $this->objectService->saveObject(objectType: $objectType, object: $data, extend: $extend);

            // Return the updated object as a JSON response.
            return new JSONResponse($object);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }//end try
    }//end update()

    /**
     * Delete an object.
     *
     * @param string $objectType The type of object to delete.
     * @param string $id         The ID of the object to delete.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function destroy(string $objectType, string $id): JSONResponse
    {
        try {
            // Delete the object.
            $result = $this->objectService->deleteObject(objectType: $objectType, id: $id);

            // Return the result as a JSON response.
            $statusCode = 404;
            if ($result === true) {
                $statusCode = 200;
            }

            return new JSONResponse(['success' => $result], $statusCode);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }//end destroy()

    /**
     * Get audit trail for a specific object.
     *
     * @param string $objectType The type of object to get audit trail for.
     * @param string $id         The ID of the object to get audit trail for.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function getAuditTrail(string $objectType, string $id): JSONResponse
    {
        try {
            $auditTrail = $this->objectService->getAuditTrail(objectType: $objectType, id: $id);
            return new JSONResponse($auditTrail);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }//end getAuditTrail()

    /**
     * Get all relations for a specific object.
     *
     * @param string $objectType The type of object to get relations for.
     * @param string $id         The ID of the object to get relations for.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function getRelations(string $objectType, string $id): JSONResponse
    {
        try {
            // Fetch the object by its ID.
            $relations = $this->objectService->getRelations(objectType: $objectType, id: $id);

            // Return the object as a JSON response.
            return new JSONResponse($relations);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }//end getRelations()

    /**
     * Get all uses for a specific object.
     *
     * @param string $objectType The type of object to get uses for.
     * @param string $id         The ID of the object to get uses for.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function getUses(string $objectType, string $id): JSONResponse
    {
        $uses = $this->objectService->getUses(objectType: $objectType, id: $id);
        return new JSONResponse($uses);
    }//end getUses()

    /**
     * Lock an object to prevent concurrent modifications.
     *
     * @param string $objectType The type of object to lock.
     * @param string $id         The ID of the object to lock.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function lock(string $objectType, string $id): JSONResponse
    {
        try {
            // Get request parameters.
            $params = $this->request->getParams();

            // Extract optional parameters.
            /** @var string|null $process */
            $process  = $params['process'] ?? null;
            $duration = null;
            if (isset($params['duration']) === true) {
                $duration = (int) $params['duration'];
            }

            // Attempt to lock the object.
            $lockedObject = $this->objectService->lockObject($objectType, $id, $process, $duration);

            return new JSONResponse($lockedObject);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }//end try
    }//end lock()

    /**
     * Unlock a previously locked object.
     *
     * @param string $objectType The type of object to unlock.
     * @param string $id         The ID of the object to unlock.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     */
    public function unlock(string $objectType, string $id): JSONResponse
    {
        try {
            $unlockedObject = $this->objectService->unlockObject(objectType: $objectType, id: $id);
            return new JSONResponse($unlockedObject);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }
    }//end unlock()

    /**
     * Revert an object to a previous state.
     *
     * @param string $objectType The type of object to revert.
     * @param string $id         The ID of the object to revert.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function revert(string $objectType, string $id): JSONResponse
    {
        try {
            // Get request parameters.
            $params = $this->request->getParams();

            // Extract revert parameters.
            /** @var DateTime|string|null $until */
            $until = null;
            if (isset($params['until']) === true) {
                // Handle both DateTime and audit trail ID cases.
                /** @var string $untilParam */
                $untilParam = $params['until'];
                if (strtotime($untilParam) !== false) {
                    $until = new DateTime($untilParam);
                } else {
                    $until = $untilParam;
                }
            }

            $overwriteVersion = (bool) ($params['overwriteVersion'] ?? false);

            // Attempt to revert the object.
            $revertedObject = $this->objectService->revertObject(
                objectType: $objectType,
                id: $id,
                until: $until,
                overwriteVersion: $overwriteVersion
            );

            return new JSONResponse($revertedObject);
        } catch (Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                400
            );
        }//end try
    }//end revert()
}//end class
