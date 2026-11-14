<?php

/**
 * OpenRegister ConfigLookup
 *
 * Test stand-in for OpenRegister's config lookup value object: the app id,
 * config key, fallback and organisation one RegisterResolverService::lookupId()
 * call reads.
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
 * One config lookup.
 */
final class ConfigLookup {

	/**
	 * Constructor.
	 *
	 * @param string      $appId            The consumer app id.
	 * @param string      $configKey        The config key to read.
	 * @param string|null $default          The fallback when the key is unset.
	 * @param string|null $organisationUuid The organisation the lookup is for.
	 */
	public function __construct(
		public readonly string $appId,
		public readonly string $configKey,
		public readonly ?string $default = null,
		public readonly ?string $organisationUuid = null,
	) {
	}//end __construct()
}//end class
