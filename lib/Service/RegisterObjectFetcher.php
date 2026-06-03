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
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
 */

declare(strict_types=1);

namespace OCA\LarpingApp\Service;

use Exception;
use InvalidArgumentException;
use OCA\LarpingApp\AppInfo\Application;
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
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
 */
class RegisterObjectFetcher
{

    /**
     * The name of the application.
     *
     * @var string
     */
    private string $appName = Application::APP_ID;

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
     * The ObjectService is resolved fresh from the DI container on every call
     * to prevent stale-state cross-contamination. OR's ObjectService mutates
     * currentRegister/currentSchema on each operation; caching a single instance
     * leaks state between calls when multiple object types are fetched in the
     * same request (closes #207).
     *
     * @return object The OpenRegister ObjectService.
     *
     * @throws Exception If OpenRegister is not installed or not available.
     *
     * @psalm-suppress MixedAssignment OpenRegister resolved dynamically.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-39
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-40
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-41
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-42
     */
    private function getOpenRegisterService(): object
    {
        if (in_array(needle: 'openregister', haystack: $this->appManager->getInstalledApps()) === false) {
            throw new Exception('OpenRegister app is not installed');
        }

        // Resolve fresh on every call — never cache. OR's ObjectService mutates
        // currentRegister/currentSchema on each getMapper() call; caching the
        // instance causes stale-state contamination between object types in the
        // same request. The adapter already binds register/schema on getMapper(),
        // so the fresh-resolve is belt-and-braces against future OR refactoring.
        // @var object $service The resolved OpenRegister ObjectService.
        $service = $this->container->get('OCA\OpenRegister\Service\ObjectService');
        return $service;
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
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-43
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-44
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-45
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-46
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-47
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-48
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-49
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
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-59
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-60
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-61
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-62
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-63
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
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-50
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-51
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-52
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-53
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
        $objects = $mapper->findAll(
                [
                    'limit'   => $limit,
                    'offset'  => $offset,
                    'filters' => $filters ?? [],
                    'sort'    => $sort ?? [],
                    'search'  => $search,
                ]
                );

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
     * The `$id` parameter must be a valid UUID. URI-format IDs (full URLs) are
     * no longer accepted — the previous URL-slicing behaviour silently derived
     * a lookup ID from any valid URL regardless of domain or register scope,
     * providing an IDOR primitive. Callers that previously passed URLs must
     * normalise to a UUID before calling. Closes #212.
     *
     * @param string $objectType The object type (e.g. 'character').
     * @param string $id         The object UUID.
     *
     * @return array<string,mixed> The object as an array.
     *
     * @throws Exception If OpenRegister is not available, type is not configured,
     *                   or the ID is not a valid UUID.
     *
     * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
     * @psalm-suppress MixedAssignment Mapper resolved dynamically.
     *
     * @SuppressWarnings(PHPMD.ShortVariable)
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-54
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-55
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-56
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-57
     * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-58
     */
    public function getObject(string $objectType, string $id): array
    {
        // Require a clean UUID. Reject URL-format IDs to close the URL-slicing
        // IDOR primitive (closes #212). Any caller that previously passed a full
        // URL must extract the UUID before calling this method.
        // Normalise to lower-case so mixed-case UUIDs (e.g. from Windows GUIDs)
        // pass validation and reach the mapper without the case-insensitive /i flag.
        $idLower = strtolower($id);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $idLower) !== 1) {
            throw new InvalidArgumentException('Invalid object ID: expected a UUID');
        }

        $mapper = $this->getMapper(objectType: $objectType);

        // @var mixed $object
        $object = $mapper->find($idLower);

        return $this->toArray(object: $object);
    }//end getObject()
}//end class
