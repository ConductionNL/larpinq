<?php

/**
 * UniqueHoldersCheck command for Larpinq
 *
 * `occ larpinq:unique-holders:check` lists every unique item and unique
 * condition that more than one character holds today, with the holders, and
 * exits non-zero while a conflict exists. The pre-write veto only fires when a
 * write adds a holder, so data from before it stays until a game master
 * cleans it up; this is how they find it.
 *
 * @category  Command
 * @package   OCA\Larpinq\Command
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Command;

use OCA\Larpinq\Service\UniqueHolderService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists the unique items and conditions held by more than one character.
 *
 * @category Command
 * @package  OCA\Larpinq\Command
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/rpg-system/spec.md
 */
class UniqueHoldersCheck extends Command {

	/**
	 * Constructor.
	 *
	 * @param UniqueHolderService $holders The holder lookup.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly UniqueHolderService $holders,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure the command name and help.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	protected function configure(): void {
		$this->setName('larpinq:unique-holders:check')
			->setDescription('List unique items and conditions that more than one character holds');
	}//end configure()

	/**
	 * List the conflicts; exit 1 while one exists.
	 *
	 * @param InputInterface $input The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return int 0 when no conflict exists, 1 otherwise.
	 *
	 * @spec openspec/specs/rpg-system/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$conflicts = $this->holders->findConflicts();
		if ($conflicts === []) {
			$output->writeln('No unique item or condition has more than one holder.');
			return 0;
		}

		foreach ($conflicts as $conflict) {
			$output->writeln(
				sprintf(
					'%s "%s" (%s) is held by: %s',
					$conflict['kind'],
					$conflict['name'],
					$conflict['id'],
					implode(', ', $conflict['holders'])
				)
			);
		}

		$output->writeln(sprintf('%d conflict(s). Remove all but one holder from each.', count($conflicts)));

		return 1;
	}//end execute()
}//end class
