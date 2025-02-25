<?php

declare(strict_types=1);

/**
 * Object service implementation
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @version   0.1.0
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

namespace OCA\LarpingApp\Service;

use Adbar\Dot;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Uid\Uuid;
use Psr\Container\ContainerInterface;
use OCP\IAppConfig;
// Open Register
use OCA\OpenRegister\Service\Exceptions\LockedException;
use OCA\OpenRegister\Service\Exceptions\NotAuthorizedException;
use OCA\OpenRegister\Service\Exceptions\NotFoundException;

/**
 * Service class for generic object operations
 *
 * This service provides a unified interface for working with different types of objects
 * in the LarpingApp. It handles CRUD operations, object relationships, and integrates
 * with the OpenRegister service.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 */
class ObjectService
{
    /**
     * The application name
     * 
     * @var string
     */
    private readonly string $_appName;

    /**
     * Constructor for ObjectService
     *
     * @param ContainerInterface $container  DI container for service resolution
     * @param IAppManager        $appManager App manager for checking installed apps
     * @param IConfig            $config     Config service for app configuration
     * 
     * @return void
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly IAppManager $appManager,
        private readonly IConfig $config
    ) {
        $this->_appName = 'larpingapp';
    }

    /**
     * Gets the appropriate mapper based on the object type.
     *
     * This method determines which mapper to use based on the object type and the
     * configured source (OpenRegister).
     *
     * @param string $objectType The type of object to retrieve the mapper for
     *
     * @return mixed The appropriate mapper for the object type
     * 
     * @throws InvalidArgumentException If an unknown object type is provided
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws Exception If OpenRegister service is not available or if register/schema is not configured
     * 
     * @psalm-return mixed
     * @phpstan-return mixed
     */
    private function _getMapper(string $objectType): mixed
    {
        // Convert object type to lowercase for consistent configuration lookup
        $objectTypeLower = strtolower($objectType);

        // Get the OpenRegister service
        $openRegister = $this->getOpenRegisters();
        if ($openRegister === null) {
            throw new Exception("OpenRegister service not available");
        }
        
        // Get the register and schema configuration
        $register = $this->config->getValueString($this->_appName, $objectTypeLower . '_register', '');
        if (empty($register)) {
            throw new Exception("Register not configured for $objectType");
        }
        
        $schema = $this->config->getValueString($this->_appName, $objectTypeLower . '_schema', '');
        if (empty($schema)) {
            throw new Exception("Schema not configured for $objectType");
        }
        
        // Return the OpenRegister mapper for the specified register and schema
        return $openRegister->getMapper(register: $register, schema: $schema);
    }

    /**
     * Gets an object based on the object type and id.
     *
     * Retrieves a single object of the specified type with the given ID.
     * Can optionally extend the object with related data.
     *
     * @param string $objectType The type of object to retrieve
     * @param string $id         The id of the object to retrieve
     * @param array  $extend     Optional array of related objects to include
     *
     * @return array The retrieved object as an array
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws DoesNotExistException If the object doesn't exist
     * @throws MultipleObjectsReturnedException If multiple objects are found
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * @throws InvalidArgumentException If extend is requested for non-OpenRegister objects
     * 
     * @psalm-return array
     * @phpstan-return array<string, mixed>
     */
    public function getObject(string $objectType, string $id, array $extend = []): array
    {
        // Clean up the id if it's a URI by getting only the last path part
        if (filter_var($id, FILTER_VALIDATE_URL)) {
            $parts = explode('/', rtrim($id, '/'));
            $id = end($parts);
        }

        // Get the appropriate mapper for the object type
        $mapper = $this->_getMapper($objectType);

        // Use the mapper to find and return the object
        $object = $mapper->find($id);

        // Convert the object to an array if it is not already an array
        if (is_object($object) && method_exists($object, 'jsonSerialize')) {
            return $object->jsonSerialize();
        }
        
        return is_array($object) ? $object : (array)$object;
    }

    /**
     * Gets objects based on the object type and various parameters.
     *
     * Retrieves multiple objects of the specified type, with optional filtering,
     * sorting, pagination, and extension of related data.
     *
     * @param string      $objectType The type of objects to retrieve
     * @param int|null    $limit      The maximum number of objects to retrieve (optional)
     * @param int|null    $offset     The offset from which to start retrieving objects (optional)
     * @param array       $filters    Filters to apply to the query (optional)
     * @param array       $sort       Sorting parameters for the query (optional)
     * @param string|null $search     Search term to filter objects (optional)
     * @param array       $extend     Additional parameters for extending the query with related data (optional)
     *
     * @return array The retrieved objects as arrays
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws DoesNotExistException If an object doesn't exist
     * @throws MultipleObjectsReturnedException If multiple objects are found for a single ID
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * 
     * @psalm-return   array
     * @phpstan-return array<int, array<string, mixed>>
     */
    public function getObjects(
        string $objectType,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        array $sort = [],
        ?string $search = null,
        array $extend = []
    ): array {
        // Get the appropriate mapper for the object type
        $mapper = $this->_getMapper($objectType);

        // Use the mapper to find and return the objects based on the provided parameters
        $objects = $mapper->findAll(
            limit: $limit, 
            offset: $offset, 
            filters: $filters, 
            sort: $sort,
            search: $search,
            extend: $extend
        );

        // Convert entity objects to arrays using jsonSerialize
        return array_map(
            function ($object) {
                // If object is already an array, return it directly
                if (is_array($object)) {
                    return $object;
                }
                // Otherwise serialize the object
                return $object->jsonSerialize();
            }, 
            $objects
        );
    }

    /**
     * Gets facet information for objects based on the object type and filters.
     *
     * Retrieves aggregated data (facets) for the specified object type with optional filtering.
     *
     * @param string $objectType The type of objects to retrieve facets for
     * @param array  $filters    Filters to apply to the facet query (optional)
     *
     * @return array The facet information
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws DoesNotExistException If an object doesn't exist
     * @throws MultipleObjectsReturnedException If multiple objects are found for a single ID
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * 
     * @psalm-return   array
     * @phpstan-return array<string, mixed>
     */
    public function getFacets(
        string $objectType,
        array $filters = [],
    ): array {
        // Get the appropriate mapper for the object type
        $mapper = $this->_getMapper($objectType);

        // Use the mapper to find and return the facets based on the provided parameters
        return $mapper->getAggregations($filters);
    }

    /**
     * Gets multiple objects based on the object type and ids.
     *
     * Retrieves multiple objects of the specified type with the given IDs.
     *
     * @param string $objectType The type of objects to retrieve
     * @param array  $ids        The ids of the objects to retrieve
     *
     * @return array The retrieved objects
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * 
     * @psalm-return   array
     * @phpstan-return array<int, mixed>
     */
    public function getMultipleObjects(string $objectType, array $ids): array
    {
        // Process the ids to handle different input formats
        $processedIds = array_map(
            function ($id) {
                if (is_object($id) && method_exists($id, 'getId')) {
                    // Extract ID from object with getId method
                    return $id->getId();
                } elseif (is_array($id) && isset($id['id'])) {
                    // Extract ID from array with 'id' key
                    return $id['id'];
                } else {
                    // Use ID directly
                    return $id;
                }
            }, 
            $ids
        );

        // Clean up the ids if they are URIs
        $cleanedIds = array_map(
            function ($id) {
                // If the id is a URI, get only the last part of the path
                if (filter_var($id, FILTER_VALIDATE_URL)) {
                    $parts = explode('/', rtrim($id, '/'));
                    return end($parts);
                }
                return $id;
            }, 
            $processedIds
        );

        // Get the appropriate mapper for the object type
        $mapper = $this->_getMapper($objectType);

        // Use the mapper to find and return multiple objects based on the provided cleaned ids
        return $mapper->findMultiple($cleanedIds);
    }

    /**
     * Gets all objects of a specific type.
     *
     * Retrieves all objects of the specified type with optional pagination.
     *
     * @param string   $objectType The type of objects to retrieve
     * @param int|null $limit      The maximum number of objects to retrieve (optional)
     * @param int|null $offset     The offset from which to start retrieving objects (optional)
     *
     * @return array The retrieved objects
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * 
     * @psalm-return   array
     * @phpstan-return array<int, mixed>
     */
    public function getAllObjects(string $objectType, ?int $limit = null, ?int $offset = null): array
    {
        // Get the appropriate mapper for the object type
        $mapper = $this->_getMapper($objectType);

        // Use the mapper to find and return all objects of the specified type
        return $mapper->findAll($limit, $offset);
    }

    /**
     * Creates a new object or updates an existing one from an array of data.
     *
     * @param string $objectType    The type of object to create or update
     * @param array  $object        The data to create or update the object from
     * @param array  $extend        Additional parameters for extending the object with related data (optional)
     * @param bool   $updateVersion Whether to update the version or not (default = true)
     *
     * @return mixed The created or updated object
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws DoesNotExistException If the object doesn't exist during update
     * @throws MultipleObjectsReturnedException If multiple objects are found for a single ID
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * 
     * @psalm-return   mixed
     * @phpstan-return mixed
     */
    public function saveObject(string $objectType, array $object, array $extend = [], bool $updateVersion = true): mixed
    {
        // Get the appropriate mapper for the object type
        $mapper = $this->_getMapper($objectType);
        
        // If the object has an id, update it; otherwise, create a new object
        if (isset($object['id']) === true) {
            // Update existing object
            return $mapper->updateFromArray(
                id: $object['id'], 
                object: $object, 
                updateVersion: $updateVersion, 
                extend: $extend
            );
        } else {
            // Create new object
            return $mapper->createFromArray(object: $object, extend: $extend);
        }
    }

    /**
     * Deletes an object based on the object type and id.
     *
     * @param string     $objectType The type of object to delete
     * @param string|int $id         The id of the object to delete
     *
     * @return bool True if the object was successfully deleted, false otherwise
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * @throws \OCP\DB\Exception If there's a database error
     * 
     * @psalm-return   bool
     * @phpstan-return bool
     */
    public function deleteObject(string $objectType, string|int $id): bool
    {
        // Get the appropriate mapper for the object type
        $mapper = $this->_getMapper($objectType);

        // Use the mapper to get and delete the object
        try {
            // Find the object first
            $object = $mapper->find($id);
            // Then delete it
            $mapper->delete($object);
            return true;
        } catch (Exception $e) {
            // Return false if any exception occurs during deletion
            return false;
        }
    }

    /**
     * Attempts to retrieve the OpenRegister service from the container.
     *
     * @return \OCA\OpenRegister\Service\ObjectService|null The OpenRegister service if available, null otherwise
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * 
     * @psalm-return   \OCA\OpenRegister\Service\ObjectService|null
     * @phpstan-return \OCA\OpenRegister\Service\ObjectService|null
     */
    public function getOpenRegisters(): ?\OCA\OpenRegister\Service\ObjectService
    {
        // Check if the OpenRegister app is installed
        if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === true) {
            try {
                // Attempt to get the OpenRegister service from the container
                return $this->container->get('OCA\OpenRegister\Service\ObjectService');
            } catch (Exception $e) {
                // If the service is not available, return null
                return null;
            }
        }

        // Return null if the OpenRegister app is not installed
        return null;
    }

    /**
     * Gets the count of objects of a specific type with optional filtering.
     *
     * @param string $objectType The type of objects to count
     * @param array  $filters    Filters to apply to the count query (optional)
     *
     * @return int The count of objects
     * 
     * @psalm-return   int
     * @phpstan-return int
     */
    private function getCount(string $objectType, array $filters = []): int
    {
        // Get the appropriate mapper for the object type
        $mapper = $this->_getMapper($objectType);
        
        // Get the count from the mapper
        return $mapper->count(filters: $filters);
    }

    /**
     * Get a result array for a request based on the request parameters and the object type.
     *
     * This method processes request parameters to retrieve objects with pagination,
     * sorting, filtering, and extension of related data.
     *
     * @param string $objectType    The type of object to retrieve
     * @param array  $requestParams The request parameters
     *
     * @return array The result array containing objects, facets, and total count
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws DoesNotExistException If an object doesn't exist
     * @throws MultipleObjectsReturnedException If multiple objects are found for a single ID
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * 
     * @psalm-return   array{results: array, facets: array, total: int}
     * @phpstan-return array{results: array<int, array<string, mixed>>, facets: array<string, mixed>, total: int}
     */
    public function getResultArrayForRequest(string $objectType, array $requestParams): array
    {
        // Extract specific parameters with support for different naming conventions
        $limit = $requestParams['limit'] ?? $requestParams['_limit'] ?? null;
        $offset = $requestParams['offset'] ?? $requestParams['_offset'] ?? null;
        $order = $requestParams['order'] ?? $requestParams['_order'] ?? [];
        $extend = $requestParams['extend'] ?? $requestParams['_extend'] ?? null;
        $page = $requestParams['page'] ?? $requestParams['_page'] ?? null;
        $search = $requestParams['search'] ?? $requestParams['_search'] ?? null;
        
        // If page is set, calculate the offset based on the page number and limit
        if ($page !== null && isset($limit)) {
            $offset = $limit * ($page - 1);
        }

        // Ensure order and extend are arrays by converting comma-separated strings if needed
        if (is_string($order)) {
            $order = array_map('trim', explode(',', $order));
        }
        if (is_string($extend)) {
            $extend = array_map('trim', explode(',', $extend));
        }

        // Remove unnecessary parameters from filters
        $filters = $requestParams;
        unset($filters['_route']); // Nextcloud automatically adds this
        unset($filters['_extend'], $filters['_limit'], $filters['_offset'], $filters['_order'], $filters['_page']);
        unset($filters['extend'], $filters['limit'], $filters['offset'], $filters['order'], $filters['page']);
        
        // Fetch objects based on filters and order
        $objects = $this->getObjects(
            objectType: $objectType,
            limit: $limit,
            offset: $offset,
            filters: $filters,
            sort: $order,
            extend: $extend,
            search: $search
        );

        // Get facets for the object type with the specified filters
        $facets = $this->getFacets($objectType, $filters);

        // Return the results, facets, and total count
        return [
            'results' => $objects,
            'facets' => $facets,
            'total' => $this->getCount(objectType: $objectType, filters: $filters),
        ];
    }

    /**
     * Extends an entity with related objects based on the extend array.
     *
     * This method enriches an entity by replacing IDs with the actual related objects.
     *
     * @param mixed $entity The entity to extend
     * @param array $extend An array of properties to extend
     *
     * @return array The extended entity as an array
     * 
     * @throws ContainerExceptionInterface If there's an error with the container
     * @throws DoesNotExistException If a related object doesn't exist
     * @throws MultipleObjectsReturnedException If multiple objects are found for a single ID
     * @throws NotFoundExceptionInterface If a required service cannot be found
     * @throws Exception If a property is not present on the entity or no mapper is available
     * 
     * @psalm-return   array
     * @phpstan-return array<string, mixed>
     */
    public function extendEntity(mixed $entity, array $extend): array
    {
        $suppressMapperError = false;
        
        // Convert the entity to an array if it's not already one
        $result = is_array($entity) ? $entity : $entity->jsonSerialize();

        // If 'all' is in the extend array, extend all properties
        if (in_array(needle: 'all', haystack: $extend) === true) {
            $extend = array_keys($entity);
            $suppressMapperError = true;
        }

        // Iterate through each property to be extended
        foreach ($extend as $property) {
            // Create a singular property name (for handling both singular and plural property names)
            $singularProperty = rtrim($property, 's');

            // Check if property or singular property are keys in the array
            if (array_key_exists($property, $result)) {
                $value = $result[$property];
                if (empty($value)) {
                    continue;
                }
            } elseif (array_key_exists($singularProperty, $result)) {
                $value = $result[$singularProperty];
            } else {
                throw new Exception("Property '$property' or '$singularProperty' is not present in the entity.");
            }

            // Get a mapper for the property
            $propertyObject = $property;
            try {
                // Try to get mapper for the property as is
                $mapper = $this->_getMapper($property);
                $propertyObject = $singularProperty;
            } catch (Exception $e) {
                try {
                    // If that fails, try to get mapper for the singular property
                    $mapper = $this->_getMapper($singularProperty);
                    $propertyObject = $singularProperty;
                } catch (Exception $e) {
                    // If still no mapper, throw a no mapper available error (unless suppressed)
                    if ($suppressMapperError === true) {
                        continue;
                    }
                    throw new Exception("No mapper available for property '$property'.");
                }
            }

            // Update the values with the actual objects
            if (is_array($value)) {
                // If the value is an array, get multiple related objects
                $result[$property] = $this->getMultipleObjects($propertyObject, $value);
            } else {
                // If the value is not an array, get a single related object
                $objectId = is_object($value) ? $value->getId() : $value;
                $result[$property] = $this->getObject($propertyObject, $objectId);
            }
        }

        // Return the extended entity as an array
        return $result;
    }

    /**
     * Get all relations for a specific object
     *
     * Retrieves all objects that have a relationship with the specified object.
     *
     * @param string $objectType The type of object to get relations for
     * @param string $id         The id of the object to get relations for
     * 
     * @return array The relations for the object
     * 
     * @throws Exception If OpenRegister service is not available
     * 
     * @psalm-return array
     * @phpstan-return array<string, mixed>
     */
    public function getRelations(string $objectType, string $id): array
    {
        // Get the mapper first
        $mapper = $this->_getMapper($objectType);

        // Get relations from the mapper
        return $mapper->getRelations($id);
    }

    /**
     * Get all the uses that a specific object has
     *
     * Retrieves all objects that use the specified object.
     *
     * @param string $objectType The type of object to get uses for
     * @param string $id         The id of the object to get uses for
     * 
     * @return array The uses for the object
     * 
     * @psalm-return array
     * @phpstan-return array<string, mixed>
     */
    public function getUses(string $objectType, string $id): array
    {
        // Get the mapper for the object type
        $mapper = $this->_getMapper($objectType);
        
        // Get uses from the mapper
        return $mapper->getUses($id);
    }

    /**
     * Get all files associated with a specific object
     *
     * @param string $objectType The type of object to get files for
     * @param string $id         The id of the object to get files for
     *
     * @return array The files associated with the object
     * 
     * @psalm-return array
     * @phpstan-return array<int, array<string, mixed>>
     */
    public function getFiles(string $objectType, string $id): array
    {
        // Get the mapper for the object type
        $mapper = $this->_getMapper($objectType);

        // Get and format files from the mapper
        return $mapper->formatFiles($mapper->getFiles($id));
    }

    /**
     * Get all audit trails for a specific object
     *
     * Retrieves the history of changes for the specified object.
     *
     * @param string $objectType The type of object to get audit trails for
     * @param string $id         The id of the object to get audit trails for
     * 
     * @return array The audit trails for the object
     * 
     * @psalm-return array
     * @phpstan-return array<int, array<string, mixed>>
     */
    public function getAuditTrail(string $objectType, string $id): array
    {
        // Get the mapper for the object type
        $mapper = $this->_getMapper($objectType);

        // Get audit trails from the mapper
        return $mapper->getAuditTrail($id);
    }

    /**
     * Lock an object to prevent concurrent modifications
     *
     * @param string      $objectType The type of object to lock
     * @param string|int  $id         The id of the object to lock
     * @param string|null $process    Optional process identifier
     * @param int|null    $duration   Lock duration in seconds (default: 1 hour)
     * 
     * @return mixed The locked object
     * 
     * @throws LockedException If the object is already locked
     * @throws NotFoundException If the object is not found
     * @throws NotAuthorizedException If the user is not authorized to lock the object
     * 
     * @psalm-return mixed
     * @phpstan-return mixed
     */
    public function lockObject(string $objectType, string|int $id, ?string $process = null, ?int $duration = 3600): mixed
    {
        $mapper = $this->_getMapper($objectType);
        return $mapper->lockObject($id, $process, $duration);
    }

    /**
     * Unlock an object
     *
     * @param string     $objectType The type of object to unlock
     * @param string|int $id         The id of the object to unlock
     * 
     * @return mixed The unlocked object
     * 
     * @throws NotFoundException If the object is not found
     * @throws NotAuthorizedException If the user is not authorized to unlock the object
     * 
     * @psalm-return mixed
     * @phpstan-return mixed
     */
    public function unlockObject(string $objectType, string|int $id): mixed
    {
        return $this->_getMapper($objectType)->unlockObject($id);
    }

    /**
     * Check if an object is locked
     *
     * @param string     $objectType The type of object to check
     * @param string|int $id         The id of the object to check
     * 
     * @return bool True if object is locked, false otherwise
     * 
     * @psalm-return bool
     * @phpstan-return bool
     */
    public function isLocked(string $objectType, string|int $id): bool
    {
        return $this->_getMapper($objectType)->isLocked($id);
    }

    /**
     * Revert an object to a previous state
     *
     * @param string               $objectType       The type of object to revert
     * @param string|int           $id               The id of the object to revert
     * @param DateTime|string|null $until            DateTime or AuditTrail ID to revert to
     * @param bool                 $overwriteVersion Whether to overwrite the version or increment it
     * 
     * @return mixed The reverted object
     * 
     * @throws NotFoundException If the object is not found
     * @throws NotAuthorizedException If the user is not authorized to revert the object
     * 
     * @psalm-return mixed
     * @phpstan-return mixed
     */
    public function revertObject(
        string $objectType,
        string|int $id,
        $until = null,
        bool $overwriteVersion = false
    ): mixed {
        return $this->_getMapper($objectType)->revertObject($id, $until, $overwriteVersion);
    }
}
