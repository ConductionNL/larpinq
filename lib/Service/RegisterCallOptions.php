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
 * @spec openspec/changes/larpinq-runsheet-scoped-cast-query/tasks.md#5-quality
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
 * @spec openspec/changes/larpinq-runsheet-scoped-cast-query/tasks.md#5-quality
 */
class RegisterCallOptions {

	/**
	 * The options of a find in this register and schema.
	 *
	 * @param string $register The register id.
	 * @param string $schema   The schema id.
	 *
	 * @return ObjectFindOptions
	 *
	 * @psalm-suppress UndefinedClass OpenRegister is an optional dependency.
	 *
	 * @spec openspec/changes/larpinq-runsheet-scoped-cast-query/tasks.md#5-quality
	 */
	public function find(string $register, string $schema): ObjectFindOptions {
		return new ObjectFindOptions(register: $register, schema: $schema);
	}//end find()

	/**
	 * The options of a save into this register and schema; a null uuid creates.
	 *
	 * @param string      $register The register id.
	 * @param string      $schema   The schema id.
	 * @param string|null $uuid     The object to update, or null to create.
	 *
	 * @return ObjectSaveOptions
	 *
	 * @psalm-suppress UndefinedClass OpenRegister is an optional dependency.
	 *
	 * @spec openspec/changes/larpinq-runsheet-scoped-cast-query/tasks.md#5-quality
	 */
	public function save(string $register, string $schema, ?string $uuid): ObjectSaveOptions {
		return new ObjectSaveOptions(target: new ObjectFindTarget(register: $register, schema: $schema), uuid: $uuid);
	}//end save()

	/**
	 * The deletion of one object in this register and schema.
	 *
	 * @param string $uuid     The object.
	 * @param string $register The register id.
	 * @param string $schema   The schema id.
	 *
	 * @return ObjectDeletion
	 *
	 * @psalm-suppress UndefinedClass OpenRegister is an optional dependency.
	 *
	 * @spec openspec/changes/larpinq-runsheet-scoped-cast-query/tasks.md#5-quality
	 */
	public function deletion(string $uuid, string $register, string $schema): ObjectDeletion {
		return new ObjectDeletion(uuid: $uuid, target: new ObjectFindTarget(register: $register, schema: $schema));
	}//end deletion()
}//end class
