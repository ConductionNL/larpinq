<?php

/**
 * ObjectLifecycleService for LarpingApp
 *
 * Handles object lifecycle operations: locking, unlocking, reverting,
 * audit trails, relations, uses, and files.
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
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Service for object lifecycle operations.
 *
 * @category Service
 * @package  OCA\LarpingApp\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UnusedClass Injected via Nextcloud DI container.
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class ObjectLifecycleService
{
    /**
     * Constructor for ObjectLifecycleService.
     *
     * @param ObjectService $objectService The object service.
     *
     * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
     */
    public function __construct(
        private ObjectService $objectService
    ) {
    }//end __construct()

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
        $mapper = $this->objectService->getMapper(objectType: $objectType);
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
        $mapper = $this->objectService->getMapper(objectType: $objectType);
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
        $mapper = $this->objectService->getMapper(objectType: $objectType);
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
        $mapper = $this->objectService->getMapper(objectType: $objectType);
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
        $mapper = $this->objectService->getMapper(objectType: $objectType);
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
        $mapper = $this->objectService->getMapper(objectType: $objectType);
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
        $mapper = $this->objectService->getMapper(objectType: $objectType);
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
        $mapper = $this->objectService->getMapper(objectType: $objectType);
        // @var array<string,mixed> $result
        $result = $mapper->revertObject($id, $until, $overwriteVersion);
        return $result;
    }//end revertObject()
}//end class
