<?php

/**
 * Contract of DemoDataService
 *
 * The public surface of DemoDataService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category  Kernel
 * @package   OCA\Larpinq\Kernel\Contracts
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Kernel\Contracts;

use RuntimeException;

/**
 * Contract of DemoDataService.
 *
 * @category Kernel
 * @package  OCA\Larpinq\Kernel\Contracts
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://github.com/ConductionNL/larpinq
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface DemoDataServiceInterface {

	/**
	 * The answer that means "plant nothing".
	 *
	 * 🔴 NOT THE ABSENCE OF AN ANSWER. An operator who declines has FINISHED the
	 * step; a step that can never be marked done reopens the wizard over every
	 * page (nextcloud-vue#806).
	 *
	 * @var string
	 */
	public const NONE_DATASET = 'none';

	/**
	 * The id of the dataset this app ships.
	 *
	 * @var string
	 */
	public const DEMO_DATASET = 'demo';

	/**
	 * Every answer the wizard's choice step may offer, declining included.
	 *
	 * 🔴 THE SERVER OWNS THIS LIST, AND THAT IS THE POINT. The step declares
	 * `optionsSource: datasets` and no options of its own, so the label, the
	 * description and the object count come from the descriptor that will
	 * actually be imported. A manifest that restated them could disagree with
	 * what lands, and nothing would notice.
	 *
	 * @return array<int, array{id: string, label: string, description: string, objectCount: integer, icon: string}> The answers.
	 *
	 * @spec exclude Demo-data choice list; ADR-111 rule 1 has no per-app behavioural spec.
	 */
	public function listChoices(): array;

	/**
	 * Import the demo dataset.
	 *''
	 * 🔴 THROWS RATHER THAN RETURNING A QUIET FAILURE. Every caller reports the
	 * outcome to an operator who just asked for this, so "nothing happened"
	 * must not be presentable as success.
	 *
	 * @return array{objects: integer, registers: integer, schemas: integer} What was imported.
	 *
	 * @throws RuntimeException When the descriptor is missing, unreadable, or OpenRegister is absent.
	 *
	 * @spec exclude Demo-data install action (ADR-111 rule 4); no per-app openspec change yet.
	 */
	public function install(): array;
}//end interface
