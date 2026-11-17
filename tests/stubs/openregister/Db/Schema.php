<?php

/**
 * Test-only stub of OpenRegister's Schema entity. The real class
 * (openregister/lib/Db/Schema.php) cannot load in a bare unit-test process;
 * larpinq only hands it on to OpenRegister's form destination validator, so
 * the stub keeps the entity base class and the properties accessor.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A register schema.
 */
class Schema extends Entity {
	/** @var array<string, mixed>|null The JSON-schema properties. */
	protected ?array $properties = [];

	/**
	 * The schema's properties.
	 *
	 * @return array<string, mixed>
	 */
	public function getProperties(): array {
		return ($this->properties ?? []);
	}
}
