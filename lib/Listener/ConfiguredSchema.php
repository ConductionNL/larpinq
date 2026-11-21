<?php

/**
 * Larpinq Configured Schema
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Listener;

use OCA\Larpinq\Support\AppIdentity;
use OCP\IAppConfig;

/**
 * The schema id stored under one app config key, matched against an
 * OpenRegister object entity.
 *
 * @category Listener
 * @package  OCA\Larpinq\Listener
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/specs/portal-contribution/spec.md
 */
final class ConfiguredSchema {

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $config The app config holding the schema id.
	 * @param string     $key    The config key of the schema id.
	 */
	public function __construct(
		private readonly IAppConfig $config,
		private readonly string $key,
	) {
	}//end __construct()

	/**
	 * Whether an entity belongs to the configured schema.
	 *
	 * @param object $entity The OpenRegister object entity.
	 *
	 * @return bool True when a schema is configured and the entity carries it.
	 *
	 * @psalm-suppress MixedMethodCall OpenRegister entity classes are optional dependencies.
	 *
	 * @spec openspec/specs/portal-contribution/spec.md
	 */
	public function matches(object $entity): bool {
		$schema = $this->config->getValueString(AppIdentity::APP_ID, $this->key, '');

		return $schema !== ''
			&& is_callable([$entity, 'getSchema']) === true
			&& (string)$entity->getSchema() === $schema;
	}//end matches()
}//end class
