<?php

/**
 * Installs this app's demo dataset on request (ADR-111).
 *
 * An app installed from the App Store opens on an empty list, and the only
 * question its first reader has is whether they can see it work. Answering it
 * requires data they cannot author, against a schema they do not know yet.
 *
 * This service imports `lib/Settings/larpinq_mock_register.json` — a `type: mock` descriptor
 * whose every object was generated from the schema that validates it —
 * through the same OpenRegister importer the app already uses for its real
 * configuration.
 *
 * 🔴 ON DEMAND ONLY, NEVER ON INSTALL. A mock register has no Repair step and
 * is not imported at boot: demo objects appearing unasked on a production
 * instance are indistinguishable from real records to everyone who did not
 * install it. The operator asks, through the setup walkthrough or `occ`.
 *
 * 🔴 AND `force: true`, DELIBERATELY. OpenRegister's importer version-gates a
 * non-forced import and SKIPS silently when the version has not moved. An
 * operator who clicks "install demo data" and is told it succeeded, on an
 * instance where nothing was written, has been lied to by a version compare.
 * The request is explicit, so the import is unconditional.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCA\Larpinq\AppInfo\Application;
use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Imports the generated demo dataset into OpenRegister on request.
 *
 * @spec exclude Demo-data install action (ADR-111 rule 4); no per-app openspec change yet.
 */
class DemoDataService {
	/**
	 * App-relative path to the generated mock descriptor.
	 *
	 * @var string
	 */
	private const DESCRIPTOR = '/lib/Settings/larpinq_mock_register.json';

	/**
	 * Configuration identity for the demo import.
	 *
	 * 🔴 ITS OWN NAMESPACE, not the app id. Sharing the app's identity would
	 * make the demo import and the real configuration import share one version
	 * gate, so installing demo data could mask a pending configuration update
	 * — or be masked by one.
	 *
	 * @var string
	 */
	private const CONFIG_APP_ID = Application::APP_ID . '.demo';

	/**
	 * Constructor.
	 *
	 * @param IAppManager        $appManager Resolves this app's path and version.
	 * @param ContainerInterface $container  Resolves OpenRegister's importer.
	 * @param LoggerInterface    $logger     Records what was imported.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether this app ships a demo dataset at all.
	 *
	 * @return boolean True when the descriptor is present on disk.
	 *
	 * @spec exclude Demo-data install action (ADR-111 rule 4); no per-app openspec change yet.
	 */
	public function isAvailable(): bool {
		return is_file($this->descriptorPath()) === true;
	}//end isAvailable()

	/**
	 * Import the demo dataset.
	 *
	 * 🔴 THROWS RATHER THAN RETURNING A QUIET FAILURE. Every caller reports the
	 * outcome to an operator who just asked for this, so "nothing happened"
	 * must not be presentable as success.
	 *
	 * @return array{objects: integer, registers: integer, schemas: integer} What was imported.
	 *
	 * @throws RuntimeException When the descriptor is missing, unreadable, or OpenRegister is absent.
	 *
	 * @spec exclude Demo-data install action (ADR-111 rule 4); no per-app openspec change yet.
	 */
	public function install(): array {
		$path = $this->descriptorPath();
		if (is_file($path) === false) {
			throw new RuntimeException('No demo dataset ships with this app (' . self::DESCRIPTOR . ' not found).');
		}

		$raw = file_get_contents($path);
		if ($raw === false) {
			throw new RuntimeException('The demo dataset could not be read: ' . $path);
		}

		$data = json_decode($raw, true);
		if (is_array($data) === false) {
			throw new RuntimeException('The demo dataset is not valid JSON: ' . $path);
		}

		// Counted from the file rather than from the importer's reply, so the
		// number reported is the number ASKED FOR. A discrepancy between this
		// and what lands is a real condition an operator should be able to see.
		$objects = 0;
		$components = ($data['components'] ?? []);
		if (is_array($components) === true && is_array(($components['objects'] ?? null)) === true) {
			$objects = count($components['objects']);
		}

		$result = $this->configurationService()->importFromApp(
			appId: self::CONFIG_APP_ID,
			data: $data,
			version: $this->appManager->getAppVersion(Application::APP_ID),
			force: true
		);

		$imported = [
			'objects'   => $objects,
			'registers' => count((array)($result['registers'] ?? [])),
			'schemas'   => count((array)($result['schemas'] ?? [])),
		];

		$this->logger->info(
			'[DemoDataService] imported demo data: '
			. $imported['objects'] . ' object(s), '
			. $imported['registers'] . ' register(s), '
			. $imported['schemas'] . ' schema(s).',
			['app' => Application::APP_ID]
		);

		return $imported;
	}//end install()

	/**
	 * Absolute path to the shipped descriptor.
	 *
	 * @return string The path.
	 */
	private function descriptorPath(): string {
		return $this->appManager->getAppPath(Application::APP_ID) . self::DESCRIPTOR;
	}//end descriptorPath()

	/**
	 * OpenRegister's configuration importer.
	 *
	 * 🔴 A CROSS-APP CLASS IS A RUNTIME LOOKUP. OpenRegister may not be
	 * installed, and asking the container for a class from a missing app
	 * raises something the caller cannot act on. Check first and say which app
	 * is missing.
	 *
	 * 🔴 THE RETURN TYPE IS `object`, NOT THE CLASS, AND THAT IS THE POINT.
	 * Naming a class from an OPTIONAL app in a native return type makes PHP
	 * resolve it whenever this method returns — so on an instance without
	 * OpenRegister the failure is a TypeError about a class nobody mentioned,
	 * instead of the RuntimeException above that names the missing app. It
	 * also makes the method impossible to exercise in a unit test, which is
	 * how this was found. The docblock keeps psalm and phpstan informed.
	 *
	 * @return object The importer — an OCA\OpenRegister\Service\ConfigurationService.
	 *
	 * @psalm-return \OCA\OpenRegister\Service\ConfigurationService
	 *
	 * @throws RuntimeException When OpenRegister is not installed.
	 */
	private function configurationService(): object {
		if (in_array('openregister', $this->appManager->getInstalledApps(), true) === false) {
			throw new RuntimeException('Demo data needs OpenRegister, which is not installed.');
		}

		return $this->container->get('OCA\OpenRegister\Service\ConfigurationService');
	}//end configurationService()
}//end class
