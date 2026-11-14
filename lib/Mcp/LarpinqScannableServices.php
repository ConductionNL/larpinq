<?php

/**
 * LarpinqScannableServices
 *
 * The service classes OpenRegister scans for Larpinq's `#[McpTool]` methods
 * (hermiq-ai-tooling REQ-004): the event-scoped XP award and the calculated
 * character sheet. Everything else an agent can do in Larpinq is a derived
 * tool from the register's MCP dialect; this list adds exactly those two.
 *
 * @category  Mcp
 * @package   OCA\Larpinq\Mcp
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-004--curated-tools-are-exposed-via-the-scannable-services-opt-in
 */

declare(strict_types=1);

namespace OCA\Larpinq\Mcp;

use OCA\Larpinq\Service\CharacterService;
use OCA\Larpinq\Service\EventRosterService;
use OCA\OpenRegister\Mcp\IMcpScannableServices;

/**
 * The opt-in list of Larpinq classes with `#[McpTool]` methods.
 *
 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-004--curated-tools-are-exposed-via-the-scannable-services-opt-in
 */
class LarpinqScannableServices implements IMcpScannableServices {

	/**
	 * The classes OpenRegister scans.
	 *
	 * @return list<class-string> The classes.
	 *
	 * @spec openspec/changes/hermiq-ai-tooling/specs/hermiq-ai-tooling/spec.md#req-004--curated-tools-are-exposed-via-the-scannable-services-opt-in
	 */
	public function getScannableServiceClasses(): array {
		return [EventRosterService::class, CharacterService::class];
	}//end getScannableServiceClasses()
}//end class
