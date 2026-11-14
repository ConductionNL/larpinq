<?php

/**
 * Buying items with in-game money.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Throwable;

/**
 * Adds a priced item to a character and takes its price from the character's money in one write.
 *
 * Gold, silver and copper are paid each from their own purse: the register holds no
 * exchange rate between them, so a character short in any of the three is refused
 * and nothing changes (REQ-RPG-001).
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
 */
class PurchaseService {

	/**
	 * The coins a price and a purse are counted in.
	 */
	public const COINS = ['gold', 'silver', 'copper'];

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $fetcher Reads and writes register objects as the caller.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $fetcher,
	) {
	}//end __construct()

	/**
	 * Read a character, as the caller.
	 *
	 * @param string $characterId The character UUID.
	 *
	 * @return array<string,mixed> The character.
	 *
	 * @throws PurchaseRefusedException 404 when there is no such character.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	public function character(string $characterId): array {
		return $this->read(objectType: 'character', id: $characterId, missing: 'Character not found');
	}//end character()

	/**
	 * Buy an item for a character.
	 *
	 * The caller MUST have been checked as the character's owner or a game master.
	 *
	 * @param array<string,mixed> $character The character, as read.
	 * @param string              $itemId    The item UUID.
	 *
	 * @return array<string,mixed> The character as saved.
	 *
	 * @throws PurchaseRefusedException 404 for an unknown item, 409 when the purchase is not possible.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	public function buy(array $character, string $itemId): array {
		$item  = $this->read(objectType: 'item', id: $itemId, missing: 'Item not found');
		$price = $this->price(item: $item);
		if ($price === null) {
			throw new PurchaseRefusedException(message: 'This item has no price', status: 409);
		}

		$world = (string)($character['setting'] ?? '');
		if ($world !== '' && (string)($item['setting'] ?? '') !== $world) {
			throw new PurchaseRefusedException(message: 'This item belongs to another world', status: 409);
		}

		$items = $this->ids(value: $character['items'] ?? []);
		if (in_array($this->idOf(object: $item, fallback: $itemId), $items, true) === true) {
			throw new PurchaseRefusedException(message: 'The character already carries this item', status: 409);
		}

		$paid = $this->pay(character: $character, price: $price);
		$paid['items'] = array_merge($items, [$this->idOf(object: $item, fallback: $itemId)]);

		$uuid = $this->idOf(object: $character, fallback: '');
		unset($paid['@self']);

		// The caller's right to this character is checked before buy() is reached; the
		// write carries the whole character as read, so property rules the caller does
		// not hold (private notes) are not mistaken for a change they make.
		return $this->fetcher->saveObjectWithAppAuthority(objectType: 'character', data: $paid, uuid: $uuid);
	}//end buy()

	/**
	 * The price of an item, or null when it has none.
	 *
	 * A price is a map of gold, silver and copper to non-negative whole numbers; a price
	 * with no coin above zero, or any coin that is not such a number, is no price.
	 *
	 * @param array<string,mixed> $item The item.
	 *
	 * @return array<string,int>|null The price per coin.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	public function price(array $item): ?array {
		$raw = $item['price'] ?? null;
		if (is_array($raw) === false) {
			return null;
		}

		$price = [];
		foreach (self::COINS as $coin) {
			$amount = $raw[$coin] ?? 0;
			if (is_int($amount) === false || $amount < 0) {
				return null;
			}

			$price[$coin] = $amount;
		}

		if (array_sum($price) === 0) {
			return null;
		}

		return $price;
	}//end price()

	/**
	 * The character with the price taken from its purse.
	 *
	 * @param array<string,mixed> $character The character.
	 * @param array<string,int>   $price     The price per coin.
	 *
	 * @return array<string,mixed> The character with its coins lowered.
	 *
	 * @throws PurchaseRefusedException 409 when any coin falls short.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	private function pay(array $character, array $price): array {
		foreach (self::COINS as $coin) {
			$held = (int)($character[$coin] ?? 0);
			if ($held < $price[$coin]) {
				throw new PurchaseRefusedException(message: 'The character cannot pay this item', status: 409);
			}

			$character[$coin] = $held - $price[$coin];
		}

		return $character;
	}//end pay()

	/**
	 * Read an object, mapping any failure to a 404 refusal.
	 *
	 * @param string $objectType The object type.
	 * @param string $id         The UUID.
	 * @param string $missing    The reason when it cannot be read.
	 *
	 * @return array<string,mixed> The object.
	 *
	 * @throws PurchaseRefusedException 404 when it cannot be read.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	private function read(string $objectType, string $id, string $missing): array {
		try {
			return $this->fetcher->getObjectOfType(objectType: $objectType, id: $id);
		} catch (Throwable) {
			throw new PurchaseRefusedException(message: $missing, status: 404);
		}
	}//end read()

	/**
	 * The ids in a relation list, as strings.
	 *
	 * @param mixed $value The stored list.
	 *
	 * @return list<string> The ids.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	private function ids(mixed $value): array {
		if (is_array($value) === false) {
			return [];
		}

		$ids = [];
		foreach ($value as $entry) {
			if (is_array($entry) === true) {
				$entry = $entry['id'] ?? null;
			}

			if (is_scalar($entry) === true && (string)$entry !== '') {
				$ids[] = (string)$entry;
			}
		}

		return $ids;
	}//end ids()

	/**
	 * The id of an object.
	 *
	 * @param array<string,mixed> $object   The object.
	 * @param string              $fallback The id to use when it carries none.
	 *
	 * @return string The id.
	 *
	 * @spec openspec/changes/capability-rows-missing-requirements/specs/rpg-system/spec.md
	 */
	private function idOf(array $object, string $fallback): string {
		$id = $object['id'] ?? ($object['@self']['id'] ?? $fallback);

		return (string)$id;
	}//end idOf()
}//end class
