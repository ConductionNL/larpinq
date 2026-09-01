<?php

/**
 * Larpinq MetricsController.
 *
 * AppHost adopter by COMPOSITION, not inheritance: the OpenRegister AppHost
 * Prometheus engine is resolved lazily out of the DI container by FQCN string
 * and rendered as text exposition 0.0.4.
 *
 * `\OCA\OpenRegister\AppHost\Routes::standard()` emits `metrics#index` for
 * every adopter, so this route existed in larpinq's table with no class behind
 * it until this file landed. gate-14 route-reachability named it
 * `controller-class-not-found`.
 *
 * ⚠️ This class MUST NOT `extends` — nor name in any resolved position — a
 * class from another app. Nextcloud's router `ReflectionClass()`es every file
 * in `lib/Controller/` while MATCHING a route, so an unresolvable parent makes
 * EVERY route in larpinq return HTTP 500, not just this one.
 *
 * @category Controller
 * @package  OCA\Larpinq\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git_id>
 *
 * @link https://github.com/ConductionNL/larpinq
 *
 * @spec openspec/specs/apphost-adoption/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Controller;

use OCA\Larpinq\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IRequest;
use Psr\Container\ContainerInterface;

/**
 * Admin-only declarative Prometheus metrics endpoint backed by the AppHost engine.
 *
 * No `#[NoAdminRequired]` — the absence of that attribute means NC requires an
 * admin session, which is the intended ADR-006 posture for metrics. Anonymous
 * callers get the NC login redirect / 401, never metric data.
 *
 * @psalm-suppress UnusedClass
 *
 * @spec openspec/specs/apphost-adoption/spec.md
 */
class MetricsController extends Controller {

	/**
	 * FQCN of the AppHost observability manifest loader.
	 *
	 * Referenced as a string, never imported: the class only exists when
	 * openregister is installed.
	 *
	 * @var string
	 */
	private const MANIFEST_LOADER = 'OCA\\OpenRegister\\AppHost\\Observability\\ManifestLoader';

	/**
	 * FQCN of the AppHost Prometheus metrics engine.
	 *
	 * Referenced as a string, never imported: the class only exists when
	 * openregister is installed.
	 *
	 * @var string
	 */
	private const METRICS_ENGINE = 'OCA\\OpenRegister\\AppHost\\Observability\\MetricsEngine';

	/**
	 * Prometheus text exposition content type (mirrors the engine's renderer).
	 *
	 * @var string
	 */
	private const CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8';

	/**
	 * Constructor.
	 *
	 * @param IRequest $request The HTTP request.
	 * @param ContainerInterface $container DI container — resolves the AppHost engine lazily.
	 *
	 * @return void
	 */
	public function __construct(
		IRequest $request,
		private readonly ContainerInterface $container,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);

	}//end __construct()

	/**
	 * GET /api/metrics — declarative Prometheus metrics (admin-only, ADR-006).
	 *
	 * Admin-only by the deliberate absence of `#[NoAdminRequired]`.
	 *
	 * Returns HTTP 503 with a Prometheus comment line when the AppHost engine
	 * is unavailable (openregister absent or disabled) — never a 500.
	 *
	 * @return TextPlainResponse Prometheus text exposition 0.0.4.
	 *
	 * @spec openspec/specs/apphost-adoption/spec.md
	 */
	#[NoCSRFRequired]
	public function index(): TextPlainResponse {
		try {
			$manifestLoader = $this->container->get(self::MANIFEST_LOADER);
			$engine = $this->container->get(self::METRICS_ENGINE);

			$manifest = $manifestLoader->load(appId: $this->appName);
			$body = (string)$engine->render(manifest: $manifest);
			$status = Http::STATUS_OK;
		} catch (\Throwable $e) {
			$body = '# metrics unavailable: the OpenRegister AppHost observability engine is not installed' . "\n";
			$status = Http::STATUS_SERVICE_UNAVAILABLE;
		}//end try

		$response = new TextPlainResponse($body, $status);
		$response->addHeader('Content-Type', self::CONTENT_TYPE);

		return $response;
	}//end index()
}//end class
