<?php

/**
 * An in-memory OpenRegister ObjectService for tests that drive larpinq's real
 * fetcher, services and listeners through a whole save.
 *
 * It answers the calls RegisterObjectFetcher makes (findAll with and without
 * RBAC, getMapper()->find/findAll, saveObject) and, like OpenRegister's
 * SaveObject, dispatches the pre-write event to the registered listeners,
 * applies their modified data or refuses on a stopped event, stores the row,
 * then dispatches the post-write event. Event classes are OpenRegister's own
 * (the real ones under OPENREGISTER_LIB, else the verbatim stubs).
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Support
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec exclude test support, not product code
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Support;

use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Event\ObjectCreatedEvent;
use OCA\OpenRegister\Event\ObjectCreatingEvent;
use OCA\OpenRegister\Event\ObjectUpdatedEvent;
use OCA\OpenRegister\Event\ObjectUpdatingEvent;
use OCP\EventDispatcher\IEventListener;
use RuntimeException;

/**
 * An OpenRegister object as the events carry it.
 */
class InMemoryObjectEntity extends ObjectEntity {
	/**
	 * @param string $schema The schema id.
	 * @param array<string, mixed> $data The object data.
	 * @param string|null $uuid The uuid.
	 */
	public function __construct(string $schema, array $data, ?string $uuid = null) {
		$this->schema = $schema;
		$this->object = $data;
		$this->uuid = $uuid;
	}
}

/**
 * The fake ObjectService.
 */
class InMemoryOpenRegister {

	/**
	 * Rows by schema id and uuid.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	public array $objects = [];

	/**
	 * Every save: schema, uuid and whether RBAC was on.
	 *
	 * @var list<array{schema: string, uuid: string, rbac: bool}>
	 */
	public array $saves = [];

	/**
	 * Listeners the saves dispatch to.
	 *
	 * @var list<IEventListener>
	 */
	public array $listeners = [];

	/**
	 * Uuid counter.
	 *
	 * @var int
	 */
	private int $next = 1;

	/**
	 * Store a row as it is, without events.
	 *
	 * @param string $schema The schema id.
	 * @param array<string, mixed> $data The row; `id` is its uuid.
	 *
	 * @return void
	 */
	public function seed(string $schema, array $data): void {
		$this->objects[$schema][(string)$data['id']] = $data;
	}

	/**
	 * Rows of a schema matching every filter.
	 *
	 * @param array<string, mixed> $config OpenRegister's findAll config.
	 * @param bool $_rbac Ignored: the rows these tests read carry no rules.
	 * @param bool $_multitenancy Ignored.
	 *
	 * @return list<array<string, mixed>> The rows.
	 */
	public function findAll(array $config = [], bool $_rbac = true, bool $_multitenancy = true): array {
		$filters = (array)($config['filters'] ?? []);
		$schema = (string)($filters['schema'] ?? '');
		unset($filters['schema'], $filters['register']);
		return $this->rows(schema: $schema, filters: $filters, limit: $config['limit'] ?? null);
	}

	/**
	 * Rows of a schema matching every filter.
	 *
	 * @param string $schema The schema id.
	 * @param array<string, mixed> $filters Field equality filters.
	 * @param int|null $limit At most this many.
	 *
	 * @return list<array<string, mixed>> The rows.
	 */
	public function rows(string $schema, array $filters, ?int $limit = null): array {
		$rows = [];
		foreach ($this->objects[$schema] ?? [] as $row) {
			foreach ($filters as $field => $value) {
				if (($row[$field] ?? null) !== $value) {
					continue 2;
				}
			}

			$rows[] = $row;
		}

		if ($limit === null) {
			return $rows;
		}

		return array_slice($rows, 0, $limit);
	}

	/**
	 * A mapper bound to one schema.
	 *
	 * @param mixed $register The register id.
	 * @param mixed $schema The schema id.
	 *
	 * @return object The mapper.
	 */
	/**
	 * One object by id in a schema, as OpenRegister's ObjectService::find() answers it; null when absent.
	 *
	 * @param string          $id            The uuid.
	 * @param array|null      $_extend       Unused.
	 * @param bool            $files         Unused.
	 * @param string|int|null $register      Unused.
	 * @param string|int|null $schema        The schema id.
	 * @param bool            $_rbac         Recorded in $finds.
	 * @param bool            $_multitenancy Recorded in $finds.
	 *
	 * @return InMemoryObjectEntity|null
	 */
	public function find(string $id, ?array $_extend = [], bool $files = false, $register = null, $schema = null, bool $_rbac = true, bool $_multitenancy = true): ?InMemoryObjectEntity {
		$this->finds[] = ['schema' => (string)$schema, 'id' => $id, 'rbac' => $_rbac, 'multitenancy' => $_multitenancy];
		$data = ($this->objects[(string)$schema][$id] ?? null);
		if ($data === null) {
			return null;
		}

		return new InMemoryObjectEntity(schema: (string)$schema, data: $data, uuid: $id);
	}

	/**
	 * Every find() asked for.
	 *
	 * @var array<int, array{schema: string, id: string, rbac: bool, multitenancy: bool}>
	 */
	public array $finds = [];

	public function getMapper($register, $schema): object {
		$store = $this;
		return new class($store, (string)$schema) {
			/**
			 * @param InMemoryOpenRegister $store The store.
			 * @param string $schema The schema id.
			 */
			public function __construct(private InMemoryOpenRegister $store, private string $schema) {
			}

			/**
			 * @param array<string, mixed> $config The config.
			 *
			 * @return list<array<string, mixed>> The rows.
			 */
			public function findAll(array $config = []): array {
				return $this->store->rows(schema: $this->schema, filters: (array)($config['filters'] ?? []), limit: $config['limit'] ?? null);
			}

			/**
			 * @param string $id The uuid.
			 *
			 * @return array<string, mixed> The row.
			 */
			public function find(string $id): array {
				return $this->store->objects[$this->schema][$id] ?? throw new RuntimeException('Object not found');
			}
		};
	}

	/**
	 * Save a row the way OpenRegister does: pre-write event, store, post-write event.
	 *
	 * @param array<string, mixed> $object The data.
	 * @param array<int, mixed>|null $extend Ignored.
	 * @param mixed $register The register id.
	 * @param mixed $schema The schema id.
	 * @param string|null $uuid The uuid of the row to update, or null to create.
	 * @param bool $_rbac Whether the caller's rights apply.
	 * @param bool $_multitenancy Ignored.
	 *
	 * @return array<string, mixed> The stored row.
	 */
	public function saveObject(
		array $object,
		?array $extend = [],
		$register = null,
		$schema = null,
		?string $uuid = null,
		bool $_rbac = true,
		bool $_multitenancy = true,
	): array {
		$schema = (string)$schema;
		unset($object['@self']);
		$old = null;
		if ($uuid !== null) {
			$old = ($this->objects[$schema][$uuid] ?? null);
		}

		$uuid = $uuid ?? sprintf('00000000-0000-4000-8000-%012d', $this->next++);
		$this->saves[] = ['schema' => $schema, 'uuid' => $uuid, 'rbac' => $_rbac];
		$data = $object;
		if ($old !== null) {
			$data = array_merge($old, $object);
		}

		$data['id'] = $uuid;

		$newEntity = new InMemoryObjectEntity(schema: $schema, data: $data, uuid: $uuid);
		$oldEntity = null;
		$pre = new ObjectCreatingEvent($newEntity);
		if ($old !== null) {
			$oldEntity = new InMemoryObjectEntity(schema: $schema, data: $old, uuid: $uuid);
			$pre = new ObjectUpdatingEvent($newEntity, $oldEntity);
		}

		foreach ($this->listeners as $listener) {
			$listener->handle($pre);
		}

		if ($pre->isPropagationStopped() === true) {
			throw new RuntimeException('Refused: ' . json_encode($pre->getErrors()));
		}

		$data = array_merge($data, $pre->getModifiedData());
		$this->objects[$schema][$uuid] = $data;
		$stored = new InMemoryObjectEntity(schema: $schema, data: $data, uuid: $uuid);
		$post = new ObjectCreatedEvent($stored);
		if ($oldEntity !== null) {
			$post = new ObjectUpdatedEvent($stored, $oldEntity);
		}

		foreach ($this->listeners as $listener) {
			$listener->handle($post);
		}

		return $data;
	}
}
