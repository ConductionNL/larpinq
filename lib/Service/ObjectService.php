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

use DateTime;
use Exception;
use InvalidArgumentException;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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
     * @return object The mapper.
     *
     * @throws InvalidArgumentException If an unknown object type is provided.
     * @throws NotFoundExceptionInterface|ContainerExceptionInterface If OpenRegister service is not available.
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

        // If the source is 'openregister', use the OpenRegister service.
        if ($source === 'openregister') {
            return $this->getOpenRegisterMapper(objectType: $objectTypeLower);
        }

        // If the source is internal, return the appropriate mapper.
        return $this->getInternalMapper(objectType: $objectType);
    }//end getMapper()

    /**
     * Get the OpenRegister mapper for a given object type.
     *
     * @param string $objectType The lowercased object type.
     *
     * @return object The OpenRegister mapper.
     *
     * @throws Exception If OpenRegister is not available or not configured.
     *
     * @psalm-suppress MixedMethodCall OpenRegister is an optional cross-app dependency.
     */
    private function getOpenRegisterMapper(string $objectType): object
    {
        $openRegister = $this->getOpenRegisters();
        if ($openRegister === null) {
            throw new Exception("OpenRegister service not available");
        }

        $register = $this->config->getValueString($this->appName, $objectType.'_register', '');
        if (empty($register) === true) {
            throw new Exception("Register not configured for $objectType");
        }

        $schema = $this->config->getValueString($this->appName, $objectType.'_schema', '');
        if (empty($schema) === true) {
            throw new Exception("Schema not configured for $objectType");
        }

        // @var object $orMapper
        $orMapper = $openRegister->getMapper($register, $schema);
        return $orMapper;
    }//end getOpenRegisterMapper()

    /**
     * Get the internal mapper for a given object type.
     *
     * @param string $objectType The object type.
     *
     * @return object The internal mapper.
     *
     * @throws InvalidArgumentException If an unknown object type is provided.
     */
    private function getInternalMapper(string $objectType): object
    {
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
    }//end getInternalMapper()

    /**
     * Convert an object to an array.
     *
     * @param mixed $object The object to convert.
     *
     * @return array<string,mixed> The object as an array.
     *
     * @psalm-suppress MixedAssignment Mapper resolved dynamically.
     */
    private function toArray(mixed $object): array
    {
        if (is_object($object) === true && method_exists($object, 'jsonSerialize') === true) {
            return $object->jsonSerialize();
        }

        if (is_array($object) === true) {
            return $object;
        }

        return (array) $object;
    }//end toArray()

    /**
     * Clean up an ID, extracting the last path part if it's a URI.
     *
     * @param string $id The ID to clean.
     *
     * @return string The cleaned ID.
     */
    private function cleanId(string $id): string
    {
        if (filter_var($id, FILTER_VALIDATE_URL) !== false) {
            $parts = explode('/', rtrim($id, '/'));
            return end($parts);
        }

        return $id;
    }//end cleanId()

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
        $id     = $this->cleanId(id: $id);
        $mapper = $this->getMapper(objectType: $objectType);

        if (empty($extend) === false && ($mapper instanceof \OCA\OpenRegister\Service\ObjectService) === false) {
            throw new InvalidArgumentException('Extend functionality is only available for OpenRegister objects');
        }

        // @psalm-suppress MixedAssignment Mapper resolved dynamically.
        $object = $mapper->find($id);

        return $this->toArray(object: $object);
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
        $mapper = $this->getMapper(objectType: $objectType);

        if ($extend !== null && count($extend) > 0
            && ($mapper instanceof \OCA\OpenRegister\Service\ObjectService) === false
        ) {
            throw new InvalidArgumentException('Extend functionality is only available for OpenRegister objects');
        }

        // @psalm-suppress MixedAssignment Mapper resolved dynamically.
        $objects = $mapper->findAll($limit, $offset, $filters, $sort, $search, $extend);

        // @psalm-suppress MixedArgument Mapper resolved dynamically.
        return array_map(
            function (mixed $object): array {
                return $this->toArray(object: $object);
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
        $mapper = $this->getMapper(objectType: $objectType);
        if (($mapper instanceof \OCA\OpenRegister\Service\ObjectService) === true) {
            return $mapper->getAggregations($filters);
        }

        return [];
    }//end getFacets()

    /**
     * Process and clean an array of IDs.
     *
     * Extracts IDs from objects/arrays and cleans URI-format IDs.
     *
     * @param array $ids The raw IDs to process.
     *
     * @return array The cleaned IDs.
     *
     */
    private function processAndCleanIds(array $ids): array
    {
        $processedIds = array_map(
            function (mixed $id): mixed {
                if (is_object($id) === true && method_exists($id, 'getId') === true) {
                    return $id->getId();
                }

                if (is_array($id) === true && isset($id['id']) === true) {
                    return $id['id'];
                }

                return $id;
            },
            $ids
        );

        return array_map(
            function ($id) {
                return $this->cleanId(id: (string) $id);
            },
            $processedIds
        );
    }//end processAndCleanIds()

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
     */
    public function getMultipleObjects(string $objectType, array $ids): array
    {
        $cleanedIds = $this->processAndCleanIds(ids: $ids);
        $mapper     = $this->getMapper(objectType: $objectType);

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
        $mapper = $this->getMapper(objectType: $objectType);
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
     */
    public function saveObject(string $objectType, array $object, array $extend=[], bool $updateVersion=true): mixed
    {
        $mapper = $this->getMapper(objectType: $objectType);
        if (isset($object['id']) === true) {
            // @var array<string,mixed>|\OCP\AppFramework\Db\Entity $saveResult
            $saveResult = $mapper->updateFromArray($object['id'], $object, $updateVersion, $extend);
            return $saveResult;
        }

        // @var array<string,mixed>|\OCP\AppFramework\Db\Entity $saveResult
        $saveResult = $mapper->createFromArray($object, $extend);
        return $saveResult;
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
        $mapper = $this->getMapper(objectType: $objectType);
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
                // @var object.
                return $this->container->get('OCA\OpenRegister\Service\ObjectService');
            } catch (Exception $e) {
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
}//end class
