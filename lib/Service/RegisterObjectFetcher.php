<?php

/**
 * RegisterObjectFetcher for Larpinq
 *
 * Thin wrapper around OpenRegister's ObjectService for fetching
 * register objects by type. Replaces the deleted generic ObjectService
 * proxy layer with direct cross-app calls.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Exception;
use InvalidArgumentException;
use OCA\Larpinq\Kernel\Contracts\RegisterObjectFetcherInterface;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectDeletion;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindTarget;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetches objects from OpenRegister by resolving register/schema
 * IDs from the app configuration per object type.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UndefinedClass, UndefinedDocblockClass OpenRegister is an optional dependency.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
 */
class RegisterObjectFetcher implements RegisterObjectFetcherInterface {

	/**
	 * Resolves the register and schema ids configured per object type.
	 *
	 * @var RegisterSchemaLookup
	 */
	private readonly RegisterSchemaLookup $schemaLookup;

	/**
	 * Constructor for RegisterObjectFetcher.
	 *
	 * @param ContainerInterface $container DI container.
	 * @param IAppManager $appManager App manager.
	 * @param IAppConfig $config Config service.
	 * @param LoggerInterface $logger Logger for the resolver fallback path.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly IAppManager $appManager,
		IAppConfig $config,
		LoggerInterface $logger,
	) {
		$this->schemaLookup = new RegisterSchemaLookup(container: $container, config: $config, logger: $logger);
	}//end __construct()

	/**
	 * Resolve the configured register and schema IDs for an object type.
	 *
	 * Prefers OpenRegister's RegisterResolverService (real API:
	 * resolveRegisterId / resolveSchemaId, keyed by the `{type}_register` /
	 * `{type}_schema` IAppConfig convention) when it is available. Falls back
	 * to a direct IAppConfig::getValueString lookup — preserving the exact
	 * legacy behaviour, including the "not configured" exceptions — when the
	 * resolver is absent (older OpenRegister, or during an upgrade window).
	 *
	 * @param string $objectTypeLower The lower-cased object type slug.
	 *
	 * @return array{0: string, 1: string} A [registerId, schemaId] tuple.
	 *
	 * @throws Exception If the register or schema is not configured.
	 *
	 * @psalm-suppress MixedMethodCall    The resolver is an optional cross-app dependency.
	 * @psalm-suppress MixedAssignment    The resolver is an optional cross-app dependency.
	 * @psalm-suppress MixedArgument      The resolver is an optional cross-app dependency.
	 */
	private function resolveRegisterAndSchema(string $objectTypeLower): array {
		return $this->schemaLookup->resolve(objectTypeLower: $objectTypeLower);
	}//end resolveRegisterAndSchema()

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
	private function getOpenRegisterService(): object {
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
	private function getMapper(string $objectType): object {
		$objectTypeLower = strtolower($objectType);
		$openRegister = $this->getOpenRegisterService();

		[$register, $schema] = $this->resolveRegisterAndSchema(objectTypeLower: $objectTypeLower);

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
	private function toArray(mixed $object): array {
		if (is_object($object) === true && method_exists($object, 'jsonSerialize') === true) {
			return $object->jsonSerialize();
		}

		if (is_array($object) === true) {
			return $object;
		}

		return (array)$object;
	}//end toArray()

	/**
	 * Convert a list of objects to arrays.
	 *
	 * @param mixed $objects The objects OpenRegister returned.
	 *
	 * @return array<int, array<string, mixed>> The objects as arrays.
	 *
	 * @psalm-suppress MixedArgument OpenRegister resolved dynamically.
	 */
	private function toArrays(mixed $objects): array {
		return array_map(
			function (mixed $object): array {
				return $this->toArray(object: $object);
			},
			$objects
		);
	}//end toArrays()

	/**
	 * Get all objects of a given type from OpenRegister.
	 *
	 * @param string $objectType The object type (e.g. 'skill', 'character').
	 * @param int|null $limit Maximum number of objects to retrieve.
	 * @param int|null $offset Offset for pagination.
	 * @param array|null $filters Filters to apply.
	 * @param array|null $sort Sort parameters.
	 * @param string|null $search Search string.
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
		?int $limit = null,
		?int $offset = null,
		?array $filters = [],
		?array $sort = [],
		?string $search = null,
	): array {
		$config = ['limit' => $limit, 'offset' => $offset, 'filters' => $filters ?? [], 'sort' => $sort ?? [], 'search' => $search];

		return $this->toArrays(objects: $this->getMapper(objectType: $objectType)->findAll($config));
	}//end getObjects()

	/**
	 * Read objects of a type with the app's authority: OpenRegister's RBAC and
	 * multitenancy are off for this read.
	 *
	 * Only for records a caller may see through the app's pages but not through
	 * the object API (DECISIONS row 30: a player sees the XP awards on their own
	 * character). The caller MUST have made larpinq's own ownership check first
	 * and MUST pass a filter that scopes the read to what that check covered.
	 * An empty filter is refused, so this can never become an unscoped read.
	 *
	 * @param string $objectType The object type (e.g. 'xpAward').
	 * @param array<string, string|int> $filters Equality filters; at least one, non-empty.
	 * @param int|null $limit Maximum number of objects to retrieve.
	 *
	 * @return array<int, array<string, mixed>> The objects as arrays.
	 *
	 * @throws InvalidArgumentException If no scoping filter is given.
	 * @throws Exception If OpenRegister is not available or type is not configured.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister ObjectService resolved dynamically.
	 * @psalm-suppress MixedAssignment OpenRegister ObjectService resolved dynamically.
	 *
	 * @spec openspec/specs/event-xp-awards/spec.md
	 */
	public function getObjectsWithAppAuthority(string $objectType, array $filters, ?int $limit = null): array {
		if ($filters === [] || in_array('', $filters, true) === true) {
			throw new InvalidArgumentException('A read with the app\'s authority needs a scoping filter');
		}

		$openRegister = $this->getOpenRegisterService();
		[$register, $schema] = $this->resolveRegisterAndSchema(objectTypeLower: strtolower($objectType));

		// @var array $objects
		$objects = $openRegister->findAll(
			config: [
				'limit' => $limit,
				'filters' => array_merge($filters, ['register' => $register, 'schema' => $schema]),
			],
			_rbac: false,
			_multitenancy: false
		);

		return $this->toArrays(objects: $objects);
	}//end getObjectsWithAppAuthority()

	/**
	 * Read objects with the app's authority by the value of a property that
	 * OpenRegister shows only on request (`"reveal": "explicit"`).
	 *
	 * OpenRegister refuses a filter on such a property and masks it in what a
	 * list returns, except inside its explicit-reveal service's trusted scope,
	 * which server code that needs the value on purpose may open. This read
	 * runs in that scope, so the filter is applied and the rows carry the
	 * stored value. The rules of getObjectsWithAppAuthority() apply.
	 *
	 * @param string                    $objectType The object type (e.g. 'registration').
	 * @param array<string, string|int> $filters    Equality filters; at least one, non-empty.
	 * @param int|null                  $limit      Maximum number of objects to retrieve.
	 *
	 * @return array<int, array<string, mixed>> The objects as arrays, values unmasked.
	 *
	 * @throws InvalidArgumentException If no scoping filter is given.
	 * @throws Exception If OpenRegister is not available or type is not configured.
	 *
	 * @spec openspec/changes/events-qr-checkin/specs/event-checkin-roster/spec.md#requirement-a-code-is-checked-in-once-and-only-for-its-event-req-eqc-004
	 */
	public function getObjectsByRevealedValue(string $objectType, array $filters, ?int $limit = null): array {
		$read = fn (): array => $this->getObjectsWithAppAuthority(objectType: $objectType, filters: $filters, limit: $limit);

		try {
			$reveal = $this->container->get('OCA\OpenRegister\Service\Rbac\ExplicitRevealInterface');
		} catch (Exception $e) {
			return $read();
		}

		if (is_object($reveal) === false || method_exists($reveal, 'trustedDuring') === false) {
			return $read();
		}

		// @var array<int, array<string, mixed>> $rows
		$rows = $reveal->trustedDuring($read);
		return $rows;
	}//end getObjectsByRevealedValue()

	/**
	 * Read objects of a type by id, as the caller, skipping the ids that
	 * cannot be read (missing, not readable, or not a uuid).
	 *
	 * @param string            $objectType The object type (e.g. 'character').
	 * @param array<int,string> $ids        The object UUIDs.
	 *
	 * @return list<array<string, mixed>> The readable objects, in the order of `$ids`.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	public function getObjectsById(string $objectType, array $ids): array {
		$objects = [];
		foreach (array_unique(array_filter($ids)) as $id) {
			try {
				$objects[] = $this->getObject(objectType: $objectType, id: $id);
			} catch (Exception $e) {
				continue;
			}
		}

		return $objects;
	}//end getObjectsById()

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
	 * @param string $id The object UUID.
	 *
	 * @return array<string,mixed> The object as an array.
	 *
	 * @throws Exception If OpenRegister is not available, type is not configured,
	 *                   or the ID is not a valid UUID.
	 *
	 * @psalm-suppress MixedMethodCall Mapper resolved dynamically via getMapper().
	 * @psalm-suppress MixedAssignment Mapper resolved dynamically.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-54
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-55
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-56
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-57
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-58
	 */
	public function getObject(string $objectType, string $id): array {
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

	/**
	 * The audit-trail rows of one object, oldest first as OpenRegister keeps them.
	 *
	 * Callers read the object itself first (getObject(), RBAC on as the calling
	 * user), so only someone who may read the object reaches its history.
	 *
	 * @param string $id The object UUID.
	 *
	 * @return array<int, array<string, mixed>> The audit-trail rows as arrays.
	 *
	 * @throws Exception If OpenRegister is not available or the ID is not a valid UUID.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister is an optional cross-app dependency.
	 *
	 * @spec openspec/changes/characters-skill-refund/specs/character-management/spec.md#requirement-the-refund-shows-in-the-xp-history-req-csp-006
	 */
	public function getObjectLogs(string $id): array {
		$idLower = strtolower($id);
		if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $idLower) !== 1) {
			throw new InvalidArgumentException('Invalid object ID: expected a UUID');
		}

		return $this->toArrays(objects: (array)$this->getOpenRegisterService()->getLogs(uuid: $idLower));
	}//end getObjectLogs()

	/**
	 * Get a single object by type and ID with the app's own authority.
	 *
	 * For a server-side step that reads on behalf of a state change another
	 * user triggered, such as copying a player's care details when a game
	 * master accepts the registration: RBAC and multitenancy are off, so the
	 * caller decides what may be read. Property authorization does not strip
	 * fields on such a trusted internal read.
	 *
	 * @param string $objectType The object type (e.g. 'player').
	 * @param string $id The object UUID.
	 *
	 * @return array<string,mixed> The object as an array.
	 *
	 * @throws Exception If OpenRegister is not available, the type is not configured,
	 *                   the ID is not a valid UUID or the object does not exist.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister ObjectService resolved dynamically.
	 * @psalm-suppress MixedAssignment OpenRegister ObjectService resolved dynamically.
	 *
	 * @spec openspec/changes/players-care-details-and-erasure/specs/player-care-details/spec.md#requirement-each-accepted-registration-carries-a-copy-for-the-event-req-pce-002
	 */
	public function getObjectWithAppAuthority(string $objectType, string $id): array {
		$idLower = strtolower($id);
		if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $idLower) !== 1) {
			throw new InvalidArgumentException('Invalid object ID: expected a UUID');
		}

		$openRegister = $this->getOpenRegisterService();
		[$register, $schema] = $this->resolveRegisterAndSchema(objectTypeLower: strtolower($objectType));

		// @var mixed $object
		$object = $openRegister->find(
			id: $idLower,
			options: new ObjectFindOptions(register: $register, schema: $schema),
			_rbac: false,
			_multitenancy: false,
		);
		if ($object === null) {
			throw new DoesNotExistException('Object ' . $id . ' does not exist');
		}

		return $this->toArray(object: $object);
	}//end getObjectWithAppAuthority()

	/**
	 * Get a single object by type and ID, only when it belongs to that type's schema.
	 *
	 * OpenRegister's find() retries a UUID without the schema when the
	 * requested schema does not hold it, so getObject() can answer with an
	 * object of another type. This read treats such an object as missing.
	 *
	 * @param string $objectType The object type (e.g. 'item').
	 * @param string $id The object UUID.
	 *
	 * @return array<string,mixed> The object as an array.
	 *
	 * @throws DoesNotExistException If the object belongs to another schema.
	 * @throws Exception If OpenRegister is not available, the type is not configured,
	 *                   or the ID is not a valid UUID.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function getObjectOfType(string $objectType, string $id): array {
		$object = $this->getObject(objectType: $objectType, id: $id);
		[, $schema] = $this->resolveRegisterAndSchema(objectTypeLower: strtolower($objectType));

		$actual = $object['@self']['schema'] ?? null;
		if (is_scalar($actual) === false || (string)$actual !== $schema) {
			throw new DoesNotExistException('Object ' . $id . ' is not in the ' . $objectType . ' schema');
		}

		return $object;
	}//end getObjectOfType()

	/**
	 * Create or update an object of a given type in OpenRegister.
	 *
	 * Writes through OpenRegister's ObjectService::saveObject so storage,
	 * audit trail and schema-level RBAC (ADR-022) are OR-owned — the app keeps
	 * no parallel write path. RBAC is left enabled (`_rbac: true`), so a schema
	 * whose `authorization.create/update` restricts a group (e.g. the
	 * `larping_attendance` gamemasters restriction) is enforced by OR itself in
	 * addition to any controller-level guard. When `$uuid` is null a new object
	 * is created; otherwise the existing object is updated in place.
	 *
	 * @param string $objectType The object type (e.g. 'attendance').
	 * @param array<string,mixed> $data The object payload to persist.
	 * @param string|null $uuid The UUID to update, or null to create.
	 *
	 * @return array<string,mixed> The persisted object as an array.
	 *
	 * @throws Exception If OpenRegister is not available or the type is not configured.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister ObjectService resolved dynamically.
	 * @psalm-suppress MixedAssignment  OpenRegister ObjectService resolved dynamically.
	 *
	 * @spec openspec/specs/event-checkin-roster/spec.md
	 */
	public function saveObject(string $objectType, array $data, ?string $uuid = null): array {
		$objectTypeLower = strtolower($objectType);
		$openRegister = $this->getOpenRegisterService();

		[$register, $schema] = $this->resolveRegisterAndSchema(objectTypeLower: $objectTypeLower);

		// @var mixed $saved
		$saved = $openRegister->saveObject(
			$data,
			new ObjectSaveOptions(
				target: new ObjectFindTarget(register: $register, schema: $schema),
				uuid: $uuid
			)
		);

		return $this->toArray(object: $saved);
	}//end saveObject()

	/**
	 * Create or update an object with the app's own authority (RBAC off).
	 *
	 * For writes larpinq makes on a user's behalf that the user's own rights do
	 * not cover: a registration the sign-up form creates for a player, and the
	 * event's participants a registration decision changes. Callers decide
	 * first whether the write is due; this method does not check rights.
	 *
	 * An update merges `$data` over the stored object: OpenRegister replaces
	 * an object with what it is given, so a partial payload would drop (and
	 * fail validation on) every field it leaves out.
	 *
	 * @param string $objectType The object type (e.g. 'registration').
	 * @param array<string,mixed> $data The fields to persist; on an update only those that change.
	 * @param string|null $uuid The UUID to update, or null to create.
	 *
	 * @return array<string,mixed> The persisted object as an array.
	 *
	 * @throws Exception If OpenRegister is not available or the type is not configured.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister ObjectService resolved dynamically.
	 * @psalm-suppress MixedAssignment  OpenRegister ObjectService resolved dynamically.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function saveObjectWithAppAuthority(string $objectType, array $data, ?string $uuid = null): array {
		if ($uuid !== null && $uuid !== '') {
			$data = array_merge($this->storedFields(objectType: $objectType, uuid: $uuid), $data);
		}

		$openRegister = $this->getOpenRegisterService();
		[$register, $schema] = $this->resolveRegisterAndSchema(objectTypeLower: strtolower($objectType));

		// @var mixed $saved
		$saved = $openRegister->saveObject(
			object: $data,
			options: new ObjectSaveOptions(
				target: new ObjectFindTarget(register: $register, schema: $schema),
				uuid: $uuid
			),
			_rbac: false,
			_multitenancy: false
		);

		return $this->toArray(object: $saved);
	}//end saveObjectWithAppAuthority()

	/**
	 * The stored fields of an object, without its `@self` metadata, or none
	 * when it cannot be read.
	 *
	 * @param string $objectType The object type.
	 * @param string $uuid       The object UUID.
	 *
	 * @return array<string,mixed>
	 */
	private function storedFields(string $objectType, string $uuid): array {
		try {
			$stored = $this->getObjectWithAppAuthority(objectType: $objectType, id: $uuid);
		} catch (Exception $e) {
			return [];
		}

		unset($stored['@self'], $stored['id']);
		return $stored;
	}//end storedFields()

	/**
	 * Delete an object of a given type from OpenRegister, with RBAC on.
	 *
	 * @param string $objectType The object type (e.g. 'skill').
	 * @param string $uuid The object UUID.
	 *
	 * @return bool True when OpenRegister deleted it.
	 *
	 * @throws Exception If OpenRegister is not available, the type is not configured, or the delete is refused.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister ObjectService resolved dynamically.
	 *
	 * @spec openspec/specs/setting-management/spec.md
	 */
	public function deleteObject(string $objectType, string $uuid): bool {
		$openRegister = $this->getOpenRegisterService();

		[$register, $schema] = $this->resolveRegisterAndSchema(objectTypeLower: strtolower($objectType));

		return (bool)$openRegister->deleteObject(new ObjectDeletion(uuid: $uuid, target: new ObjectFindTarget(register: $register, schema: $schema)));
	}//end deleteObject()
}//end class
