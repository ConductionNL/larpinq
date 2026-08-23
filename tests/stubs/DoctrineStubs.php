<?php

/**
 * Doctrine DBAL stubs for the unit suite.
 *
 * `OCP\DB\QueryBuilder\IQueryBuilder` evaluates class constants that reference
 * `Doctrine\DBAL\ParameterType` AT PARSE TIME. `IDBConnection::getQueryBuilder()`
 * declares IQueryBuilder as its return type, so PHPUnit cannot even build a mock
 * of IDBConnection until those Doctrine names are in the class table — the
 * failure is `Class "Doctrine\DBAL\ParameterType" not found`, raised from inside
 * createMock(), which reads as a broken test rather than a missing dev
 * dependency.
 *
 * This app does not ship doctrine/dbal (nothing in lib/ needs it), so the
 * placeholders live here instead.
 *
 * ONLY THE TWO CONSTANT HOLDERS ARE STUBBED, AND THAT RESTRICTION IS
 * LOAD-BEARING. A first version stubbed `Doctrine\DBAL\Connection` as well and
 * killed the CI run outright:
 *
 *   PHP Fatal error: OC\DB\Connection::createQueryBuilder() has #[\Override]
 *   attribute, but no matching parent method exists
 *
 * — because Nextcloud's own `OC\DB\Connection` EXTENDS the Doctrine one, and it
 * inherited an empty stub instead of the real class. The class_exists() guard
 * does not save you there: at the moment this file runs, the genuine class is
 * not yet reachable, so the guard passes and the stub wins the name for the rest
 * of the process.
 *
 * So the rule is: stub a class nothing extends, never one something inherits
 * from. ParameterType and ArrayParameterType are only ever read as constants.
 * The failure is also invisible locally, where no real Nextcloud is present —
 * it only appears in a full-server CI leg.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Stubs
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace Doctrine\DBAL {
	if (class_exists(\Doctrine\DBAL\ParameterType::class) === false) {
		class ParameterType {
			public const NULL = 0;
			public const INTEGER = 1;
			public const STRING = 2;
			public const BINARY = 3;
			public const BOOLEAN = 5;
			public const LARGE_OBJECT = 6;
		}
	}

	if (class_exists(\Doctrine\DBAL\ArrayParameterType::class) === false) {
		class ArrayParameterType {
			public const STRING = 101;
			public const INTEGER = 102;
			public const ASCII = 103;
		}
	}

}

// NOTHING ELSE IS STUBBED, AND THAT IS THE POINT — see the note above.
