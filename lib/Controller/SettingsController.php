<?php

declare(strict_types=1);

/**
 * Settings controller implementation
 *
 * @category  Controller
 * @package   OCA\LarpingApp\Controller
 * @author    Ruben Linde <ruben@larpingapp.com>
 * @copyright 2024 Ruben Linde
 * @license   https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @version   Release: 0.1.0
 * @link      https://larpingapp.com
 *
 * @phpversion 8.2
 */

namespace OCA\LarpingApp\Controller;

use Exception;
use OCP\IAppConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCA\LarpingApp\Service\ObjectService;

/**
 * Controller for handling settings-related operations
 *
 * This controller provides endpoints for managing application settings,
 * particularly related to object types and OpenRegister configuration.
 *
 * @category Controller
 * @package  OCA\LarpingApp\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 * @link     https://larpingapp.com
 */
class SettingsController extends Controller
{
	/**
	 * SettingsController constructor
	 *
	 * @param string        $appName       The name of the app
	 * @param IRequest      $request       The request object
	 * @param IAppConfig    $config        The app configuration service
	 * @param ObjectService $objectService The object service
	 * 
	 * @return void
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
	 * Retrieve the current settings
	 *
	 * Gets all application settings including object types, OpenRegister availability,
	 * and current configuration values.
	 *
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse JSON response containing the current settings
	 * 
	 * @psalm-return JSONResponse
	 * @phpstan-return JSONResponse
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
		} catch (Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], 500);
		}
	}

	/**
	 * Update application settings
	 *
	 * Handles the post request to update multiple settings at once.
	 *
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse JSON response containing the updated settings
	 * 
	 * @psalm-return JSONResponse
	 * @phpstan-return JSONResponse
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
		} catch (Exception $e) {
			return new JSONResponse(['error' => $e->getMessage()], 500);
		}
	}
}
