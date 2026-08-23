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
 * placeholders live here instead. Every declaration is class_exists()-guarded,
 * so this is a no-op the moment a real Nextcloud runtime supplies the genuine
 * classes. Same approach dossiq and humaniq take, for the same reason.
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

	if (class_exists(\Doctrine\DBAL\Connection::class) === false) {
		class Connection {
		}
	}

	if (class_exists(\Doctrine\DBAL\Exception::class) === false) {
		class Exception extends \RuntimeException {
		}
	}
}

namespace Doctrine\DBAL\Schema {
	if (class_exists(\Doctrine\DBAL\Schema\Schema::class) === false) {
		class Schema {
		}
	}

	if (class_exists(\Doctrine\DBAL\Schema\Table::class) === false) {
		class Table {
		}
	}
}
