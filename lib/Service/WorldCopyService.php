<?php

/**
 * Copies a world's rules into a new world.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/worlds-copy-ruleset/specs/setting-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Exception;
use InvalidArgumentException;
use LengthException;
use OCP\AppFramework\Db\DoesNotExistException;
use Throwable;

/**
 * A new world with a copy of every ability, effect, skill, item, condition
 * and lore page of an existing world, every reference between them pointing
 * at the copies (design D1, D2).
 *
 * The copy goes through RegisterObjectFetcher, so OpenRegister's RBAC and
 * validation apply to every write. The new world is saved `archived` first
 * and set `active` only when every object is copied; a failure deletes what
 * was made and names what it could not remove.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/worlds-copy-ruleset/specs/setting-management/spec.md
 */
class WorldCopyService {

	/**
	 * The most rule objects one copy writes (design D3).
	 *
	 * @var integer
	 */
	public const MAX_OBJECTS = 2000;

	/**
	 * Rows per read.
	 *
	 * @var integer
	 */
	private const PAGE = 500;

	/**
	 * The copied types in dependency order, with their key in the counts.
	 *
	 * @var array<string, string>
	 */
	private const TYPES = [
		'ability' => 'abilities',
		'effect' => 'effects',
		'skill' => 'skills',
		'item' => 'items',
		'condition' => 'conditions',
		'lorepage' => 'lorePages',
	];

	/**
	 * Types whose schema may not be configured yet (before a register re-import).
	 *
	 * @var list<string>
	 */
	private const OPTIONAL_TYPES = ['lorepage'];

	/**
	 * Reference fields per type, with the type they point at.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const REFERENCES = [
		'effect' => ['abilities' => 'ability'],
		'skill' => [
			'effects' => 'effect',
			'requiredSkills' => 'skill',
			'requiredStats' => 'ability',
			'requiredConditions' => 'condition',
			'requiredEffects' => 'effect',
		],
		'item' => ['effects' => 'effect'],
		'condition' => ['effects' => 'effect'],
		'lorepage' => ['parent' => 'lorepage'],
	];

	/**
	 * Holder lists that start empty in the copy.
	 *
	 * @var array<string, list<string>>
	 */
	private const HOLDERS = [
		'item' => ['characters'],
		'condition' => ['characters'],
	];

	/**
	 * Old-to-new ids per type for the copy in progress.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $idMap = [];

	/**
	 * Every object the copy in progress created: [type, uuid], in order.
	 *
	 * @var list<array{0: string, 1: string}>
	 */
	private array $created = [];

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher OpenRegister reads and writes.
	 * @param IdListNormaliser $idList Reads a reference list in either shape.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly IdListNormaliser $idList,
	) {
	}//end __construct()

	/**
	 * What a copy of the world would create, without writing anything.
	 *
	 * @param string $worldId The world UUID.
	 *
	 * @return array{world: array<string, mixed>, counts: array<string, int>} The world and counts.
	 *
	 * @throws DoesNotExistException When the world does not exist.
	 * @throws LengthException When the world holds more than MAX_OBJECTS rule objects.
	 *
	 * @spec openspec/changes/worlds-copy-ruleset/specs/setting-management/spec.md
	 */
	public function preview(string $worldId): array {
		$world = $this->readWorld(worldId: $worldId);
		return ['world' => $world, 'counts' => $this->counts(objects: $this->readRules(worldId: $worldId))];
	}//end preview()

	/**
	 * Copy the world under a new name.
	 *
	 * @param string $worldId The world UUID.
	 * @param string $name The new world's name.
	 *
	 * @return array{world: array<string, mixed>, counts: array<string, int>} The new world and counts.
	 *
	 * @throws InvalidArgumentException When the name is empty or too long.
	 * @throws DoesNotExistException When the world does not exist.
	 * @throws LengthException When the world holds more than MAX_OBJECTS rule objects.
	 * @throws WorldCopyFailedException When a write fails; the copy is rolled back.
	 *
	 * @spec openspec/changes/worlds-copy-ruleset/specs/setting-management/spec.md
	 */
	public function copy(string $worldId, string $name): array {
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 255) {
			throw new InvalidArgumentException('A world needs a name of 1 to 255 characters');
		}

		$world = $this->readWorld(worldId: $worldId);
		$objects = $this->readRules(worldId: $worldId);

		$this->idMap = [];
		$this->created = [];
		$label = 'the world';
		try {
			$payload = $this->payload(type: 'setting', source: $world, newWorld: null);
			$payload['name'] = $name;
			$payload['status'] = 'archived';
			$newWorld = $this->create(type: 'setting', payload: $payload);
			$newId = (string)$newWorld['id'];

			$deferred = [];
			foreach ($objects as $type => $rows) {
				foreach ($rows as $row) {
					$label = $type . ' "' . $this->nameOf(row: $row) . '"';
					$full = $this->payload(type: $type, source: $row, newWorld: $newId);
					$later = $this->laterReferences(type: $type, payload: $full);
					$saved = $this->create(type: $type, payload: array_diff_key($full, $later));
					$this->idMap[$type][(string)$row['id']] = (string)$saved['id'];
					if ($later !== []) {
						$deferred[] = [$type, (string)$saved['id'], $row, $full];
					}
				}
			}

			foreach ($deferred as [$type, $uuid, $row, $full]) {
				$label = $type . ' "' . $this->nameOf(row: $row) . '"';
				$this->objectFetcher->saveObject(objectType: $type, data: $this->remapAll(type: $type, payload: $full), uuid: $uuid);
			}

			$label = 'the world';
			$payload['status'] = 'active';
			$newWorld = $this->objectFetcher->saveObject(objectType: 'setting', data: $payload, uuid: $newId);
		} catch (Throwable $e) {
			throw new WorldCopyFailedException(
				message: 'Copying ' . $label . ' failed: ' . $e->getMessage(),
				leftovers: $this->rollback(),
				previous: $e
			);
		}//end try

		return ['world' => $newWorld, 'counts' => $this->counts(objects: $objects)];
	}//end copy()

	/**
	 * Read the world, or fail when it does not exist.
	 *
	 * @param string $worldId The world UUID.
	 *
	 * @return array<string, mixed> The world.
	 *
	 * @throws DoesNotExistException When the world does not exist.
	 */
	private function readWorld(string $worldId): array {
		$world = $this->objectFetcher->getObject(objectType: 'setting', id: $worldId);
		if (isset($world['id']) === false) {
			throw new DoesNotExistException('World not found');
		}

		return $world;
	}//end readWorld()

	/**
	 * Read every rule object of the world, per type, within the cap.
	 *
	 * @param string $worldId The world UUID.
	 *
	 * @return array<string, list<array<string, mixed>>> The objects per type.
	 *
	 * @throws LengthException When the world holds more than MAX_OBJECTS rule objects.
	 */
	private function readRules(string $worldId): array {
		$objects = [];
		$total = 0;
		foreach (array_keys(self::TYPES) as $type) {
			$objects[$type] = $this->readType(type: $type, worldId: $worldId, budget: self::MAX_OBJECTS - $total + 1);
			$total += count($objects[$type]);
			if ($total > self::MAX_OBJECTS) {
				throw new LengthException('A world with more than ' . self::MAX_OBJECTS . ' rule objects cannot be copied in one go');
			}
		}

		return $objects;
	}//end readRules()

	/**
	 * Read one type's objects of the world, paged, up to a budget.
	 *
	 * @param string $type The type.
	 * @param string $worldId The world UUID.
	 * @param int $budget The most rows to read.
	 *
	 * @return list<array<string, mixed>> The objects.
	 */
	private function readType(string $type, string $worldId, int $budget): array {
		$rows = [];
		$offset = 0;
		do {
			try {
				$page = $this->objectFetcher->getObjects(objectType: $type, limit: self::PAGE, offset: $offset, filters: ['setting' => $worldId]);
			} catch (Exception $e) {
				if (in_array($type, self::OPTIONAL_TYPES, true) === true && str_contains($e->getMessage(), 'not configured') === true) {
					return [];
				}

				throw $e;
			}

			foreach ($page as $row) {
				if (isset($row['id']) === true) {
					$rows[] = $row;
				}
			}

			$offset += self::PAGE;
		} while (count($page) === self::PAGE && count($rows) < $budget);

		return $rows;
	}//end readType()

	/**
	 * The object's data as a payload for its copy: no id, no metadata, no
	 * nulls, empty holders, the new world, and references remapped as far
	 * as the id map reaches.
	 *
	 * @param string $type The type.
	 * @param array<string, mixed> $source The original object.
	 * @param string|null $newWorld The new world's UUID, or null for the world itself.
	 *
	 * @return array<string, mixed> The payload.
	 */
	private function payload(string $type, array $source, ?string $newWorld): array {
		$payload = [];
		foreach ($source as $key => $value) {
			if ($value === null || $key === 'id' || $key === 'uuid' || str_starts_with((string)$key, '@') === true || str_starts_with((string)$key, '_') === true) {
				continue;
			}

			$payload[(string)$key] = $value;
		}

		foreach (self::HOLDERS[$type] ?? [] as $field) {
			$payload[$field] = [];
		}

		if ($newWorld !== null) {
			$payload['setting'] = $newWorld;
		}

		return $this->remapAll(type: $type, payload: $payload);
	}//end payload()

	/**
	 * The reference fields that point at objects not all copied yet (the same
	 * type or a later one), which the first save leaves out.
	 *
	 * @param string $type The type.
	 * @param array<string, mixed> $payload The full payload.
	 *
	 * @return array<string, mixed> The deferred fields and their values.
	 */
	private function laterReferences(string $type, array $payload): array {
		$order = array_keys(self::TYPES);
		$own = (int)array_search($type, $order, true);
		$later = [];
		foreach (self::REFERENCES[$type] ?? [] as $field => $target) {
			$empty = ($payload[$field] ?? null) === null || $payload[$field] === [] || $payload[$field] === '';
			if ($empty === false && (int)array_search($target, $order, true) >= $own) {
				$later[$field] = $payload[$field];
			}
		}

		return $later;
	}//end laterReferences()

	/**
	 * Point every reference at the copy where one exists; a reference outside
	 * the world stays as it is.
	 *
	 * @param string $type The type.
	 * @param array<string, mixed> $payload The payload.
	 *
	 * @return array<string, mixed> The remapped payload.
	 */
	private function remapAll(string $type, array $payload): array {
		foreach (self::REFERENCES[$type] ?? [] as $field => $target) {
			if (isset($payload[$field]) === false) {
				continue;
			}

			$map = $this->idMap[$target] ?? [];
			$scalar = is_array($payload[$field]) === false;
			$ids = array_map(static fn (string $id): string => $map[$id] ?? $id, $this->idList->normalise(value: $payload[$field]));
			$payload[$field] = $ids;
			if ($scalar === true) {
				// A single reference (a lore page's parent) stays a single id.
				$payload[$field] = $ids[0] ?? null;
				if ($payload[$field] === null) {
					unset($payload[$field]);
				}
			}
		}

		return $payload;
	}//end remapAll()

	/**
	 * Save a new object and remember it for a rollback.
	 *
	 * @param string $type The type.
	 * @param array<string, mixed> $payload The payload.
	 *
	 * @return array<string, mixed> The saved object.
	 */
	private function create(string $type, array $payload): array {
		$saved = $this->objectFetcher->saveObject(objectType: $type, data: $payload);
		$this->created[] = [$type, (string)($saved['id'] ?? '')];
		return $saved;
	}//end create()

	/**
	 * Delete what the failed copy created, newest first.
	 *
	 * @return list<string> The ids that could not be removed.
	 */
	private function rollback(): array {
		$leftovers = [];
		foreach (array_reverse($this->created) as [$type, $uuid]) {
			try {
				$this->objectFetcher->deleteObject(objectType: $type, uuid: $uuid);
			} catch (Throwable $e) {
				$leftovers[] = $uuid;
			}
		}

		$this->created = [];
		return array_reverse($leftovers);
	}//end rollback()

	/**
	 * The counts per type.
	 *
	 * @param array<string, list<array<string, mixed>>> $objects The objects per type.
	 *
	 * @return array<string, int> The counts.
	 */
	private function counts(array $objects): array {
		$counts = [];
		foreach (self::TYPES as $type => $key) {
			$counts[$key] = count($objects[$type] ?? []);
		}

		return $counts;
	}//end counts()

	/**
	 * A readable name for an error message.
	 *
	 * @param array<string, mixed> $row The object.
	 *
	 * @return string The name.
	 */
	private function nameOf(array $row): string {
		return (string)($row['name'] ?? $row['title'] ?? $row['id'] ?? '');
	}//end nameOf()
}//end class
