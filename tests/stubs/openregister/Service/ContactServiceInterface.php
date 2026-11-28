<?php

/**
 * OpenRegister ContactServiceInterface
 *
 * Test stand-in for OpenRegister's contact service: the one read Larpinq makes,
 * the contact links of an object.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Service
 * @package  OCA\OpenRegister\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://OpenRegister.app
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service;

/**
 * The contact links of an object, as OpenRegister's ContactService answers them.
 */
interface ContactServiceInterface {

	/**
	 * The contact links of an object.
	 *
	 * @param string $objectUuid The object UUID.
	 *
	 * @return array{results: array, total: int}
	 */
	public function getContactsForObject(string $objectUuid): array;
}
