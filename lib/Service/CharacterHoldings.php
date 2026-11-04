<?php

/**
 * CharacterHoldings for Larpinq
 *
 * The character side of one conflict scan: which characters hold each item or
 * condition according to their own `items[]` / `conditions[]`, and every
 * character's name.
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
 * The characters that hold each object of one kind, by name.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */
final class CharacterHoldings {

	/**
	 * The kind the holdings are read for, 'item' or 'condition'.
	 *
	 * @var string
	 */
	private string $kind = '';

	/**
	 * Constructor.
	 *
	 * @param array<string,array<string,array<string,string>>> $held kind => objectId => [characterId => name].
	 * @param array<string,string> $names characterId => name.
	 */
	public function __construct(
		private readonly array $held,
		private readonly array $names,
	) {
	}//end __construct()

	/**
	 * The same holdings, read for one kind.
	 *
	 * @param string $kind 'item' or 'condition'.
	 *
	 * @return self The holdings for that kind.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function forKind(string $kind): self {
		$copy = clone $this;
		$copy->kind = $kind;

		return $copy;
	}//end forKind()

	/**
	 * The kind these holdings are read for.
	 *
	 * @return string 'item' or 'condition'.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function kind(): string {
		return $this->kind;
	}//end kind()

	/**
	 * Every holder of one object, sorted by name.
	 *
	 * The character side comes first; an id from the object's own
	 * `characters[]` adds that character's name, or the id when no character
	 * has it.
	 *
	 * @param string $objectId The item or condition id.
	 * @param array<int,string> $objectSide The character ids on the object itself.
	 *
	 * @return array<int,string> The holder names.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	public function holderNames(string $objectId, array $objectSide): array {
		$holders = $this->held[$this->kind][$objectId] ?? [];
		foreach ($objectSide as $characterId) {
			$holders[$characterId] ??= ($this->names[$characterId] ?? $characterId);
		}

		sort($holders);

		return $holders;
	}//end holderNames()
}//end class
