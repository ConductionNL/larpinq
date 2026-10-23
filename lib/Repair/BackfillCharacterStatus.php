<?php

/**
 * BackfillCharacterStatus repair step for Larpinq
 *
 * Gives every stored character without a status the status `active`
 * (characters-status-and-bulk-edit). The event participant picker filters on
 * `status = active`, and a character stored before the status existed has
 * none, so without this step every existing character would drop out of the
 * picker.
 *
 * @category  Repair
 * @package   OCA\Larpinq\Repair
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/character-management/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Repair;

use OCA\Larpinq\Service\CharacterStatusGuard;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sets `status: active` on characters that have no status. Idempotent: a
 * character with any status is left alone. Never throws: a character that
 * cannot be saved is logged and the step goes on.
 *
 * @spec openspec/specs/character-management/spec.md
 */
class BackfillCharacterStatus implements IRepairStep {

	/**
	 * Characters read per page.
	 *
	 * @var int
	 */
	private const PAGE = 200;

	/**
	 * Constructor.
	 *
	 * @param RegisterObjectFetcher $objectFetcher Reads and writes characters through OpenRegister.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly RegisterObjectFetcher $objectFetcher,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function getName(): string {
		return 'Give Larpinq characters without a status the status active';
	}//end getName()

	/**
	 * Run the backfill.
	 *
	 * @param IOutput $output The output.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/character-management/spec.md
	 */
	public function run(IOutput $output): void {
		try {
			$missing = $this->charactersWithoutStatus();
		} catch (Throwable $e) {
			$output->warning('Could not read the characters, so none got a status: ' . $e->getMessage());
			return;
		}

		$done = 0;
		foreach ($missing as $character) {
			$uuid = (string)($character['id'] ?? '');
			unset($character['@self']);
			$character['status'] = CharacterStatusGuard::DEFAULT_STATUS;
			try {
				$this->objectFetcher->saveObject(objectType: 'character', data: $character, uuid: $uuid);
				$done++;
			} catch (Throwable $e) {
				$this->logger->warning('Larpinq: could not give character {id} a status', ['id' => $uuid, 'exception' => $e->getMessage()]);
			}
		}

		$output->info($done . ' of ' . count($missing) . ' characters without a status are now active.');
	}//end run()

	/**
	 * Every stored character that has no status. Read in full before any
	 * write, so the writes cannot shift the pages.
	 *
	 * @return array<int, array<string, mixed>> The characters.
	 */
	private function charactersWithoutStatus(): array {
		$missing = [];
		$offset = 0;
		do {
			$page = $this->objectFetcher->getObjects(objectType: 'character', limit: self::PAGE, offset: $offset);
			foreach ($page as $character) {
				if ((string)($character['status'] ?? '') === '' && (string)($character['id'] ?? '') !== '') {
					$missing[] = $character;
				}
			}

			$offset += self::PAGE;
			$full = count($page) === self::PAGE;
		} while ($full === true);

		return $missing;
	}//end charactersWithoutStatus()
}//end class
