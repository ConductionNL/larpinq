<?php

/**
 * Larpinq Payment Leaf
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
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCP\IAppConfig;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Shillinq's payment requests leaf (`shillinq-payment-requests`, hydra
 * ADR-066), reached through OpenRegister's integration registry so larpinq
 * never calls shillinq's own classes (hydra ADR-041). A registration is the
 * leaf's host object: the request's subject names larpinq's register and
 * registration schema as configured.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @psalm-suppress MixedMethodCall OpenRegister's registry and the leaf are optional dependencies.
 * @psalm-suppress MixedAssignment OpenRegister's registry and the leaf are optional dependencies.
 *
 * @spec openspec/specs/event-registration/spec.md
 */
class PaymentLeaf {

	/**
	 * The leaf id shillinq registers.
	 *
	 * @var string
	 */
	public const LEAF_ID = 'shillinq-payment-requests';

	/**
	 * OpenRegister's integration registry service id.
	 *
	 * @var string
	 */
	public const REGISTRY = 'OCA\OpenRegister\Service\Integration\IntegrationRegistry';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container The DI container (OpenRegister's registry).
	 * @param IAppConfig $config The registration register and schema.
	 * @param LoggerInterface $logger The logger.
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly IAppConfig $config,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether shillinq's leaf is there.
	 *
	 * @return bool True when a request can be raised and read.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function available(): bool {
		return $this->provider() !== null;
	}//end available()

	/**
	 * Raise a payment request on a registration.
	 *
	 * @param string $registrationId The registration.
	 * @param array<string, mixed> $payload The leaf payload.
	 *
	 * @return array<string, mixed> The created request (`id`, `paymentLink` when shillinq has one).
	 *
	 * @throws PaymentRequestRefusedException When shillinq is absent or refuses.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function create(string $registrationId, array $payload): array {
		$provider = $this->provider();
		if ($provider === null) {
			throw new PaymentRequestRefusedException('Shillinq is not installed. Set the payment state by hand.', 409);
		}

		try {
			return (array)$provider->create($this->register(), $this->schema(), $registrationId, $payload);
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: shillinq refused the payment request of registration {id}.', ['id' => $registrationId, 'exception' => $e]);
			throw new PaymentRequestRefusedException('Shillinq refused the payment request: ' . $e->getMessage(), 502);
		}
	}//end create()

	/**
	 * The payment requests on a registration, or none when shillinq cannot be read.
	 *
	 * @param string $registrationId The registration.
	 *
	 * @return array<int, array<string, mixed>> The requests (`id`, `state`, `paymentLink`, ...).
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function requests(string $registrationId): array {
		$provider = $this->provider();
		if ($provider === null) {
			return [];
		}

		try {
			$listed = (array)$provider->list($this->register(), $this->schema(), $registrationId);
		} catch (Throwable $e) {
			$this->logger->warning('Larpinq: the payment requests of registration {id} could not be read.', ['id' => $registrationId, 'exception' => $e]);
			return [];
		}

		return array_values(array_filter((array)($listed['items'] ?? []), 'is_array'));
	}//end requests()

	/**
	 * Whether a payment request's subject is a larpinq registration.
	 *
	 * @param mixed $subject The request's `subject`.
	 *
	 * @return bool True for a registration of this instance.
	 *
	 * @spec openspec/specs/event-registration/spec.md
	 */
	public function isRegistrationSubject(mixed $subject): bool {
		if (is_array($subject) === false || (string)($subject['id'] ?? '') === '') {
			return false;
		}

		return (string)($subject['register'] ?? '') === $this->register()
			&& (string)($subject['schema'] ?? '') === $this->schema();
	}//end isRegistrationSubject()

	/**
	 * The leaf, or null without OpenRegister's registry or shillinq.
	 *
	 * @return object|null The leaf.
	 */
	private function provider(): ?object {
		try {
			if ($this->container->has(self::REGISTRY) === false) {
				return null;
			}

			$provider = $this->container->get(self::REGISTRY)->get(self::LEAF_ID);
		} catch (Throwable $e) {
			$this->logger->debug('Larpinq: OpenRegister\'s integration registry could not be read.', ['exception' => $e]);
			return null;
		}

		if (is_object($provider) === false) {
			return null;
		}

		return $provider;
	}//end provider()

	/**
	 * The register registrations live in.
	 *
	 * @return string The register id.
	 */
	private function register(): string {
		return $this->config->getValueString('larpinq', 'registration_register', '');
	}//end register()

	/**
	 * The registration schema.
	 *
	 * @return string The schema id.
	 */
	private function schema(): string {
		return $this->config->getValueString('larpinq', 'registration_schema', '');
	}//end schema()
}//end class
