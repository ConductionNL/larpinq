<?php

/**
 * RegisterObjectFetcher for LarpingApp
 *
 * Thin wrapper around OpenRegister's ObjectService for fetching
 * register objects by type. Replaces the deleted generic ObjectService
 * proxy layer with direct cross-app calls.
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

use Exception;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use Psr\Container\ContainerInterface;

/**
 * Fetches objects from OpenRegister by resolving register/schema
 * IDs from the app configuration per object type.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UndefinedClass, UndefinedDocblockClass OpenRegister is an optional dependency.
 */
class RegisterObjectFetcher
{

    /**
     * The name of the application.
     *
     * @var string
     */
    private string $appName = 'larpingapp';

    /**
     * Cached OpenRegister ObjectService instance.
     *
     * @var object|null
     */
    private ?object $openRegisterService = null;

    /**
     * Constructor for RegisterObjectFetcher.
     *
     * @param ContainerInterface $container  DI container.
     * @param IAppManager        $appManager App manager.
     * @param IAppConfig         $config     Config service.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly IAppManager $appManager,
        private readonly IAppConfig $config
    ) {
    }//end __construct()

    /**
     * Get the OpenRegister ObjectService.
     *
     * @return object The OpenRegister ObjectService.
     *
     * @throws Exception If OpenRegister is not installed or not available.
     *
     * @psalm-suppress MixedAssignment OpenRegister resolved dynamically.
     */
    private function getOpenRegisterService(): object
    {
        if ($this->openRegisterService !== null) {
            return $this->openRegisterService;
        }

        if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === false) {
            throw new Exception('OpenRegister app is not installed');
        }

        // @var object $service
        $service = $this->container->get('OCA\OpenRegister\Service\ObjectService');
        $this->openRegisterService = $service;
        return $this->openRegisterService;
    }//end getOpenRegisterService()

    /**
     * Get the OpenRegister mapper for a given object type.
     *
     * Resolves the register and schema IDs from app configuration,
     * then obtains the mapper from OpenRegister's ObjectService.
     *
     * @param string $objectType The object type (e.g. 'skill', 'character').
     *
     * @return object The OpenRegister mapper for the given type.
     *
     * @throws Exception If register or schema is not configured.
     *
     * @psalm-suppress MixedMethodCall OpenRegister is an optional cross-app dependency.
     */
    private function getMapper(string $objectType): object
    {
        $objectTypeLower = strtolower($objectType);
        $openRegister    = $this->getOpenRegisterService();

        $register = $this->config->getValueString($this->appName, $objectTypeLower.'_register', '');
        if (empty($register) === true) {
            throw new Exception("Register not configured for $objectTypeLower");
        }

        $schema = $this->config->getValueString($this->appName, $objectTypeLower.'_schema', '');
        if (empty($schema) === true) {
            throw new Exception("Schema not configured for $objectTypeLower");
        }

        // @var object $mapper
        $mapper = $openRegister->getMapper($register, $schema);
        return $mapper;
    }//end getMapper()

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
     * Get all objects of a given type from OpenRegister.
     *
     * @param string      $objectType The object type (e.g. 'skill', 'character').
     * @param int|null    $limit      Maximum number of objects to retrieve.
     * @param int|null    $offset     Offset for pagination.
     * @param array|null  $filters    Filters to apply.
     * @param array|null  $sort       Sort parameters.
     * @param string|null $search     Search string.
     *
     * @return array<int, array<string, mixed>> The objects as arrays.
     *
     * @throws Exception If OpenRegister is not available or type is not configured.
     *
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedAssignment Mapper resolved dynamically.
     */
    public function getObjects(
        string $objectType,
        ?int $limit=null,
        ?int $offset=null,
        ?array $filters=[],
        ?array $sort=[],
        ?string $search=null
    ): array {
        $mapper = $this->getMapper(objectType: $objectType);

        // @var array $objects
        $objects = $mapper->findAll($limit, $offset, $filters, $sort, $search);

        // @psalm-suppress MixedArgument Mapper resolved dynamically.
        return array_map(
            function (mixed $object): array {
                return $this->toArray(object: $object);
            },
            $objects
        );
    }//end getObjects()

    /**
     * Get a single object by type and ID from OpenRegister.
     *
     * @param string $objectType The object type (e.g. 'character').
     * @param string $id         The object ID.
     *
     * @return array<string,mixed> The object as an array.
     *
     * @throws Exception If OpenRegister is not available or type is not configured.
     *
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedAssignment Mapper resolved dynamically.
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function getObject(string $objectType, string $id): array
    {
        $mapper = $this->getMapper(objectType: $objectType);

        // Clean URI-format IDs.
        if (filter_var($id, FILTER_VALIDATE_URL) !== false) {
            $parts = explode('/', rtrim($id, '/'));
            $id    = end($parts);
        }

        // @var mixed $object
        $object = $mapper->find($id);

        return $this->toArray(object: $object);
    }//end getObject()
}//end class
