<?php

namespace OCA\LarpingApp\Controller;

use OCP\IAppConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCA\LarpingApp\Service\ObjectService;

/**
 * Class SettingsController
 *
 * Controller for handling settings-related operations in the OpenCatalogi app.
 */
class SettingsController extends Controller
{

	/**
	 * SettingsController constructor.
	 *
	 * @param string $appName The name of the app
	 * @param IAppConfig $config The app configuration
	 * @param IRequest $request The request object
	 * @param ObjectService $objectService The object service
	 */
	public function __construct(
		$appName,
		IRequest $request,
		private readonly IAppConfig $config,
		private readonly ObjectService $objectService
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Retrieve the current settings.
	 *
	 * @return JSONResponse JSON response containing the current settings
	 *
	 * @NoCSRFRequired
	 */
	public function index(): JSONResponse
	{
		// Initialize the data array
		$data = [];
		$data['objectTypes'] = ['ability', 'character', 'condition', 'effect', 'event', 'item', 'player', 'setting', 'skill', 'template'];
		$data['openRegisters'] = false;
		$data['availableRegisters'] = [];

		// Check if the OpenRegister service is available
		$openRegisters = $this->objectService->getOpenRegisters();
		if ($openRegisters !== null) {
			$data['openRegisters'] = true;
			$data['availableRegisters'] = $openRegisters->getRegisters();
		}

		// Build defaults array dynamically based on object types
		$defaults = [];
		foreach ($data['objectTypes'] as $type) {
			$defaults["{$type}_source"] = 'internal';
			$defaults["{$type}_schema"] = '';
			$defaults["{$type}_register"] = '';
		}

		// Get the current values for the object types from the configuration
		try {
			foreach ($defaults as $key => $defaultValue) {
				$data['configuration'][$key] = $this->config->getValueString($this->appName, $key, $defaultValue);
			}
			return new JSONResponse($data);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], 500);
		}
	}

	/**
	 * Handle the post request to update settings.
	 *
	 * @return JSONResponse JSON response containing the updated settings
	 *
	 * @NoCSRFRequired
	 */
	public function create(): JSONResponse
	{
		// Get all parameters from the request
		$data = $this->request->getParams();

		// Define allowed setting keys to prevent arbitrary config writes
		$allowedTypes = ['ability', 'character', 'condition', 'effect', 'event', 'item', 'player', 'setting', 'skill', 'template'];
		$allowedSuffixes = ['_source', '_schema', '_register'];

		try {
			$result = [];
			// Update each setting in the configuration (only allowed keys)
			foreach ($data as $key => $value) {
				// Skip Nextcloud framework params
				if (str_starts_with($key, '_') === true) {
					continue;
				}

				// Validate key is an allowed setting
				$isAllowed = false;
				foreach ($allowedTypes as $type) {
					foreach ($allowedSuffixes as $suffix) {
						if ($key === $type.$suffix) {
							$isAllowed = true;
							break 2;
						}
					}
				}

				if ($isAllowed === false) {
					continue;
				}

				$this->config->setValueString($this->appName, $key, $value);
				// Retrieve the updated value to confirm the change
				$result[$key] = $this->config->getValueString($this->appName, $key);
			}
			return new JSONResponse($result);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], 500);
		}
	}
}
