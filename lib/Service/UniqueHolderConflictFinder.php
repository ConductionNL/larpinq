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
		$holdings = $this->indexCharacterSide();

		$conflicts = [];
		foreach (array_keys(UniqueHolderService::CHARACTER_FIELD) as $kind) {
			$ofKind = $holdings->forKind(kind: $kind);
			foreach ($this->holders->pages(objectType: $kind) as $object) {
				$conflict = $this->conflictOf(object: $object, holdings: $ofKind);
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
	 * @return CharacterHoldings Who holds what, and every character's name.
	 */
	private function indexCharacterSide(): CharacterHoldings {
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

		return new CharacterHoldings(held: $held, names: $names);
	}//end indexCharacterSide()

	/**
	 * The conflict for one item or condition, or null when it has at most one holder.
	 *
	 * @param array<string,mixed> $object The item or condition data.
	 * @param CharacterHoldings $holdings The character side, read for the object's kind.
	 *
	 * @return array{kind:string,id:string,name:string,holders:array<int,string>}|null The conflict.
	 */
	private function conflictOf(array $object, CharacterHoldings $holdings): ?array {
		$objectId = $this->holders->objectId(object: $object);
		if ($objectId === '' || $this->holders->isUnique(kind: $holdings->kind(), object: $object) === false) {
			return null;
		}

		$holders = $holdings->holderNames(
			objectId: $objectId,
			objectSide: $this->idNormaliser->normalise(value: $object['characters'] ?? [])
		);
		if (count($holders) < 2) {
			return null;
		}

		return [
			'kind' => $holdings->kind(),
			'id' => $objectId,
			'name' => $this->holders->nameOf(object: $object, fallback: $objectId),
			'holders' => $holders,
		];
	}//end conflictOf()
}//end class
