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
	 * The characters, other than $exceptCharacter, that hold an item or condition.
	 *
	 * Holders are counted on both sides: every character whose `items[]` (or
	 * `conditions[]`) contains the id, plus every id in the object's own
	 * `characters[]`. The character-side lookup asks OpenRegister for at most
	 * $max rows filtered on the id; when the answer shows the filter was not
	 * applied (a row without the id), it pages through characters in batches of
	 * BATCH and stops as soon as $max other holders are found.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param string $id The item or condition id.
	 * @param array<int,string> $objectSide Ids in the object's own `characters[]`.
	 * @param string|null $exceptCharacter The character being written, never counted.
	 * @param int $max Stop after this many other holders.
	 *
	 * @return array<int,array{id:string,name:string}> At most $max holders.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function otherHolders(
		string $kind,
		string $id,
		array $objectSide = [],
		?string $exceptCharacter = null,
		int $max = 2
	): array {
		$holders = [];
		foreach ($this->characterSideHolders(kind: $kind, id: $id, exceptCharacter: $exceptCharacter, max: $max) as $holder) {
			$holders[$holder['id']] = $holder;
		}

		foreach ($objectSide as $characterId) {
			if (count($holders) >= $max) {
				break;
			}

			$characterId = (string)$characterId;
			if ($characterId === '' || $characterId === $exceptCharacter || isset($holders[$characterId]) === true) {
				continue;
			}

			$holders[$characterId] = ['id' => $characterId, 'name' => $this->characterName(id: $characterId)];
		}

		return array_slice(array_values($holders), 0, $max);
	}//end otherHolders()

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
	 * @param string $kind 'item' or 'condition'.
	 * @param string $id The item or condition id.
	 * @param string|null $exceptCharacter The character being written.
	 * @param int $max Stop after this many holders.
	 *
	 * @return array<int,array{id:string,name:string}> The holders.
	 */
	private function characterSideHolders(string $kind, string $id, ?string $exceptCharacter, int $max): array {
		$field = self::CHARACTER_FIELD[$kind];

		// One extra row, so the character being written cannot use up the limit.
		$rows = $this->objectFetcher->getObjects(
			objectType: 'character',
			limit: $max + 1,
			filters: [$field => $id]
		);

		$filterApplied = true;
		foreach ($rows as $row) {
			if (is_array($row) === false || $this->holds(character: $row, field: $field, id: $id) === false) {
				$filterApplied = false;
				break;
			}
		}

		if ($filterApplied === true) {
			return $this->collect(rows: $rows, field: $field, id: $id, exceptCharacter: $exceptCharacter, max: $max);
		}

		// The filter did not match inside the array: bounded scan, stop early.
		$holders = [];
		foreach ($this->pages(objectType: 'character') as $row) {
			foreach ($this->collect(rows: [$row], field: $field, id: $id, exceptCharacter: $exceptCharacter, max: $max) as $holder) {
				$holders[] = $holder;
				if (count($holders) >= $max) {
					return $holders;
				}
			}
		}

		return $holders;
	}//end characterSideHolders()

	/**
	 * The holders among a set of character rows.
	 *
	 * @param array<int,mixed> $rows Character rows.
	 * @param string $field The relation field.
	 * @param string $id The item or condition id.
	 * @param string|null $exceptCharacter The character being written.
	 * @param int $max Stop after this many holders.
	 *
	 * @return array<int,array{id:string,name:string}> The holders.
	 */
	private function collect(array $rows, string $field, string $id, ?string $exceptCharacter, int $max): array {
		$holders = [];
		foreach ($rows as $row) {
			if (is_array($row) === false || $this->holds(character: $row, field: $field, id: $id) === false) {
				continue;
			}

			$characterId = $this->objectId(object: $row);
			if ($characterId === '' || $characterId === $exceptCharacter) {
				continue;
			}

			$holders[] = ['id' => $characterId, 'name' => $this->nameOf(object: $row, fallback: $characterId)];
			if (count($holders) >= $max) {
				break;
			}
		}

		return $holders;
	}//end collect()

	/**
	 * Whether a character's relation field contains the id.
	 *
	 * @param array<string,mixed> $character The character data.
	 * @param string $field The relation field.
	 * @param string $id The item or condition id.
	 *
	 * @return bool True when the character holds it.
	 */
	private function holds(array $character, string $field, string $id): bool {
		return in_array($id, $this->idNormaliser->normalise(value: $character[$field] ?? []), true);
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
	 * Read one item or condition; null when it cannot be read.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param string $id The object id.
	 *
	 * @return array<string,mixed>|null The object data.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function readObject(string $kind, string $id): ?array {
		try {
			return $this->objectFetcher->getObject(objectType: $kind, id: $id);
		} catch (\Throwable $e) {
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
