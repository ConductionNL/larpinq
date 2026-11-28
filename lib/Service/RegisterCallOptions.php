<?php

/**
 * RegisterCallOptions for Larpinq
 *
 * Builds the OpenRegister value objects that name a register and schema in a
 * find, save or delete call.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://larpingapp.com
 *
 * @spec openspec/specs/object-service/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCA\OpenRegister\Kernel\Contracts\Value\ObjectDeletion;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindOptions;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectFindTarget;
use OCA\OpenRegister\Kernel\Contracts\Value\ObjectSaveOptions;

/**
 * The OpenRegister call options for one register and schema.
 *
 * Kept out of RegisterObjectFetcher so the fetcher stays under the phpmd
 * coupling limit.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @psalm-suppress UndefinedClass OpenRegister is an optional dependency.
 *
 * @spec openspec/specs/object-service/spec.md
 */
class RegisterCallOptions {

	/**
	 * The options of a find in this register and schema.
	 *
	 * @param array{0: string, 1: string} $target The register id and the schema id.
	 *
	 * @return ObjectFindOptions
	 *
	 * @psalm-suppress UndefinedClass OpenRegister is an optional dependency.
	 *
	 * @spec openspec/specs/object-service/spec.md
	 */
	public function find(array $target): ObjectFindOptions {
		return new ObjectFindOptions(register: $target[0], schema: $target[1]);
	}//end find()

	/**
	 * The options of a save into this register and schema; a null uuid creates.
	 *
	 * @param array{0: string, 1: string} $target The register id and the schema id.
	 * @param string|null                 $uuid   The object to update, or null to create.
	 *
	 * @return ObjectSaveOptions
	 *
	 * @psalm-suppress UndefinedClass OpenRegister is an optional dependency.
	 *
	 * @spec openspec/specs/object-service/spec.md
	 */
	public function save(array $target, ?string $uuid): ObjectSaveOptions {
		return new ObjectSaveOptions(target: new ObjectFindTarget(register: $target[0], schema: $target[1]), uuid: $uuid);
	}//end save()

	/**
	 * The deletion of one object in this register and schema.
	 *
	 * @param string                      $uuid   The object.
	 * @param array{0: string, 1: string} $target The register id and the schema id.
	 *
	 * @return ObjectDeletion
	 *
	 * @psalm-suppress UndefinedClass OpenRegister is an optional dependency.
	 *
	 * @spec openspec/specs/object-service/spec.md
	 */
	public function deletion(string $uuid, array $target): ObjectDeletion {
		return new ObjectDeletion(uuid: $uuid, target: new ObjectFindTarget(register: $target[0], schema: $target[1]));
	}//end deletion()
}//end class
