<?php

/**
 * ObjectService for LarpingApp
 *
 * @category  Service
 * @package   OCA\LarpingApp\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   AGPL-3.0-or-later https://www.gnu.org/licenses/agpl-3.0.en.html
 * @link      https://larpingapp.com
 */

declare(strict_types=1);

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
// Import mappers.
use OCA\LarpingApp\Db\AbilityMapper;
use OCA\LarpingApp\Db\CharacterMapper;
use OCA\LarpingApp\Db\ConditionMapper;
use OCA\LarpingApp\Db\EffectMapper;
use OCA\LarpingApp\Db\EventMapper;
use OCA\LarpingApp\Db\ItemMapper;
use OCA\LarpingApp\Db\PlayerMapper;
use OCA\LarpingApp\Db\SettingMapper;
use OCA\LarpingApp\Db\SkillMapper;
use OCA\OpenRegister\Service\Exceptions\LockedException;
use OCA\OpenRegister\Service\Exceptions\NotAuthorizedException;
use OCA\OpenRegister\Service\Exceptions\NotFoundException;

/**
 * Service class for generic object operations.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UndefinedClass, UndefinedDocblockClass OpenRegister is an optional dependency.
 * @psalm-suppress UnusedProperty Container and appManager used in getOpenRegisters().
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class ObjectService
{

    /**
     * The name of the application.
     *
     * @var string
     */
    private $appName;

    /**
     * Constructor for ObjectService.
     *
     * @param AbilityMapper      $abilityMapper   Ability mapper.
     * @param CharacterMapper    $characterMapper Character mapper.
     * @param ConditionMapper    $conditionMapper Condition mapper.
     * @param EffectMapper       $effectMapper    Effect mapper.
     * @param EventMapper        $eventMapper     Event mapper.
     * @param ItemMapper         $itemMapper      Item mapper.
     * @param PlayerMapper       $playerMapper    Player mapper.
     * @param SettingMapper      $settingMapper   Setting mapper.
     * @param SkillMapper        $skillMapper     Skill mapper.
     * @param ContainerInterface $container       DI container.
     * @param IAppManager        $appManager      App manager.
     * @param IAppConfig         $config          Config service.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private AbilityMapper $abilityMapper,
        private CharacterMapper $characterMapper,
        private ConditionMapper $conditionMapper,
        private EffectMapper $effectMapper,
        private EventMapper $eventMapper,
        private ItemMapper $itemMapper,
        private PlayerMapper $playerMapper,
        private SettingMapper $settingMapper,
        private SkillMapper $skillMapper,
        // @psalm-suppress UnusedProperty Used in getOpenRegisters().
        private ContainerInterface $container,
        // @psalm-suppress UnusedProperty Used in getOpenRegisters().
        private IAppManager $appManager,
        private IAppConfig $config
    ) {
        $this->appName = 'larpingapp';
    }//end __construct()

    /**
     * Gets the appropriate mapper based on the object type.
     *
     * @param string $objectType The type of object to retrieve the mapper for.
     *
     * @return AbilityMapper|CharacterMapper|ConditionMapper|EffectMapper|EventMapper
     *         |ItemMapper|PlayerMapper|SettingMapper|SkillMapper|object The mapper.
     *
     * @throws InvalidArgumentException If an unknown object type is provided.
     * @throws NotFoundExceptionInterface|ContainerExceptionInterface If OpenRegister service is not available
     *                                                                or if register/schema is not configured.
     * @throws Exception
     *
     * @psalm-suppress UndefinedDocblockClass OpenRegister classes may not be installed.
     */
    public function getMapper(string $objectType): object
    {
        $objectTypeLower = strtolower($objectType);

        // Get the source for the object type from the configuration.
        $source = $this->config->getValueString(
            $this->appName,
            $objectTypeLower.'_source',
            'internal'
        );

        // If the source is 'open_registers', use the OpenRegister service.
        if ($source === 'openregister') {
            $openRegister = $this->getOpenRegisters();
            if ($openRegister === null) {
                throw new Exception("OpenRegister service not available");
            }

            $register = $this->config->getValueString($this->appName, $objectTypeLower.'_register', '');
            if (empty($register) === true) {
                throw new Exception("Register not configured for $objectType");
            }

            $schema = $this->config->getValueString($this->appName, $objectTypeLower.'_schema', '');
            if (empty($schema) === true) {
                throw new Exception("Schema not configured for $objectType");
            }

            // @psalm-suppress MixedMethodCall OpenRegister is an optional cross-app dependency.
            // @var object $orMapper
            $orMapper = $openRegister->getMapper($register, $schema);
            return $orMapper;
        }//end if

        // If the source is internal, return the appropriate mapper based on the object type.
        return match ($objectType) {
            'ability' => $this->abilityMapper,
            'character' => $this->characterMapper,
            'condition' => $this->conditionMapper,
            'effect' => $this->effectMapper,
            'event' => $this->eventMapper,
            'item' => $this->itemMapper,
            'player' => $this->playerMapper,
            'setting' => $this->settingMapper,
            'skill' => $this->skillMapper,
            default => throw new InvalidArgumentException("Unknown object type: $objectType"),
        };
    }//end getMapper()

    /**
     * Gets an object based on the object type and id.
     *
     * @param string $objectType The type of object to retrieve.
     * @param string $id         The id of the object to retrieve.
     * @param array  $extend     Optional array of properties to extend.
     *
     * @return array<string,mixed> The retrieved object.
     *
     * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
     * @throws InvalidArgumentException If extend is requested for non-OpenRegister objects.
     *
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedReturnStatement Mapper resolved dynamically via getMapper().
     */
    public function getObject(string $objectType, string $id, array $extend=[]): array
    {
        // Clean up the id if it's a URI by getting only the last path part.
        if (filter_var($id, FILTER_VALIDATE_URL) !== false) {
            $parts = explode('/', rtrim($id, '/'));
            $id    = end($parts);
        }

        // Get the appropriate mapper for the object type.
        $mapper = $this->getMapper(objectType: $objectType);

        // Check if extend is requested for non-OpenRegister objects.
        if (empty($extend) === false && ($mapper instanceof \OCA\OpenRegister\Service\ObjectService) === false) {
            throw new InvalidArgumentException('Extend functionality is only available for OpenRegister objects');
        }

        // Use the mapper to find and return the object.
        // @psalm-suppress MixedAssignment Mapper resolved dynamically.
        $object = $mapper->find($id);

        // Convert the object to an array if it is not already an array.
        if (is_object($object) === true && method_exists($object, 'jsonSerialize') === true) {
            return $object->jsonSerialize();
        }

        // @psalm-suppress MixedAssignment Mapper resolved dynamically.
        $result = $object;
        if (is_array($object) === false) {
            $result = (array) $object;
        }

        return $result;
    }//end getObject()

    /**
     * Gets objects based on the object type and various parameters.
     *
     * @param string      $objectType The type of objects to retrieve.
     * @param int|null    $limit      The maximum number of objects to retrieve.
     * @param int|null    $offset     The offset from which to start retrieving objects.
     * @param array|null  $filters    Filters to apply to the query.
     * @param array|null  $sort       Sorting parameters for the query.
     * @param string|null $search     Search string.
     * @param array|null  $extend     Additional parameters for extending the query.
     *
     * @return array The retrieved objects as arrays.
     *
     * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
     * @throws InvalidArgumentException If extend is requested for non-OpenRegister objects.
     *
     * @psalm-suppress PossiblyUnusedParam Params forwarded to dynamic mapper findAll() via named args.
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedReturnStatement Mapper resolved dynamically via getMapper().
     */
    public function getObjects(
        string $objectType,
        ?int $limit=null,
        ?int $offset=null,
        ?array $filters=[],
        ?array $sort=[],
        ?string $search=null,
        ?array $extend=[]
    ): array {
        // Get the appropriate mapper for the object type.
        $mapper = $this->getMapper(objectType: $objectType);

        // Check if extend is requested for non-OpenRegister objects.
        if ($extend !== null && count($extend) > 0
            && ($mapper instanceof \OCA\OpenRegister\Service\ObjectService) === false
        ) {
            throw new InvalidArgumentException('Extend functionality is only available for OpenRegister objects');
        }

        // Use the mapper to find and return the objects based on the provided parameters.
        // @psalm-suppress MixedAssignment Mapper resolved dynamically.
        $objects = $mapper->findAll($limit, $offset, $filters, $sort, $search, $extend);

        // Convert entity objects to arrays using jsonSerialize.
        // @psalm-suppress MixedArgument Mapper resolved dynamically.
        return array_map(
            function (mixed $object): array {
                // If object is already an array, return it directly.
                if (is_array($object) === true) {
                    // @var array<string, mixed> $object
                    return $object;
                }

                // Otherwise serialize the object.
                // @var \JsonSerializable $object.
                return $object->jsonSerialize();
            },
            $objects
        );
    }//end getObjects()

    /**
     * Gets facets based on the object type and filters.
     *
     * @param string $objectType The type of objects to retrieve facets for.
     * @param array  $filters    Filters to apply to the query.
     *
     * @return array The retrieved facets as arrays.
     *
     * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
     *
     * @psalm-suppress PossiblyUnusedParam $filters passed to dynamic mapper getAggregations().
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedReturnStatement Mapper resolved dynamically via getMapper().
     */
    public function getFacets(
        string $objectType,
        array $filters=[],
    ): array {
        // Get the appropriate mapper for the object type.
        $mapper = $this->getMapper(objectType: $objectType);

        // Use the mapper to find and return the objects based on the provided parameters.
        if (($mapper instanceof \OCA\OpenRegister\Service\ObjectService) === true) {
            return $mapper->getAggregations($filters);
        }

        return [];
    }//end getFacets()

    /**
     * Gets multiple objects based on the object type and ids.
     *
     * @param string $objectType The type of objects to retrieve.
     * @param array  $ids        The ids of the objects to retrieve.
     *
     * @return array The retrieved objects.
     *
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface If an unknown object type is provided.
     *
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedReturnStatement Mapper resolved dynamically via getMapper().
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function getMultipleObjects(string $objectType, array $ids): array
    {
        // Process the ids.
        $processedIds = array_map(
            function (mixed $id): mixed {
                if (is_object($id) === true && method_exists($id, 'getId') === true) {
                    return $id->getId();
                } else if (is_array($id) === true && isset($id['id']) === true) {
                    return $id['id'];
                } else {
                    return $id;
                }
            },
            $ids
        );

        // Clean up the ids if they are URIs.
        $cleanedIds = array_map(
            function ($id) {
                // If the id is a URI, get only the last part of the path.
                if (filter_var($id, FILTER_VALIDATE_URL) !== false) {
                    $parts = explode('/', rtrim((string) $id, '/'));
                    return end($parts);
                }

                return $id;
            },
            $processedIds
        );

        // Get the appropriate mapper for the object type.
        $mapper = $this->getMapper(objectType: $objectType);

        // Use the mapper to find and return multiple objects based on the provided cleaned ids.
        // @var array $multipleResults.
        $multipleResults = $mapper->findMultiple($cleanedIds);
        return $multipleResults;
    }//end getMultipleObjects()

    /**
     * Gets all objects of a specific type.
     *
     * @param string   $objectType The type of objects to retrieve.
     * @param int|null $limit      The maximum number of objects to retrieve.
     * @param int|null $offset     The offset from which to start retrieving objects.
     *
     * @return array The retrieved objects.
     *
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface If an unknown object type is provided.
     *
     * @psalm-suppress PossiblyUnusedMethod Public API method for batch object retrieval.
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedReturnStatement Mapper resolved dynamically via getMapper().
     */
    public function getAllObjects(string $objectType, ?int $limit=null, ?int $offset=null): array
    {
        // Get the appropriate mapper for the object type.
        $mapper = $this->getMapper(objectType: $objectType);

        // Use the mapper to find and return all objects of the specified type.
        // @var array $allResults.
        $allResults = $mapper->findAll($limit, $offset);
        return $allResults;
    }//end getAllObjects()

    /**
     * Creates a new object or updates an existing one from an array of data.
     *
     * @param string $objectType    The type of object to create or update.
     * @param array  $object        The data to create or update the object from.
     * @param array  $extend        Optional array of properties to extend.
     * @param bool   $updateVersion If we should update the version or not, default = true.
     *
     * @return mixed The created or updated object.
     *
     * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
     *
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function saveObject(string $objectType, array $object, array $extend=[], bool $updateVersion=true): mixed
    {
        // Get the appropriate mapper for the object type.
        $mapper = $this->getMapper(objectType: $objectType);
        // If the object has an id, update it; otherwise, create a new object.
        if (isset($object['id']) === true) {
            // @var array<string,mixed>|\OCP\AppFramework\Db\Entity $saveResult
            $saveResult = $mapper->updateFromArray($object['id'], $object, $updateVersion, $extend);
            return $saveResult;
        } else {
            // @var array<string,mixed>|\OCP\AppFramework\Db\Entity $saveResult
            $saveResult = $mapper->createFromArray($object, $extend);
            return $saveResult;
        }
    }//end saveObject()

    /**
     * Deletes an object based on the object type and id.
     *
     * @param string     $objectType The type of object to delete.
     * @param string|int $id         The id of the object to delete.
     *
     * @return bool True if the object was successfully deleted, false otherwise.
     *
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface|\OCP\DB\Exception If unknown type.
     *
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     */
    public function deleteObject(string $objectType, string|int $id): bool
    {
        // Get the appropriate mapper for the object type.
        $mapper = $this->getMapper(objectType: $objectType);

        // Use the mapper to get and delete the object.
        try {
            // @var \OCP\AppFramework\Db\Entity $object.
            $object = $mapper->find($id);
            $mapper->delete($object);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }//end deleteObject()

    /**
     * Attempts to retrieve the OpenRegister service from the container.
     *
     * @return object|null The OpenRegister service if available, null otherwise.
     *
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function getOpenRegisters(): ?object
    {
        if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === true) {
            try {
                // Attempt to get the OpenRegister service from the container.
                // @var object.
                return $this->container->get('OCA\OpenRegister\Service\ObjectService');
            } catch (Exception $e) {
                // If the service is not available, return null.
                return null;
            }
        }

        return null;
    }//end getOpenRegisters()

    /**
     * Gets the count of objects for a given type and filters.
     *
     * @param string $objectType The type of objects to count.
     * @param array  $filters    Filters to apply to the count query.
     *
     * @return int The number of objects matching the filters.
     *
     * @psalm-suppress UnusedParam $filters used conditionally when mapper is OpenRegister.
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedReturnStatement Mapper resolved dynamically via getMapper().
     */
    public function getCount(string $objectType, array $filters=[]): int
    {
        $mapper = $this->getMapper(objectType: $objectType);
        if (($mapper instanceof \OCA\OpenRegister\Service\ObjectService) === true) {
            return $mapper->count($filters);
        }

        return 0;
    }//end getCount()

    /**
     * Get a result array for a request based on the request and the object type.
     *
     * @param string $objectType    The type of object to retrieve.
     * @param array  $requestParams The request parameters.
     *
     * @return array The result array containing objects and total count.
     *
     * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
     * @throws InvalidArgumentException If extend is requested for non-OpenRegister objects.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getResultArrayForRequest(string $objectType, array $requestParams): array
    {
        // Extract specific parameters.
        // @var int|null $limit.
        $limit = $requestParams['limit'] ?? $requestParams['_limit'] ?? null;

        // @var int|null $offset
        $offset = $requestParams['offset'] ?? $requestParams['_offset'] ?? null;

        // @var array|string $order
        $order = $requestParams['order'] ?? $requestParams['_order'] ?? [];

        // @var array|string|null $extend
        $extend = $requestParams['extend'] ?? $requestParams['_extend'] ?? null;

        // @var int|null $page
        $page = $requestParams['page'] ?? $requestParams['_page'] ?? null;

        // @var string|null $search
        $search = $requestParams['search'] ?? $requestParams['_search'] ?? null;

        // If page is set, calculate the offset.
        if ($page !== null && isset($limit) === true) {
            $offset = $limit * ($page - 1);
        }

        // Ensure order and extend are arrays.
        if (is_string($order) === true) {
            $order = array_map('trim', explode(',', $order));
        }

        if (is_string($extend) === true) {
            $extend = array_map('trim', explode(',', $extend));
        }

        // Remove unnecessary parameters from filters.
        $filters = $requestParams;
        unset($filters['_route']);
        // Nextcloud automatically adds this.
        unset($filters['_extend'], $filters['_limit'], $filters['_offset'], $filters['_order'], $filters['_page']);
        unset($filters['extend'], $filters['limit'], $filters['offset'], $filters['order'], $filters['page']);

        // Fetch objects based on filters and order.
        $extendArray = null;
        if (is_array($extend) === true) {
            $extendArray = $extend;
        }

        $objects = $this->getObjects(
            objectType: $objectType,
            limit: $limit,
            offset: $offset,
            filters: $filters,
            sort: $order,
            extend: $extendArray,
            search: $search
        );

        $facets = $this->getFacets(objectType: $objectType, filters: $filters);

        return [
            'results' => $objects,
            'facets'  => $facets,
            'total'   => $this->getCount(objectType: $objectType, filters: $filters),
        ];
    }//end getResultArrayForRequest()

    /**
     * Extends an entity with related objects based on the extend array.
     *
     * @param mixed $entity The entity to extend.
     * @param array $extend An array of properties to extend.
     *
     * @return array The extended entity as an array.
     *
     * @throws ContainerExceptionInterface|DoesNotExistException|MultipleObjectsReturnedException|NotFoundExceptionInterface
     *         If a property is not present on the entity.
     *
     * @psalm-suppress PossiblyUnusedMethod Public API for extending entities with related objects.
     * @psalm-suppress MixedMethodCall Mapper/entity resolved dynamically.
     * @psalm-suppress MixedReturnStatement Entity may return mixed from jsonSerialize().
     * @psalm-suppress MixedArrayOffset Entity properties are dynamic string keys.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function extendEntity(mixed $entity, array $extend): array
    {
        $surpressMapperError = false;
        // Convert the entity to an array if it's not already one.
        if (is_array($entity) === true) {
            $result = $entity;
        } else {
            // @var \JsonSerializable $entityObject.
            $entityObject = $entity;

            // @var array<string,mixed> $result.
            $result = $entityObject->jsonSerialize();
        }

        if (in_array(needle: 'all', haystack: $extend) === true) {
            // @var array<string,mixed> $entityArray
            $entityArray = $entity;
            $extend      = array_keys($entityArray);
            $surpressMapperError = true;
        }

        // Iterate through each property to be extended.
        // @psalm-suppress MixedAssignment Extend array values are strings.
        foreach ($extend as $property) {
            // Create a singular property name.
            $singularProperty = rtrim((string) $property, 's');

            // Check if property or singular property are keys in the array.
            // @var string $property.
            if (array_key_exists($property, $result) === true) {
                // @var mixed $value
                $value = $result[$property];
                if (empty($value) === true) {
                    continue;
                }
            } else if (array_key_exists($singularProperty, $result) === true) {
                // @var mixed $value
                $value = $result[$singularProperty];
            } else {
                throw new Exception("Property '$property' or '$singularProperty' is not present in the entity.");
            }

            // Get a mapper for the property (validates the type exists).
            $propertyStr    = $property;
            $propertyObject = $propertyStr;
            try {
                $this->getMapper(objectType: $propertyStr);
                $propertyObject = $singularProperty;
            } catch (Exception $e) {
                try {
                    $this->getMapper(objectType: $singularProperty);
                    $propertyObject = $singularProperty;
                } catch (Exception $e) {
                    // If still no mapper, throw a no mapper available error.
                    if ($surpressMapperError === true) {
                        continue;
                    }

                    throw new Exception("No mapper available for property '$property'.");
                }
            }

            // Update the values.
            if (is_array($value) === true) {
                // If the value is an array, get multiple related objects.
                $result[$property] = $this->getMultipleObjects(
                    objectType: $propertyObject,
                    ids: $value
                );
            } else {
                // If the value is not an array, get a single related object.
                // @psalm-suppress MixedAssignment Value from entity array.
                $objectId = $value;
                if (is_object($value) === true) {
                    // @var \OCP\AppFramework\Db\Entity $value.
                    $objectId = (string) $value->getId();
                }

                $result[$property] = $this->getObject(
                    objectType: $propertyObject,
                    id: (string) $objectId
                );
            }//end if
        }//end foreach

        // Return the extended entity as an array.
        return $result;
    }//end extendEntity()

    /**
     * Get all relations for a specific object.
     *
     * @param string $objectType The type of object to get relations for.
     * @param string $id         The id of the object to get relations for.
     *
     * @return array The relations for the object.
     *
     * @throws Exception If OpenRegister service is not available.
     *
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     */
    public function getRelations(string $objectType, string $id): array
    {
        // Get the mapper first.
        $mapper = $this->getMapper(objectType: $objectType);

        // Get audit trails from OpenRegister.
        // @var array $auditTrails.
        $auditTrails = $mapper->getRelations($id);

        return $auditTrails;
    }//end getRelations()

    /**
     * Get all the uses that a specific object has.
     *
     * @param string $objectType The type of object to get uses for.
     * @param string $id         The id of the object to get uses for.
     *
     * @return array The uses for the object.
     *
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     */
    public function getUses(string $objectType, string $id): array
    {
        $mapper = $this->getMapper(objectType: $objectType);

        // @var array $uses
        $uses = $mapper->getUses($id);
        return $uses;
    }//end getUses()

    /**
     * Get all files associated with a specific object.
     *
     * @param string $objectType The type of object.
     * @param string $id         The id of the object.
     *
     * @return array The files associated with the object.
     *
     * @psalm-suppress PossiblyUnusedMethod Called from ObjectsController::getFiles route handler.
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     */
    public function getFiles(string $objectType, string $id): array
    {
        // Get the mapper first.
        $mapper = $this->getMapper(objectType: $objectType);

        // @var array $files
        $files = $mapper->formatFiles($mapper->getFiles($id));
        return $files;
    }//end getFiles()

    /**
     * Get all audit trails for a specific object.
     *
     * @param string $objectType The type of object to get audit trails for.
     * @param string $id         The id of the object to get audit trails for.
     *
     * @return array The audit trails for the object.
     *
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     */
    public function getAuditTrail(string $objectType, string $id): array
    {
        // Get the mapper first.
        $mapper = $this->getMapper(objectType: $objectType);

        // Get audit trails from OpenRegister.
        // @var array $auditTrails.
        $auditTrails = $mapper->getAuditTrail($id);

        return $auditTrails;
    }//end getAuditTrail()

    /**
     * Lock an object.
     *
     * @param string      $objectType The type of object to lock.
     * @param string|int  $id         The id of the object to lock.
     * @param string|null $process    Optional process identifier.
     * @param int|null    $duration   Lock duration in seconds (default: 1 hour).
     *
     * @return array<string,mixed> The locked object.
     *
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     */
    public function lockObject(string $objectType, string|int $id, ?string $process=null, ?int $duration=3600): array
    {
        $mapper = $this->getMapper(objectType: $objectType);

        // @var array<string,mixed> $result
        $result = $mapper->lockObject($id, $process, $duration);
        return $result;
    }//end lockObject()

    /**
     * Unlock an object.
     *
     * @param string     $objectType The type of object to unlock.
     * @param string|int $id         The id of the object to unlock.
     *
     * @return array<string,mixed> The unlocked object.
     *
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     */
    public function unlockObject(string $objectType, string|int $id): array
    {
        $mapper = $this->getMapper(objectType: $objectType);

        // @var array<string,mixed> $result
        $result = $mapper->unlockObject($id);
        return $result;
    }//end unlockObject()

    /**
     * Check if an object is locked.
     *
     * @param string     $objectType The type of object to check.
     * @param string|int $id         The id of the object to check.
     *
     * @return bool True if object is locked, false otherwise.
     *
     * @psalm-suppress PossiblyUnusedMethod Public API for checking object lock state.
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedReturnStatement Mapper resolved dynamically via getMapper().
     */
    public function isLocked(string $objectType, string|int $id): bool
    {
        // @var \OCA\OpenRegister\Service\ObjectService $mapper
        $mapper = $this->getMapper(objectType: $objectType);

        // @var bool $locked
        $locked = $mapper->isLocked($id);
        return $locked;
    }//end isLocked()

    /**
     * Revert an object to a previous state.
     *
     * @param string               $objectType       The type of object to revert.
     * @param string|int           $id               The id of the object to revert.
     * @param DateTime|string|null $until            DateTime or AuditTrail ID to revert to.
     * @param bool                 $overwriteVersion Whether to overwrite the version or increment it.
     *
     * @return array<string,mixed> The reverted object.
     *
     * @psalm-suppress MixedInferredReturnType Return type depends on dynamic mapper resolution.
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function revertObject(string $objectType, string|int $id, $until=null, bool $overwriteVersion=false): array
    {
        $mapper = $this->getMapper(objectType: $objectType);

        // @var array<string,mixed> $result
        $result = $mapper->revertObject($id, $until, $overwriteVersion);
        return $result;
    }//end revertObject()
}//end class
