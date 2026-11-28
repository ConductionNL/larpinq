<?php

/**
 * Answers the doubles of IAppConfig and the DI container give in the register tests.
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

use RuntimeException;

/**
 * Config and container answers shared by the in-memory register tests.
 */
final class RegisterAnswers {

	/**
	 * The configured value for a key: register 3 for every `*_register`, the
	 * object type itself for every `*_schema`, and the default otherwise.
	 *
	 * @param string $key     The config key.
	 * @param string $default The default the caller passed.
	 *
	 * @return string The value.
	 */
	public static function registerConfig(string $key, string $default): string {
		if (str_ends_with($key, '_register') === true) {
			return '3';
		}

		if (str_ends_with($key, '_schema') === true) {
			return substr($key, 0, -strlen('_schema'));
		}

		return $default;
	}//end registerConfig()

	/**
	 * The service a container double holds for one id; any other id is not bound.
	 *
	 * @param string $id      The requested id.
	 * @param string $bound   The one id the container knows.
	 * @param object $service The service bound to it.
	 *
	 * @return object The service.
	 *
	 * @throws RuntimeException For any other id.
	 */
	public static function onlyService(string $id, string $bound, object $service): object {
		if ($id !== $bound) {
			throw new RuntimeException('not bound: ' . $id);
		}

		return $service;
	}//end onlyService()
}//end class
