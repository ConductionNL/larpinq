<?php

/**
 * AgentCharacterSheet for Larpinq
 *
 * The calculated sheet an agent reads (hermiq-ai-tooling REQ-003): the
 * character read with the caller's rights, calculated, without
 * `slNotesPrivate`. A character the caller cannot read answers exactly like
 * one that does not exist, and nothing is written.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-003--a-curated-calculated-character-sheet-read-tool-exists
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

/**
 * Reads and calculates one character sheet for an agent.
 *
 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-003--a-curated-calculated-character-sheet-read-tool-exists
 */
class AgentCharacterSheet {

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher Reads the character with the caller's rights.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
	) {
	}//end __construct()

	/**
	 * The calculated sheet, or `{error: 'not-found', character}`.
	 *
	 * @param string   $characterId The character UUID.
	 * @param callable $calculate   Calculates a character array, {@see CharacterService::calculateCharacter()}.
	 *
	 * @return array<string, mixed> The sheet without `slNotesPrivate`, or the not-found answer.
	 *
	 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-003--a-curated-calculated-character-sheet-read-tool-exists
	 */
	public function read(string $characterId, callable $calculate): array {
		try {
			$character = $this->objectFetcher->getObject(objectType: 'character', id: $characterId);
		} catch (\Exception $exception) {
			$character = [];
		}

		if ($character === []) {
			return ['error' => 'not-found', 'character' => $characterId];
		}

		$sheet = $calculate($character);
		unset($sheet['slNotesPrivate']);

		return $sheet;
	}//end read()
}//end class
