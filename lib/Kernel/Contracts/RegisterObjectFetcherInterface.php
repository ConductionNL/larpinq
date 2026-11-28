<?php

/**
 * Contract of RegisterObjectFetcher
 *
 * The public surface of RegisterObjectFetcher that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Larpinq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Kernel\Contracts;

use Exception;
use InvalidArgumentException;

/**
 * Contract of RegisterObjectFetcher.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface RegisterObjectFetcherInterface {

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
	public function getObject(string $objectType, string $id): array;

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
	): array;

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
	public function getObjectsWithAppAuthority(string $objectType, array $filters, ?int $limit = null): array;

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
	public function saveObject(string $objectType, array $data, ?string $uuid = null): array;

	/**
	 * Change some fields of an existing object, with RBAC on.
	 *
	 * OpenRegister replaces an object with what it is given, so a partial
	 * payload would drop, and fail validation on, every field it leaves out.
	 * The fields are merged over the object as the signed-in user reads it.
	 *
	 * @param string              $objectType The object type (e.g. 'character').
	 * @param array<string,mixed> $data       Only the fields that change.
	 * @param string              $uuid       The UUID to update.
	 *
	 * @return array<string,mixed> The persisted object as an array.
	 *
	 * @throws Exception If the object cannot be read or the write is refused.
	 *
	 * @spec openspec/specs/event-casting/spec.md
	 */
	public function updateObject(string $objectType, array $data, string $uuid): array;

	/**
	 * Create or update an object with the app's own authority (RBAC off).
	 *
	 * For writes larpinq makes on a user's behalf that the user's own rights do
	 * not cover: a registration the sign-up form creates for a player, and the
	 * event's participants a registration decision changes. Callers decide
	 * first whether the write is due; this method does not check rights.
	 *
	 * @param string $objectType The object type (e.g. 'registration').
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
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function saveObjectWithAppAuthority(string $objectType, array $data, ?string $uuid = null): array;
}//end interface
