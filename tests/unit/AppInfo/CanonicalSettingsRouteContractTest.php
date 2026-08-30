<?php

/**
 * Unit tests for the canonical settings route/method contract.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\AppInfo
 *
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2024 Ruben Linde <ruben@larpingapp.com>
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\AppInfo;

use OCA\Larpinq\Controller\SettingsController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Larpinq does NOT call `OCA\OpenRegister\AppHost\Routes::standard()`; it
 * declares its own short route table. That has two consequences, and this test
 * pins both:
 *
 *   1. Every canonical settings route the AppHost dialect defines has to be
 *      spelled out in `appinfo/routes.php` by hand. A missing verb is not a
 *      500 here — it is a 405 Method Not Allowed, because the URL matches but
 *      the verb does not. Measured on the dev instance 2026-08-08 before this
 *      change: `GET /apps/larpinq/api/settings` -> 200,
 *      `POST` -> 200, `PUT` -> 405.
 *   2. Because the app ships `lib/Controller/SettingsController.php` itself,
 *      `OCA\OpenRegister\AppHost\Bootstrap::aliasControllerUnlessLeafDefinesIt()`
 *      never aliases the generic controller in, so this leaf owes every
 *      `settings#` method its own route table names. A routed name with no
 *      method behind it is a ReflectionException 500 (ADR-029, gate-14).
 *
 * The assertions below are on the ITEM — each individual route entry, each
 * individual method — never on the container (the route array being non-empty,
 * or the controller class merely existing). Both tests carry a positive
 * control so a probe that matched nothing cannot read as a pass.
 *
 * @spec openspec/specs/settings-management-ui/spec.md#REQ-003
 */
class CanonicalSettingsRouteContractTest extends TestCase {

	/**
	 * The settings surface this app's own route table must publish.
	 *
	 * `[route name, url, verb, controller method]`.
	 *
	 * @var array<int, array{0: string, 1: string, 2: string, 3: string}>
	 */
	private const CANONICAL_SETTINGS_ROUTES = [
		['settings#index', 'api/settings', 'GET', 'index'],
		['settings#create', 'api/settings', 'POST', 'create'],
		['settings#update', 'api/settings', 'PUT', 'update'],
		['settings#reimport', 'api/settings/reimport', 'POST', 'reimport'],
	];

	/**
	 * The evaluated contents of `appinfo/routes.php`.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $routes = [];

	/**
	 * Evaluate the real route table — not a grep over its source.
	 *
	 * A substring search would happily match the route name inside a comment,
	 * or inside an entry that was commented out. Including the file and reading
	 * the array it returns is the only measurement that reflects what Nextcloud
	 * actually registers.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$table = include __DIR__ . '/../../../appinfo/routes.php';

		self::assertIsArray($table, 'appinfo/routes.php must return an array');
		self::assertArrayHasKey('routes', $table, "appinfo/routes.php must return a 'routes' key");

		$this->routes = $table['routes'];

	}//end setUp()

	/**
	 * Every canonical settings route must be present as a real array entry.
	 *
	 * Nextcloud's RouteParser ltrims the leading slash from `url`
	 * (`$url = $root.'/'.ltrim($route['url'], '/')`), so `api/settings` and
	 * `/api/settings` register identically; the comparison normalises for that
	 * rather than pinning one spelling.
	 *
	 * @return void
	 */
	public function testRouteTableDeclaresEveryCanonicalSettingsRoute(): void {
		$inspected = 0;
		$missing = [];

		foreach (self::CANONICAL_SETTINGS_ROUTES as [$name, $url, $verb, $method]) {
			$inspected++;

			$found = false;
			foreach ($this->routes as $route) {
				if (($route['name'] ?? null) !== $name) {
					continue;
				}

				if (ltrim((string)($route['url'] ?? ''), '/') !== ltrim($url, '/')) {
					continue;
				}

				if (($route['verb'] ?? null) !== $verb) {
					continue;
				}

				$found = true;
				break;
			}

			if ($found === false) {
				$missing[] = sprintf('%s (%s %s)', $name, $verb, $url);
			}
		}//end foreach

		// Positive control: an empty $missing list is only evidence if the loop
		// above actually evaluated something. Zero inspections would mean the
		// constant was emptied and every assertion below passed vacuously.
		self::assertGreaterThan(
			0,
			$inspected,
			'No canonical settings route was inspected — the expectation table is empty, '
			. 'so an empty findings list means nothing.'
		);

		self::assertSame(
			[],
			$missing,
			sprintf(
				'appinfo/routes.php is missing canonical settings route(s). A URL that '
				. "matches with an unregistered verb answers 405 Method Not Allowed:\n  - %s",
				implode("\n  - ", $missing)
			)
		);

	}//end testRouteTableDeclaresEveryCanonicalSettingsRoute()

	/**
	 * PUT /api/settings specifically must resolve to `settings#update`.
	 *
	 * Pinned on its own — this is the route the fleet-wide convergence adds,
	 * and the one whose absence was measured as a 405.
	 *
	 * @return void
	 */
	public function testPutApiSettingsIsRoutedToSettingsUpdate(): void {
		$puts = [];
		foreach ($this->routes as $route) {
			if (($route['verb'] ?? null) !== 'PUT') {
				continue;
			}

			if (ltrim((string)($route['url'] ?? ''), '/') !== 'api/settings') {
				continue;
			}

			$puts[] = $route['name'] ?? '(unnamed)';
		}

		self::assertSame(
			['settings#update'],
			$puts,
			'Exactly one route must answer PUT /api/settings, and it must be settings#update. '
			. 'Found: ' . var_export($puts, true)
		);

	}//end testPutApiSettingsIsRoutedToSettingsUpdate()

	/**
	 * Every settings route name must have a public method behind it.
	 *
	 * The route table matching a URL while the target method does not exist is
	 * a ReflectionException 500 at dispatch time, not a 404 — the failure gate-14
	 * (route-reachability) exists for.
	 *
	 * @return void
	 */
	public function testEverySettingsRouteHasAPublicControllerMethod(): void {
		$reflection = new ReflectionClass(SettingsController::class);

		$inspected = 0;
		$missing = [];

		foreach ($this->routes as $route) {
			$name = (string)($route['name'] ?? '');
			if (str_starts_with($name, 'settings#') === false) {
				continue;
			}

			$inspected++;
			$method = substr($name, strlen('settings#'));

			if ($reflection->hasMethod($method) === false) {
				$missing[] = SettingsController::class . '::' . $method . '()';
				continue;
			}

			self::assertTrue(
				$reflection->getMethod($method)->isPublic(),
				sprintf('%s::%s() must be public to be dispatchable', SettingsController::class, $method)
			);
		}//end foreach

		// Positive control: if the `settings#` prefix probe matched nothing the
		// empty $missing list would be manufactured by a broken scan, not by a
		// healthy controller.
		self::assertGreaterThan(
			0,
			$inspected,
			'No settings# route was inspected — the route-name probe matched nothing, '
			. 'so the empty findings list is not evidence.'
		);

		self::assertSame(
			[],
			$missing,
			sprintf(
				'appinfo/routes.php routes to method(s) SettingsController does not define. '
				. "Each is a dispatch-time 500:\n  - %s",
				implode("\n  - ", $missing)
			)
		);

	}//end testEverySettingsRouteHasAPublicControllerMethod()

	/**
	 * `update()` and `create()` must share one auth posture.
	 *
	 * `update()` is a strict addition that must not widen or narrow access:
	 * SecurityMiddleware evaluates attributes on the DISPATCHED method only, so
	 * `create()` delegating into `update()` gives the two independent postures
	 * that have to be kept equal by hand. Both currently carry NO auth
	 * attribute, which is Nextcloud's fail-closed default (admin session + CSRF
	 * token required) — the correct posture for an instance-wide config write.
	 *
	 * @return void
	 */
	public function testUpdateAndCreateShareTheSameAuthPosture(): void {
		$reflection = new ReflectionClass(SettingsController::class);

		$attributeNames = static function (string $method) use ($reflection): array {
			$names = array_map(
				static fn ($attribute): string => $attribute->getName(),
				$reflection->getMethod($method)->getAttributes()
			);
			sort($names);
			return $names;
		};

		self::assertSame(
			$attributeNames('create'),
			$attributeNames('update'),
			'create() and update() must declare identical auth attributes — the write path '
			. 'must not change privilege when the canonical PUT verb is added.'
		);

		// And that shared posture must remain fail-closed: no #[NoAdminRequired]
		// and no #[PublicPage] on either write.
		foreach (['create', 'update'] as $method) {
			self::assertNotContains(
				'OCP\AppFramework\Http\Attribute\NoAdminRequired',
				$attributeNames($method),
				sprintf('%s() writes instance-wide config and must not be #[NoAdminRequired]', $method)
			);
			self::assertNotContains(
				'OCP\AppFramework\Http\Attribute\PublicPage',
				$attributeNames($method),
				sprintf('%s() writes instance-wide config and must not be #[PublicPage]', $method)
			);
		}

	}//end testUpdateAndCreateShareTheSameAuthPosture()

}//end class
