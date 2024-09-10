<?php

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Db\Ability;
use OCA\LarpingApp\Db\AbilityMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class AbilitiesController extends Controller
{
    const TEST_ARRAY = [
        [
            "id" => "56cf6db0-7c37-41a5-968b-d322c3f0da28",
            "name" => "Experience points",
            "description" => "An experience point is a unit of measurement used in some tabletop role-playing games and role-playing video games to quantify a player character's life experience and progression through the game. Experience points are generally awarded for the completion of missions, overcoming obstacles and opponents, and for successful role-playing.",
            "base" => 0
        ],
        [
            "id" => "4c3edd34-a90d-4d2a-8894-adb5836ecde8",
            "name" => "Strength",
            "description" => "Represents the physical power and muscle of a character. It affects melee attack rolls, damage rolls for melee weapons, and various strength-based skill checks.",
            "base" => 10
        ],
        [
            "id" => "15551d6f-44e3-43f3-a9d2-59e583c91eb0",
            "name" => "Mana",
            "description" => "Represents the magical energy or power that a character can use to cast spells or perform magical abilities. It is often depleted when using magic and regenerates over time or through rest.",
            "base" => 20
        ],
        [
            "id" => "0a3a0ffb-dc03-4aae-b207-0ed1502e60da",
            "name" => "Hit Points",
            "description" => "Represents the amount of damage a character can sustain before falling unconscious or dying. It's often calculated based on other stats like Constitution.",
            "base" => 15
        ]
    ];

    public function __construct(
		$appName,
		IRequest $request,
		private readonly IAppConfig $config,
		private readonly AbilityMapper $abilityMapper
	)
    {
        parent::__construct($appName, $request);
    }

	/**
	 * Returns the template of the main app's page
	 * 
	 * This method renders the main page of the application, adding any necessary data to the template.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse The rendered template response
	 */
	public function page(): TemplateResponse
	{			
        return new TemplateResponse(
            //Application::APP_ID,
            'larpingapp',
            'index',
            []
        );
	}
	

    /**
     * Retrieves a list of all abilities
     * 
     * This method returns a JSON response containing an array of all abilities in the system.
     * It uses filters and search parameters to refine the results.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of abilities
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch: $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->abilityMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);
    }

    /**
     * Retrieves a single ability by its ID
     * 
     * This method returns a JSON response containing the details of a specific ability.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the ability to retrieve
     * @return JSONResponse A JSON response containing the ability details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->abilityMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new ability
     * 
     * This method creates a new ability based on POST data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the created ability
     */
    public function create(): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        
        return new JSONResponse($this->abilityMapper->createFromArray(object: $data));
    }

    /**
     * Updates an existing ability
     * 
     * This method updates an existing ability based on its ID and the provided data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the ability to update
     * @return JSONResponse A JSON response containing the updated ability details
     */
    public function update(int $id): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        if (isset($data['id'])) {
            unset($data['id']);
        }
        return new JSONResponse($this->abilityMapper->updateFromArray(id: (int) $id, object: $data));
    }

    /**
     * Deletes an ability
     * 
     * This method deletes an ability based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the ability to delete
     * @return JSONResponse An empty JSON response
     */
    public function destroy(int $id): JSONResponse
    {
        $this->abilityMapper->delete($this->abilityMapper->find((int) $id));

        return new JSONResponse([]);
    }
}