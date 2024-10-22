<?php

namespace OCA\LarpingApp\Controller;

use OCP\IAppConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCA\OpenCatalogi\Service\ObjectService;

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

		// Define the default values for the object types
		$defaults = [
			'ability_source' => 'internal',
			'ability_schema' => '',
			'ability_register' => '',
			'character_source' => 'internal',
			'character_schema' => '',
			'character_register' => '',
			'condition_source' => 'internal',
			'condition_schema' => '',
			'condition_register' => '',
			'effect_source' => 'internal',
			'effect_schema' => '',
			'effect_register' => '',
			'event_source' => 'internal',
			'event_schema' => '',
			'event_register' => '',
			'item_source' => 'internal',
			'item_schema' => '',
			'item_register' => '',
			'player_source' => 'internal',
			'player_schema' => '',
			'player_register' => '',
			'setting_source' => 'internal',
			'setting_schema' => '',
			'setting_register' => '',	
			'skill_source' => 'internal',
			'skill_schema' => '',
			'skill_register' => '',
			'template_source' => 'internal',
			'template_schema' => '',
			'template_register' => '',
		];

		// Get the current values for the object types from the configuration
		try {
			foreach ($defaults as $key => $value) {
				$data[$key] = $this->config->getValueString($this->appName, $key, $value);
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

		try {
			// Update each setting in the configuration
			foreach ($data as $key => $value) {
				$this->config->setValueString($this->appName, $key, $value);
				// Retrieve the updated value to confirm the change
				$data[$key] = $this->config->getValueString($this->appName, $key);
			}
			return new JSONResponse($data);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], 500);
		}
	}
}
