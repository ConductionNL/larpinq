<?php

/**
 * Larpinq Register Schema Lookup
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use Exception;
use OCA\Larpinq\Support\AppIdentity;
use OCP\IAppConfig;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolves the register and schema ids configured for an object type, for
 * RegisterObjectFetcher: through OpenRegister's RegisterResolverService when
 * it is available, else from the app config directly.
 *
 * @psalm-suppress UndefinedClass, UndefinedDocblockClass OpenRegister is an optional dependency.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
 */
class RegisterSchemaLookup {

	/**
	 * OpenRegister's config lookup, reached by name: it exists only when OpenRegister is installed.
	 */
	private const CONFIG_LOOKUP = 'OCA\\OpenRegister\\Service\\ConfigLookup';

	/**
	 * The name of the application.
	 *
	 * @var string
	 */
	private string $appName = AppIdentity::APP_ID;

	/**
	 * Whether the resolver-absence deprecation warning has already been logged
	 * this request. Avoids flooding the log on every getMapper() call.
	 *
	 * @var boolean
	 */
	private bool $fallbackLogged = false;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container DI container.
	 * @param IAppConfig $config Config service.
	 * @param LoggerInterface $logger Logger for the resolver fallback path.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly IAppConfig $config,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Resolve the configured register and schema IDs for an object type.
	 *
	 * Prefers OpenRegister's RegisterResolverService when it is available and
	 * falls back to a direct IAppConfig::getValueString lookup, with the same
	 * "not configured" exceptions, when it is absent.
	 *
	 * @param string $objectTypeLower The lower-cased object type slug.
	 *
	 * @return array{0: string, 1: string} A [registerId, schemaId] tuple.
	 *
	 * @throws Exception If the register or schema is not configured.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-38
	 */
	public function resolve(string $objectTypeLower): array {
		$resolver = $this->getRegisterResolver();

		if ($resolver !== null) {
			return $this->resolveThroughResolver(resolver: $resolver, objectTypeLower: $objectTypeLower);
		}

		// Legacy fallback: resolver unavailable. Behaviour-preserving.
		$this->logResolverFallbackOnce();

		return $this->resolveFromAppConfig(objectTypeLower: $objectTypeLower);
	}//end resolve()

	/**
	 * Resolve OpenRegister's RegisterResolverService from the DI container.
	 *
	 * The resolver is an OPTIONAL cross-app dependency: it ships with newer
	 * OpenRegister releases (per openregister/openspec/changes/register-resolver-service/).
	 * When OpenRegister predates the resolver — or the class is otherwise not
	 * bound — this returns null and callers fall back to the legacy
	 * IAppConfig::getValueString path. Resolved fresh per call; OR's container
	 * is the single source of truth for whether the service exists.
	 *
	 * @return object|null The RegisterResolverService, or null when unavailable.
	 *
	 * @psalm-suppress MixedReturnStatement OpenRegister is an optional dependency resolved dynamically.
	 * @psalm-suppress MixedInferredReturnType OpenRegister is an optional dependency resolved dynamically.
	 */
	private function getRegisterResolver(): ?object {
		if (class_exists('OCA\OpenRegister\Service\RegisterResolverService') === false) {
			return null;
		}

		try {
			// @var object $resolver The resolved OpenRegister RegisterResolverService.
			$resolver = $this->container->get('OCA\OpenRegister\Service\RegisterResolverService');
			return $resolver;
		} catch (Throwable $e) {
			// Service class exists but is not bindable in this container — treat
			// as absent and fall back. Never fail-open on a missing dependency.
			return $resolver = null;
		}
	}//end getRegisterResolver()

	/**
	 * Log the resolver-fallback deprecation warning at most once per request.
	 *
	 * @return void
	 */
	private function logResolverFallbackOnce(): void {
		if ($this->fallbackLogged === true) {
			return;
		}

		$this->fallbackLogged = true;
		$this->logger->debug(
			'Larpinq: OpenRegister RegisterResolverService is unavailable; '
			. 'resolving register/schema IDs via the legacy IAppConfig path. '
			. 'Upgrade OpenRegister to consolidate register/schema resolution.',
			['app' => $this->appName]
		);
	}//end logResolverFallbackOnce()

	/**
	 * Resolve the register and schema ids through OpenRegister's
	 * RegisterResolverService, with the legacy "not configured" messages.
	 *
	 * Real OpenRegister RegisterResolverService API (ADR-022):
	 * resolveRegisterId/resolveSchemaId read `{appId}.{configKey}` and throw
	 * MissingConfigException when both the value and default are empty.
	 * Passing the empty-string default keeps the throw-on-missing semantics
	 * consistent with the legacy path.
	 *
	 * @param object $resolver The OpenRegister RegisterResolverService.
	 * @param string $objectTypeLower The lower-cased object type slug.
	 *
	 * @return array{0: string, 1: string} A [registerId, schemaId] tuple.
	 *
	 * @throws Exception If the register or schema is not configured.
	 *
	 * @psalm-suppress MixedMethodCall    The resolver is an optional cross-app dependency.
	 */
	private function resolveThroughResolver(object $resolver, string $objectTypeLower): array {
		try {
			$register = (string)$this->lookup(resolver: $resolver, configKey: $objectTypeLower . '_register', legacy: 'resolveRegisterId');
			$schema = (string)$this->lookup(resolver: $resolver, configKey: $objectTypeLower . '_schema', legacy: 'resolveSchemaId');

			return $this->requireResolved(ids: [$register, $schema], objectTypeLower: $objectTypeLower);
		} catch (Exception $e) {
			// Re-throw our own "not configured" signal unchanged so callers
			// and tests see identical error semantics across both paths.
			if (str_contains($e->getMessage(), 'not configured for') === true) {
				throw $e;
			}

			// The resolver raised its own typed exception (e.g.
			// MissingConfigException) — normalise to the legacy message so
			// downstream callers keep one contract.
			throw new Exception("Register or schema not configured for $objectTypeLower", 0, $e);
		}//end try
	}//end resolveThroughResolver()

	/**
	 * Read one configured id through the resolver: `lookupId(ConfigLookup)` where
	 * OpenRegister offers it, else the release's `resolveRegisterId()` /
	 * `resolveSchemaId()`, which take the app id and config key directly.
	 *
	 * @param object $resolver  The OpenRegister RegisterResolverService.
	 * @param string $configKey The config key to read.
	 * @param string $legacy    The release's method for this key.
	 *
	 * @return mixed The configured id.
	 *
	 * @psalm-suppress MixedMethodCall The resolver is an optional cross-app dependency.
	 */
	private function lookup(object $resolver, string $configKey, string $legacy): mixed {
		if (method_exists($resolver, 'lookupId') === true && class_exists(self::CONFIG_LOOKUP) === true) {
			$lookup = self::CONFIG_LOOKUP;

			return $resolver->lookupId(lookup: new $lookup(appId: $this->appName, configKey: $configKey));
		}

		return $resolver->{$legacy}($this->appName, $configKey);
	}//end lookup()

	/**
	 * Refuse a resolved pair with an empty register or schema id.
	 *
	 * @param array{0: string, 1: string} $ids The [registerId, schemaId] the resolver answered.
	 * @param string $objectTypeLower The lower-cased object type slug.
	 *
	 * @return array{0: string, 1: string} The same pair.
	 *
	 * @throws Exception If the register or schema is empty.
	 */
	private function requireResolved(array $ids, string $objectTypeLower): array {
		if ($ids[0] === '') {
			throw new Exception("Register not configured for $objectTypeLower");
		}

		if ($ids[1] === '') {
			throw new Exception("Schema not configured for $objectTypeLower");
		}

		return $ids;
	}//end requireResolved()

	/**
	 * Resolve the register and schema ids from the app config directly.
	 *
	 * @param string $objectTypeLower The lower-cased object type slug.
	 *
	 * @return array{0: string, 1: string} A [registerId, schemaId] tuple.
	 *
	 * @throws Exception If the register or schema is not configured.
	 */
	private function resolveFromAppConfig(string $objectTypeLower): array {
		$register = $this->config->getValueString($this->appName, $objectTypeLower . '_register', '');
		if (empty($register) === true) {
			throw new Exception("Register not configured for $objectTypeLower");
		}

		$schema = $this->config->getValueString($this->appName, $objectTypeLower . '_schema', '');
		if (empty($schema) === true) {
			throw new Exception("Schema not configured for $objectTypeLower");
		}

		return [$register, $schema];
	}//end resolveFromAppConfig()
}//end class
