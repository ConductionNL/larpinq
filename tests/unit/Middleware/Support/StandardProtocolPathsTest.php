<?php

/**
 * Unit tests for the standard protocol paths.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Middleware\Support
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Middleware\Support;

use OCA\Larpinq\Middleware\Support\StandardProtocolPaths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Which paths are standard protocol endpoints, and how a request path is made
 * relative to the app.
 *
 * @covers \OCA\Larpinq\Middleware\Support\StandardProtocolPaths
 */
class StandardProtocolPathsTest extends TestCase {

	/**
	 * Request paths and their app-relative form.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function relativePaths(): array {
		return [
			'front controller'   => ['/index.php/apps/larpinq/api/health', '/api/health'],
			'pretty url'         => ['/apps/larpinq/api/things/7', '/api/things/7'],
			'already relative'   => ['/api/things', '/api/things'],
			'no leading slash'   => ['api/things', '/api/things'],
			'many leading'       => ['///api/things', '/api/things'],
			'other app'          => ['/apps/shillinq/api/health', '/apps/shillinq/api/health'],
			'app root only'      => ['/apps/larpinq', '/apps/larpinq'],
			'app root slash'     => ['/apps/larpinq/', '/'],
			'similar app prefix' => ['/apps/larpinqx/api/health', '/apps/larpinqx/api/health'],
			'empty'              => ['', '/'],
		];
	}//end relativePaths()

	/**
	 * Each path is made relative to the app root, and only to this app's root.
	 *
	 * @param string $path     The request path.
	 * @param string $relative The expected app-relative path.
	 *
	 * @return void
	 */
	#[DataProvider('relativePaths')]
	public function testThePathIsMadeAppRelative(string $path, string $relative): void {
		$this->assertSame(expected: $relative, actual: StandardProtocolPaths::appRelative(path: $path));
	}//end testThePathIsMadeAppRelative()

	/**
	 * Paths and whether they are a standard protocol endpoint.
	 *
	 * @return array<string,array{0:string,1:bool}>
	 */
	public static function protocolPaths(): array {
		return [
			'health'                 => ['/api/health', true],
			'metrics'                => ['/api/metrics', true],
			'health via app'         => ['/index.php/apps/larpinq/api/health', true],
			'metrics via app'        => ['/apps/larpinq/api/metrics', true],
			'health trailing slash'  => ['/api/health/', false],
			'health subpath'         => ['/api/health/db', false],
			'versioned health'       => ['/api/v1/health', false],
			'other endpoint'         => ['/api/things', false],
			'other app health'       => ['/apps/shillinq/api/health', false],
			'healthy prefix'         => ['/api/healthz', false],
		];
	}//end protocolPaths()

	/**
	 * Only the exact health and metrics endpoints of this app match.
	 *
	 * @param string $path    The request path.
	 * @param bool   $matches Whether it is a protocol endpoint.
	 *
	 * @return void
	 */
	#[DataProvider('protocolPaths')]
	public function testOnlyTheProtocolEndpointsMatch(string $path, bool $matches): void {
		$this->assertSame(expected: $matches, actual: StandardProtocolPaths::matches(path: $path));
	}//end testOnlyTheProtocolEndpointsMatch()

	/**
	 * The patterns name exactly the two protocol endpoints.
	 *
	 * @return void
	 */
	public function testThePatternsNameHealthAndMetrics(): void {
		$this->assertSame(
			expected: ['Health check', 'Prometheus metrics'],
			actual: array_keys(StandardProtocolPaths::PATTERNS)
		);
	}//end testThePatternsNameHealthAndMetrics()
}//end class
