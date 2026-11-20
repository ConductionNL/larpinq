<?php

/**
 * Builds OpenRegister's real PermissionHandler for the evaluator tests.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Support
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec exclude Test support code, not product behaviour.
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Support;

/**
 * One construction site for the real handler, whichever constructor it has.
 *
 * OpenRegister moved the handler's collaborators into grouped value objects
 * (`Permission\PermissionCallerServices` and friends). An app's tests run
 * against whatever OpenRegister sits beside it, so this builds the grouped
 * form when that OpenRegister ships it and the flat named arguments otherwise.
 */
final class RealPermissionHandler {

	/**
	 * Build the handler from its collaborators.
	 *
	 * The keys of `$deps` are the flat constructor's argument names: userSession,
	 * userManager, groupManager, schemaMapper, objectEntityMapper,
	 * conditionMatcher, appConfig, logger and container.
	 *
	 * @param array<string, object> $deps The collaborators.
	 *
	 * @return object The real PermissionHandler.
	 */
	public static function build(array $deps): object {
		$handler = '\\OCA\\OpenRegister\\Service\\Object\\PermissionHandler';
		$group = '\\OCA\\OpenRegister\\Service\\Object\\Permission\\';
		if (class_exists($group . 'PermissionCallerServices') === false) {
			return new $handler(...$deps);
		}

		$caller = $group . 'PermissionCallerServices';
		$data = $group . 'PermissionDataServices';
		$rules = $group . 'PermissionRuleServices';
		$grants = $group . 'PermissionGrantServices';
		$deny = $group . 'PermissionDenyServices';
		$derived = $group . 'PermissionDerivedGrantServices';

		return new $handler(
			caller: new $caller(
				userSession: $deps['userSession'],
				userManager: $deps['userManager'],
				groupManager: $deps['groupManager'],
				appConfig: $deps['appConfig'],
			),
			data: new $data(
				schemaMapper: $deps['schemaMapper'],
				objectEntityMapper: $deps['objectEntityMapper'],
				container: $deps['container'],
				logger: $deps['logger'],
			),
			rules: new $rules(conditionMatcher: $deps['conditionMatcher']),
			grants: new $grants(deny: new $deny(), derived: new $derived()),
		);
	}//end build()
}//end class
