<?php

/**
 * UniqueHolderVeto for Larpinq
 *
 * The unique-holder rule applied to one write: a character write may not add
 * a unique item or condition another character holds, and an item or
 * condition write may not leave a unique object with two holders. Returns the
 * errors payload UniqueHolderListener sets on the vetoed event.
 *
 * @category  Listener
 * @package   OCA\Larpinq\Listener
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\Service\HolderQuery;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\UniqueHolderService;
use OCP\IL10N;

/**
 * Decides whether a write gives a unique item or condition a second holder.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */
class UniqueHolderVeto {

	/**
	 * Constructor.
	 *
	 * @param UniqueHolderService $holders The holder lookup.
	 * @param IdListNormaliser $idNormaliser Relation value normaliser.
	 * @param IL10N $l10n Translations for the refusal message.
	 */
	public function __construct(
		private readonly UniqueHolderService $holders,
		private readonly IdListNormaliser $idNormaliser,
		private readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * Refuse a character write that adds a unique item or condition held elsewhere.
	 *
	 * @param ObjectWrite $write The character write.
	 *
	 * @return array<string,mixed>|null The errors payload, or null to allow.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function vetoCharacter(ObjectWrite $write): ?array {
		foreach (UniqueHolderService::CHARACTER_FIELD as $kind => $field) {
			foreach ($this->addedIds(write: $write, field: $field) as $objectId) {
				$veto = $this->vetoHeldElsewhere(
					query: new HolderQuery(kind: $kind, id: $objectId, exceptCharacter: $write->idOrNull(), max: 1)
				);
				if ($veto !== null) {
					return $veto;
				}
			}
		}

		return null;
	}//end vetoCharacter()

	/**
	 * The ids a write adds to a relation field.
	 *
	 * @param ObjectWrite $write The write.
	 * @param string $field The relation field.
	 *
	 * @return array<int|string,string> The added ids.
	 */
	private function addedIds(ObjectWrite $write, string $field): array {
		return array_diff(
			$this->idNormaliser->normalise(value: $write->data[$field] ?? []),
			$this->idNormaliser->normalise(value: $write->old[$field] ?? [])
		);
	}//end addedIds()

	/**
	 * Refuse adding a unique item or condition that another character holds.
	 *
	 * @param HolderQuery $query The added object, the character written and a limit of one.
	 *
	 * @return array<string,mixed>|null The errors payload, or null to allow.
	 */
	private function vetoHeldElsewhere(HolderQuery $query): ?array {
		$object = $this->holders->readObject(kind: $query->kind, id: $query->id);
		if ($object === null || $this->holders->isUnique(kind: $query->kind, object: $object) === false) {
			return null;
		}

		$holders = $this->holders->otherHolders(query: $query, objectSide: $this->characterSide(object: $object));
		if ($holders === []) {
			return null;
		}

		return $this->heldElsewhereRefusal(
			query: $query,
			names: ['object' => $this->holders->nameOf(object: $object, fallback: $query->id), 'holder' => $holders[0]['name']]
		);
	}//end vetoHeldElsewhere()

	/**
	 * The refusal for an added object another character holds.
	 *
	 * @param HolderQuery $query The added object.
	 * @param array{object:string,holder:string} $names The object's name and its holder's name.
	 *
	 * @return array<string,mixed> The errors payload.
	 */
	private function heldElsewhereRefusal(HolderQuery $query, array $names): array {
		$message = $this->l10n->t('%1$s is already held by %2$s. Remove it there first.', [$names['object'], $names['holder']]);
		if ($query->kind === 'condition') {
			$message = $this->l10n->t('%1$s is already on %2$s. Remove it there first.', [$names['object'], $names['holder']]);
		}

		return $this->refusal(
			query: $query,
			entry: ['field' => $query->field(), 'code' => 'held', 'heldBy' => $names['holder'], 'message' => $message]
		);
	}//end heldElsewhereRefusal()

	/**
	 * Refuse an item or condition write that leaves a unique object with two holders.
	 *
	 * Checked only when the write adds a holder to `characters[]` or switches
	 * `unique` on, so an old conflict never blocks an unrelated edit.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param ObjectWrite $write The item or condition write.
	 *
	 * @return array<string,mixed>|null The errors payload, or null to allow.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function vetoHeldObject(string $kind, ObjectWrite $write): ?array {
		if ($this->holders->isUnique(kind: $kind, object: $write->data) === false) {
			return null;
		}

		$field = $this->changedHolderField(kind: $kind, write: $write);
		if ($field === null) {
			return null;
		}

		$query = new HolderQuery(kind: $kind, id: $write->id, exceptCharacter: null, max: 3);
		$holders = $this->currentHolders(query: $query, side: $this->characterSide(object: $write->data));
		if (count($holders) < 2) {
			return null;
		}

		return $this->severalHoldersRefusal(query: $query, found: ['field' => $field, 'write' => $write, 'holders' => $holders]);
	}//end vetoHeldObject()

	/**
	 * The refusal for a unique object that several characters hold.
	 *
	 * @param HolderQuery $query The object.
	 * @param array{field:string,write:ObjectWrite,holders:array<int,array{id:string,name:string}>} $found The refused field, the write and the holders.
	 *
	 * @return array<string,mixed> The errors payload.
	 */
	private function severalHoldersRefusal(HolderQuery $query, array $found): array {
		$heldBy = array_map(static fn (array $holder): string => $holder['name'], $found['holders']);
		$message = $this->l10n->t(
			'%1$s can have only one holder, and %2$s hold it now. Remove all but one first.',
			[$this->holders->nameOf(object: $found['write']->data, fallback: $query->id), implode(', ', $heldBy)]
		);

		return $this->refusal(
			query: $query,
			entry: ['field' => $found['field'], 'code' => 'several_holders', 'heldBy' => $heldBy, 'message' => $message]
		);
	}//end severalHoldersRefusal()

	/**
	 * The field to refuse a write of a unique item or condition on, or null
	 * when the write neither adds a holder nor switches `unique` on.
	 *
	 * A create, or a write that adds a holder, is refused on `characters`; an
	 * update that only switches `unique` on is refused on `unique`.
	 *
	 * @param string $kind 'item' or 'condition'.
	 * @param ObjectWrite $write The write.
	 *
	 * @return string|null The field.
	 */
	private function changedHolderField(string $kind, ObjectWrite $write): ?string {
		$gained = array_diff($this->characterSide(object: $write->data), $this->characterSide(object: $write->old));
		if ($gained !== [] || $write->isCreate === true) {
			return 'characters';
		}

		if ($this->holders->isUnique(kind: $kind, object: $write->old) === false) {
			return 'unique';
		}

		return null;
	}//end changedHolderField()

	/**
	 * The ids in an object's own `characters[]`.
	 *
	 * @param array<string,mixed> $object The object data.
	 *
	 * @return array<int,string> The ids.
	 */
	private function characterSide(array $object): array {
		return $this->idNormaliser->normalise(value: $object['characters'] ?? []);
	}//end characterSide()

	/**
	 * The errors payload of a refusal.
	 *
	 * @param HolderQuery $query The object the refusal is about.
	 * @param array{field:string,code:string,heldBy:string|array<int,string>,message:string} $entry Field, code suffix, holders, message.
	 *
	 * @return array<string,mixed> The payload.
	 */
	private function refusal(HolderQuery $query, array $entry): array {
		return [
			'message' => $entry['message'],
			$entry['field'] => [
				[
					'code' => 'unique_' . $query->kind . '_' . $entry['code'],
					$query->kind => $query->id,
					'heldBy' => $entry['heldBy'],
					'message' => $entry['message'],
				],
			],
		];
	}//end refusal()

	/**
	 * The holders of an item or condition, counted on both sides of the relation.
	 *
	 * @param HolderQuery $query The object, nobody excepted, a limit of three.
	 * @param array<int,string> $side The object's own `characters[]`.
	 *
	 * @return array<int,array{id:string,name:string}> Up to three holders.
	 */
	private function currentHolders(HolderQuery $query, array $side): array {
		if ($query->id !== '') {
			return $this->holders->otherHolders(query: $query, objectSide: $side);
		}

		// A new object without an id: only its own list can hold it yet.
		$holders = [];
		foreach (array_values(array_unique($side)) as $characterId) {
			$holders[] = ['id' => $characterId, 'name' => $characterId];
		}

		return $holders;
	}//end currentHolders()
}//end class
