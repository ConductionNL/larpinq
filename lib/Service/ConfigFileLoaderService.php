<?php

/**
 * Larpinq ConfigFileLoaderService.
 *
 * Service for loading and parsing the Larpinq register configuration JSON file.
 *
 * @category  Service
 * @package   OCA\Larpinq\Service
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT: <git_id>
 * @link      https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-33
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-34
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-35
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-36
 */

declare(strict_types=1);

namespace OCA\Larpinq\Service;

use OCA\Larpinq\AppInfo\Application;
use OCP\App\IAppManager;
use RuntimeException;

/**
 * Service for loading and parsing configuration JSON files.
 *
 * @category Service
 * @package  OCA\Larpinq\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-33
 */
class ConfigFileLoaderService {

	/**
	 * Path to the register config file.
	 *
	 * @var string
	 */
	private const REGISTER_FILE = '/lib/Settings/larpinq_register.json';

	/**
	 * Directory holding modular register fragments (ADR-037).
	 *
	 * Each *.json file in this directory is deep-merged over the monolith
	 * before import, so concurrent same-app builds add disjoint fragment
	 * files instead of editing the shared monolith.
	 *
	 * @var string
	 */
	private const FRAGMENT_DIR = '/lib/Settings/register.d';

	/**
	 * Signature (hash) of the merged fragment set, computed on the last
	 * loadConfigurationFile() call. Folded into the import version by
	 * SettingsLoadService so OpenRegister re-imports when fragments change.
	 *
	 * @var string
	 */
	private string $fragmentSignature = '';

	/**
	 * Constructor.
	 *
	 * @param IAppManager $appManager The app manager.
	 *
	 * @return void
	 *
	 * @psalm-suppress PossiblyUnusedMethod Instantiated via Nextcloud dependency injection.
	 */
	public function __construct(
		private readonly IAppManager $appManager,
	) {

	}//end __construct()

	/**
	 * Load and parse the configuration JSON file.
	 *
	 * @return array<string, mixed> The parsed configuration data.
	 *
	 * @throws RuntimeException If the file cannot be read or parsed.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-33
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-34
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-35
	 */
	public function loadConfigurationFile(): array {
		$appPath = $this->appManager->getAppPath(Application::APP_ID);
		$absoluteFilePath = $appPath . self::REGISTER_FILE;

		if (file_exists($absoluteFilePath) === false) {
			throw new RuntimeException("Configuration file not found: {$absoluteFilePath}");
		}

		$jsonContent = file_get_contents($absoluteFilePath);
		if ($jsonContent === false) {
			throw new RuntimeException("Failed to read configuration file: {$absoluteFilePath}");
		}

		// @var array<string, mixed>|null $data
		$data = json_decode($jsonContent, true);
		if (json_last_error() !== JSON_ERROR_NONE || is_array($data) === false) {
			throw new RuntimeException('Invalid JSON in configuration file: ' . json_last_error_msg());
		}

		// ADR-037: deep-merge modular register fragments over the monolith so
		// concurrent same-app builds add disjoint fragment files instead of
		// editing this shared document.
		$data = $this->mergeRegisterFragments(data: $data, appPath: $appPath);

		return $data;
	}//end loadConfigurationFile()

	/**
	 * Deep-merge every lib/Settings/register.d/*.json fragment over the monolith.
	 *
	 * OpenAPI `components.schemas` / `paths` are key-keyed objects, so disjoint
	 * fragments union by key; list arrays concatenate; scalars overwrite. The
	 * merged fragment signature is recorded so SettingsLoadService can fold it
	 * into the import version (ADR-037).
	 *
	 * @param array<string, mixed> $data The parsed monolith configuration.
	 * @param string $appPath Absolute path to the app directory.
	 *
	 * @return array<string, mixed> The merged configuration data.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-33
	 */
	private function mergeRegisterFragments(array $data, string $appPath): array {
		$this->fragmentSignature = '';

		$fragmentDir = $appPath . self::FRAGMENT_DIR;
		if (is_dir($fragmentDir) === false) {
			return $data;
		}

		$fragmentFiles = glob($fragmentDir . '/*.json');
		if ($fragmentFiles === false || empty($fragmentFiles) === true) {
			return $data;
		}

		// Deterministic order so the signature is stable across runs.
		sort($fragmentFiles);

		$signatureParts = [];
		foreach ($fragmentFiles as $fragmentFile) {
			$fragmentContent = file_get_contents($fragmentFile);
			if ($fragmentContent === false) {
				continue;
			}

			// @var array<string, mixed>|null $fragment
			$fragment = json_decode($fragmentContent, true);
			if (json_last_error() !== JSON_ERROR_NONE || is_array($fragment) === false) {
				throw new RuntimeException(
					'Invalid JSON in register fragment ' . basename($fragmentFile) . ': ' . json_last_error_msg()
				);
			}

			$data = self::deepMergeConfig(base: $data, overlay: $fragment);
			$signatureParts[] = basename($fragmentFile) . ':' . md5($fragmentContent);
		}//end foreach

		if (empty($signatureParts) === false) {
			$this->fragmentSignature = substr(md5(implode('|', $signatureParts)), 0, 12);
		}

		return $data;
	}//end mergeRegisterFragments()

	/**
	 * Recursively deep-merge an overlay array onto a base array (ADR-037).
	 *
	 * Associative (string-keyed) arrays union by key, recursing on shared keys.
	 * List (sequentially-keyed) arrays concatenate. Scalars in the overlay
	 * overwrite the base. This matches OpenAPI assembly semantics: schemas and
	 * paths union by name; example/required lists append.
	 *
	 * @param array<mixed> $base The base array.
	 * @param array<mixed> $overlay The overlay array merged onto the base.
	 *
	 * @return array<mixed> The merged array.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-33
	 */
	private static function deepMergeConfig(array $base, array $overlay): array {
		$baseIsList = array_is_list($base);
		$overlayIsList = array_is_list($overlay);

		// Two list arrays concatenate.
		if ($baseIsList === true && $overlayIsList === true) {
			return array_merge($base, $overlay);
		}

		foreach ($overlay as $key => $value) {
			if (array_key_exists($key, $base) === true
				&& is_array($base[$key]) === true
				&& is_array($value) === true
			) {
				$base[$key] = self::deepMergeConfig(base: $base[$key], overlay: $value);
				continue;
			}

			$base[$key] = $value;
		}//end foreach

		return $base;
	}//end deepMergeConfig()

	/**
	 * Get the signature of the merged register fragments from the last load.
	 *
	 * Empty string when no fragments were merged. SettingsLoadService folds
	 * this into the import version (`<ver>+frag.<hash>`) so OpenRegister's
	 * version-gated import re-runs whenever fragments change (ADR-037).
	 *
	 * @return string The fragment signature, or '' when no fragments present.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-33
	 */
	public function getFragmentSignature(): string {
		return $this->fragmentSignature;
	}//end getFragmentSignature()

	/**
	 * Ensure the x-openregister sourceType is set on configuration data.
	 *
	 * @param array<string, mixed> $data The configuration data.
	 *
	 * @return array<string, mixed> The data with sourceType ensured.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-36
	 */
	public function ensureSourceType(array $data): array {
		if (isset($data['x-openregister']) === false || is_array($data['x-openregister']) === false) {
			$data['x-openregister'] = [];
		}

		// @var array<string, mixed> $openRegister
		$openRegister = $data['x-openregister'];
		if (isset($openRegister['sourceType']) === false) {
			$openRegister['sourceType'] = 'local';
			$data['x-openregister'] = $openRegister;
		}

		return $data;
	}//end ensureSourceType()
}//end class
