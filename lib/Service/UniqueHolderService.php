<?php

/**
 * UniqueHolderService for Larpinq
 *
 * A unique item or a unique condition belongs to one character at a time. A
 * holding is stored on both sides of the relation and the two sides are not
 * synced: the character carries `items[]` / `conditions[]`, the item or
 * condition carries `characters[]`. This service counts holders on both sides
 * with bounded lookups, so the pre-write listener can refuse a second holder.
 * UniqueHolderConflictFinder lists the conflicts that exist today.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use InvalidArgumentException;
use OCA\OpenRegister\Exception\NotAuthorizedException;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Counts the characters that hold a unique item or condition.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */
class UniqueHolderService {

	/**
	 * Page size for the bounded fallback scan (hydra ADR-058).
	 *
	 * @var int
	 */
	public const BATCH = 100;

	/**
	 * The character field that holds each kind.
	 *
	 * @var array<string,string>
	 */
	public const CHARACTER_FIELD = [
		'item' => 'items',
		'condition' => 'conditions',
	];

	/**
	 * The schema default of `unique` per kind (larpinq_register.json).
	 *
	 * @var array<string,bool>
	 */
	private const UNIQUE_DEFAULT = [
		'item' => true,
		'condition' => false,
	];

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher The OpenRegister object fetcher.
	 * @param IdListNormaliser $idNormaliser Relation value normaliser.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly IdListNormaliser $idNormaliser,
	) {
	}//end __construct()

	/**
	 * Whether an item or condition object is unique, applying the schema default.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param array<string,mixed> $object The item or condition data.
	 *
	 * @return bool True when only one character may hold it.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function isUnique(string $kind, array $object): bool {
		if (array_key_exists('unique', $object) === false || $object['unique'] === null) {
			return self::UNIQUE_DEFAULT[$kind] ?? false;
		}

		return $object['unique'] === true || $object['unique'] === 1 || $object['unique'] === '1' || $object['unique'] === 'true';
	}//end isUnique()

	/**
	 * The characters, other than the query's excepted one, that hold an item or condition.
	 *
	 * Holders are counted on both sides: every character whose `items[]` (or
	 * `conditions[]`) contains the id, plus every id in the object's own
	 * `characters[]`. The character-side lookup asks OpenRegister for at most
	 * max rows filtered on the id; when the answer shows the filter was not
	 * applied (a row without the id), it pages through characters in batches of
	 * BATCH and stops as soon as max other holders are found.
	 *
	 * @param HolderQuery $query The kind, id, excepted character and limit.
	 * @param array<int,string> $objectSide Ids in the object's own `characters[]`.
	 *
	 * @return array<int,array{id:string,name:string}> At most max holders.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function otherHolders(HolderQuery $query, array $objectSide = []): array {
		$holders = [];
		foreach ($this->characterSideHolders(query: $query) as $holder) {
			$holders[$holder['id']] = $holder;
		}

		foreach ($this->objectSideIds(objectSide: $objectSide, query: $query) as $characterId) {
			if (count($holders) >= $query->max) {
				break;
			}

			if (isset($holders[$characterId]) === false) {
				$holders[$characterId] = ['id' => $characterId, 'name' => $this->characterName(id: $characterId)];
			}
		}

		return array_slice(array_values($holders), 0, $query->max);
	}//end otherHolders()

	/**
	 * The ids of the object's own `characters[]` that can count as holders.
	 *
	 * @param array<int,mixed> $objectSide Ids in the object's own `characters[]`.
	 * @param HolderQuery $query The lookup; its excepted character is left out.
	 *
	 * @return array<int,string> The ids, in order, without '' and the excepted character.
	 */
	private function objectSideIds(array $objectSide, HolderQuery $query): array {
		$ids = [];
		foreach ($objectSide as $characterId) {
			$characterId = (string)$characterId;
			if ($characterId !== '' && $characterId !== $query->exceptCharacter) {
				$ids[] = $characterId;
			}
		}

		return $ids;
	}//end objectSideIds()

	/**
	 * The object's id, from `id` or `@self.id`.
	 *
	 * @param array<string,mixed> $object The object data.
	 *
	 * @return string The id, or '' when absent.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function objectId(array $object): string {
		$id = $object['id'] ?? null;
		if (is_string($id) === false || $id === '') {
			$self = $object['@self'] ?? [];
			$id = '';
			if (is_array($self) === true) {
				$id = $self['id'] ?? '';
			}
		}

		if (is_scalar($id) === false) {
			return '';
		}

		return (string)$id;
	}//end objectId()

	/**
	 * Holders found on the character side of the relation.
	 *
	 * @param HolderQuery $query The lookup.
	 *
	 * @return array<int,array{id:string,name:string}> The holders.
	 */
	private function characterSideHolders(HolderQuery $query): array {
		// One extra row, so the character being written cannot use up the limit.
		$rows = $this->objectFetcher->getObjects(
			objectType: 'character',
			limit: $query->max + 1,
			filters: [$query->field() => $query->id]
		);

		if ($this->allHold(rows: $rows, query: $query) === true) {
			return $this->collect(rows: $rows, query: $query);
		}

		// The filter did not match inside the array: bounded scan, stop early.
		return $this->collect(rows: $this->pages(objectType: 'character'), query: $query);
	}//end characterSideHolders()

	/**
	 * Whether every row is a character that holds the id, which shows the
	 * register applied the filter.
	 *
	 * @param array<int,mixed> $rows Character rows.
	 * @param HolderQuery $query The lookup.
	 *
	 * @return bool True when every row holds it.
	 */
	private function allHold(array $rows, HolderQuery $query): bool {
		foreach ($rows as $row) {
			if (is_array($row) === false || $this->holds(character: $row, query: $query) === false) {
				return false;
			}
		}

		return true;
	}//end allHold()

	/**
	 * The holders among character rows, read only until max are found.
	 *
	 * @param iterable<mixed> $rows Character rows, or a generator over pages of them.
	 * @param HolderQuery $query The lookup.
	 *
	 * @return array<int,array{id:string,name:string}> At most max holders.
	 */
	private function collect(iterable $rows, HolderQuery $query): array {
		$holders = [];
		foreach ($rows as $row) {
			$holder = $this->holderOf(row: $row, query: $query);
			if ($holder === null) {
				continue;
			}

			$holders[] = $holder;
			if (count($holders) >= $query->max) {
				break;
			}
		}

		return $holders;
	}//end collect()

	/**
	 * The holder a character row stands for, or null when it does not hold the
	 * id, has no id or is the excepted character.
	 *
	 * @param mixed $row A character row.
	 * @param HolderQuery $query The lookup.
	 *
	 * @return array{id:string,name:string}|null The holder.
	 */
	private function holderOf(mixed $row, HolderQuery $query): ?array {
		if (is_array($row) === false || $this->holds(character: $row, query: $query) === false) {
			return null;
		}

		return $this->namedHolder(row: $row, query: $query);
	}//end holderOf()

	/**
	 * A holding character's id and name, or null when it has no id or is the
	 * excepted character.
	 *
	 * @param array<string,mixed> $row A character row that holds the id.
	 * @param HolderQuery $query The lookup.
	 *
	 * @return array{id:string,name:string}|null The holder.
	 */
	private function namedHolder(array $row, HolderQuery $query): ?array {
		$characterId = $this->objectId(object: $row);
		if ($characterId === '' || $characterId === $query->exceptCharacter) {
			return null;
		}

		return ['id' => $characterId, 'name' => $this->nameOf(object: $row, fallback: $characterId)];
	}//end namedHolder()

	/**
	 * Whether a character's relation field contains the query's id.
	 *
	 * @param array<string,mixed> $character The character data.
	 * @param HolderQuery $query The lookup.
	 *
	 * @return bool True when the character holds it.
	 */
	private function holds(array $character, HolderQuery $query): bool {
		return in_array($query->id, $this->idNormaliser->normalise(value: $character[$query->field()] ?? []), true);
	}//end holds()

	/**
	 * Page through every object of a type in batches of BATCH.
	 *
	 * @param string $objectType The larpinq object type.
	 *
	 * @return \Generator<int,array<string,mixed>> The objects.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function pages(string $objectType): \Generator {
		$offset = 0;
		do {
			$rows = $this->objectFetcher->getObjects(objectType: $objectType, limit: self::BATCH, offset: $offset);
			$fetched = count($rows);
			foreach ($rows as $row) {
				if (is_array($row) === true) {
					yield $row;
				}
			}

			$offset += self::BATCH;
		} while ($fetched === self::BATCH);
	}//end pages()

	/**
	 * Read one item or condition as the caller.
	 *
	 * An object that does not exist and one the caller may not read give the
	 * same answer, so the result never shows whether an unreadable id exists.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param string $id The object id.
	 *
	 * @return array<string,mixed>|null The object data, or null when it does not exist,
	 *                                  its id is malformed or OpenRegister refuses the caller.
	 *
	 * @throws \Throwable When the lookup itself fails.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function readObject(string $kind, string $id): ?array {
		try {
			return $this->objectFetcher->getObject(objectType: $kind, id: $id);
		} catch (DoesNotExistException|InvalidArgumentException|NotAuthorizedException $e) {
			return null;
		}
	}//end readObject()

	/**
	 * A character's name, read by id; the id when it cannot be read.
	 *
	 * @param string $id The character id.
	 *
	 * @return string The name.
	 */
	private function characterName(string $id): string {
		try {
			return $this->nameOf(object: $this->objectFetcher->getObject(objectType: 'character', id: $id), fallback: $id);
		} catch (\Throwable $e) {
			return $id;
		}
	}//end characterName()

	/**
	 * The `name` of an object, or the fallback.
	 *
	 * @param array<string,mixed> $object The object data.
	 * @param string $fallback Returned when the object has no name.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function nameOf(array $object, string $fallback): string {
		$name = $object['name'] ?? '';
		if (is_string($name) === true && trim($name) !== '') {
			return $name;
		}

		return $fallback;
	}//end nameOf()
}//end class
