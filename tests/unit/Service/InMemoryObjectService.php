<?php

/**
 * An in-memory stand-in for OpenRegister's ObjectService, shaped like the
 * three calls RegisterObjectFetcher makes: getMapper()->findAll(),
 * saveObject() and deleteObject(). The schema id doubles as the type name.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

use RuntimeException;

/**
 * In-memory objects per schema, with OpenRegister's equality filters.
 */
class InMemoryObjectService {

	/**
	 * Objects per schema, keyed by uuid.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	public array $objects = [];

	/**
	 * Every payload saved, in order: [schema, data, uuid].
	 *
	 * @var list<array{0: string, 1: array<string, mixed>, 2: ?string}>
	 */
	public array $saves = [];

	/**
	 * Every uuid deleted, in order.
	 *
	 * @var list<string>
	 */
	public array $deletes = [];

	/**
	 * Throw on this save number (1-based), to test the rollback.
	 *
	 * @var integer|null
	 */
	public ?int $failOnSave = null;

	/**
	 * Uuids a delete refuses.
	 *
	 * @var list<string>
	 */
	public array $undeletable = [];

	/**
	 * Counter for generated uuids.
	 *
	 * @var integer
	 */
	private int $next = 0;

	/**
	 * Seed an object.
	 *
	 * @param string $schema The schema (type) name.
	 * @param array<string, mixed> $data The object, with its `id`.
	 *
	 * @return void
	 */
	public function seed(string $schema, array $data): void {
		$data['@self'] = ['register' => 'larpinq', 'schema' => $schema, 'id' => $data['id']];
		$this->objects[$schema][(string)$data['id']] = $data;
	}//end seed()

	/**
	 * The mapper for one schema.
	 *
	 * @param string $register The register id.
	 * @param string $schema The schema id.
	 *
	 * @return object The mapper.
	 */
	public function getMapper($register, $schema): object {
		$store = $this;
		return new class($store, (string)$schema) {
			/**
			 * @param InMemoryObjectService $store The store.
			 * @param string $schema The schema.
			 */
			public function __construct(private InMemoryObjectService $store, private string $schema) {
			}

			/**
			 * @param array<string, mixed> $config limit, offset, filters.
			 *
			 * @return list<array<string, mixed>>
			 */
			public function findAll(array $config = []): array {
				$rows = array_values($this->store->objects[$this->schema] ?? []);
				foreach (($config['filters'] ?? []) as $field => $value) {
					$rows = array_values(array_filter($rows, static fn (array $row): bool => ($row[$field] ?? null) === $value));
				}

				return array_slice($rows, (int)($config['offset'] ?? 0), $config['limit'] ?? null);
			}

			/**
			 * @param string $id The uuid.
			 *
			 * @return array<string, mixed>|null
			 */
			public function find(string $id): ?array {
				return $this->store->objects[$this->schema][$id] ?? null;
			}
		};
	}//end getMapper()

	/**
	 * Create or replace an object.
	 *
	 * @param array<string, mixed> $data The payload.
	 * @param array<mixed> $extend Unused.
	 * @param string $register The register id.
	 * @param string $schema The schema id.
	 * @param string|null $uuid The uuid to update.
	 *
	 * @return array<string, mixed> The saved object.
	 */
	public function saveObject(array $data, array $extend, $register, $schema, ?string $uuid = null): array {
		$this->saves[] = [(string)$schema, $data, $uuid];
		if ($this->failOnSave !== null && count($this->saves) === $this->failOnSave) {
			throw new RuntimeException('Validation failed');
		}

		if ($uuid === null) {
			$this->next++;
			$uuid = sprintf('00000000-0000-4000-8000-%012d', $this->next);
		}

		$data['id'] = $uuid;
		$this->seed((string)$schema, $data);
		return $this->objects[(string)$schema][$uuid];
	}//end saveObject()

	/**
	 * Delete an object.
	 *
	 * @param string $uuid The uuid.
	 * @param string $register The register id.
	 * @param string $schema The schema id.
	 *
	 * @return bool True when deleted.
	 */
	public function deleteObject(string $uuid, $register = null, $schema = null): bool {
		$this->deletes[] = $uuid;
		if (in_array($uuid, $this->undeletable, true) === true) {
			throw new RuntimeException('Delete refused');
		}

		unset($this->objects[(string)$schema][$uuid]);
		return true;
	}//end deleteObject()
}//end class
