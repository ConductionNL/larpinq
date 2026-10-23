<?php

/**
 * UniqueHolderConflictFinder for Larpinq
 *
 * Lists every unique item and unique condition that more than one character
 * holds today, for `occ larpinq:unique-holders:check`. Both sides of the
 * relation count: the character's `items[]` / `conditions[]` and the item's or
 * condition's `characters[]`.
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
 * Finds unique items and conditions with more than one holder.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */
class UniqueHolderConflictFinder {

	/**
	 * Constructor.
	 *
	 * @param UniqueHolderService $holders The holder lookup (paging, ids, names, uniqueness).
	 * @param IdListNormaliser $idNormaliser Relation value normaliser.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly UniqueHolderService $holders,
		private readonly IdListNormaliser $idNormaliser,
	) {
	}//end __construct()

	/**
	 * Every unique item and unique condition held by more than one character.
	 *
	 * @return array<int,array{kind:string,id:string,name:string,holders:array<int,string>}> The conflicts.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function findConflicts(): array {
		[$held, $names] = $this->indexCharacterSide();

		$conflicts = [];
		foreach (array_keys(UniqueHolderService::CHARACTER_FIELD) as $kind) {
			foreach ($this->holders->pages(objectType: $kind) as $object) {
				$conflict = $this->conflictOf(kind: $kind, object: $object, held: $held[$kind], names: $names);
				if ($conflict !== null) {
					$conflicts[] = $conflict;
				}
			}
		}

		return $conflicts;
	}//end findConflicts()

	/**
	 * Index the character side once.
	 *
	 * Returns kind => objectId => [characterId => name], and characterId => name.
	 *
	 * @return array{0:array<string,array<string,array<string,string>>>,1:array<string,string>} The index and the names.
	 */
	private function indexCharacterSide(): array {
		$held = ['item' => [], 'condition' => []];
		$names = [];
		foreach ($this->holders->pages(objectType: 'character') as $character) {
			$characterId = $this->holders->objectId(object: $character);
			if ($characterId === '') {
				continue;
			}

			$names[$characterId] = $this->holders->nameOf(object: $character, fallback: $characterId);
			foreach (UniqueHolderService::CHARACTER_FIELD as $kind => $field) {
				foreach ($this->idNormaliser->normalise(value: $character[$field] ?? []) as $objectId) {
					$held[$kind][$objectId][$characterId] = $names[$characterId];
				}
			}
		}

		return [$held, $names];
	}//end indexCharacterSide()

	/**
	 * The conflict for one item or condition, or null when it has at most one holder.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param array<string,mixed> $object The item or condition data.
	 * @param array<string,array<string,string>> $held objectId => [characterId => name] from the character side.
	 * @param array<string,string> $names characterId => name.
	 *
	 * @return array{kind:string,id:string,name:string,holders:array<int,string>}|null The conflict.
	 */
	private function conflictOf(string $kind, array $object, array $held, array $names): ?array {
		$objectId = $this->holders->objectId(object: $object);
		if ($objectId === '' || $this->holders->isUnique(kind: $kind, object: $object) === false) {
			return null;
		}

		$holders = $held[$objectId] ?? [];
		foreach ($this->idNormaliser->normalise(value: $object['characters'] ?? []) as $characterId) {
			$holders[$characterId] ??= ($names[$characterId] ?? $characterId);
		}

		if (count($holders) < 2) {
			return null;
		}

		$holders = array_values($holders);
		sort($holders);

		return [
			'kind' => $kind,
			'id' => $objectId,
			'name' => $this->holders->nameOf(object: $object, fallback: $objectId),
			'holders' => $holders,
		];
	}//end conflictOf()
}//end class
