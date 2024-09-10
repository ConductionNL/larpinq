<?php

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Db\Effect;
use OCA\LarpingApp\Db\EffectMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class EffectsController extends Controller
{
    const TEST_ARRAY = [
        [
            "id" => "5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f",
            "name" => "+ 5 Healing Mana",
            "description" => "The character has additional healing mana",
            "stat" => [],
            "modifier" => 5,
            "modification" => "positive",
            "cumulative" => "non-cumulative"
        ],
        [
            "id" => "4c3edd34-a90d-4d2a-8894-adb5836ecde8",
            "name" => "- 2 Strength",
            "description" => "The character's strength is temporarily reduced",
            "stat" => [],
            "modifier" => -2,
            "modification" => "negative",
            "cumulative" => "non-cumulative"
        ],
        [
            "id" => "15551d6f-44e3-43f3-a9d2-59e583c91eb0",
            "name" => "+ 3 Agility",
            "description" => "The character gains increased agility",
            "stat" => [],
            "modifier" => 3,
            "modification" => "positive",
            "cumulative" => "cumulative"
        ],
        [
            "id" => "0a3a0ffb-dc03-4aae-b207-0ed1502e60da",
            "name" => "+ 1 Intelligence",
            "description" => "The character's intelligence is slightly enhanced",
            "stat" => [],
            "modifier" => 1,
            "modification" => "positive",
            "cumulative" => "non-cumulative"
        ]
    ];

    public function __construct(
		$appName,
		IRequest $request,
		private readonly IAppConfig $config,
		private readonly EffectMapper $effectMapper
	)
    {
        parent::__construct($appName, $request);
    }

	/**
	 * This returns the template of the main app's page
	 * It adds some data to the template (app version)
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
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
     * Retrieves a list of all effects
     * 
     * This method returns a JSON response containing an array of all effects in the system.
     * It uses filters and search parameters to refine the results.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of effects
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch: $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->effectMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);
    }

    /**
     * Retrieves a single effect by its ID
     * 
     * This method returns a JSON response containing the details of a specific effect.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the effect to retrieve
     * @return JSONResponse A JSON response containing the effect details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->effectMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new effect
     * 
     * This method creates a new effect based on POST data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the created effect
     */
    public function create(): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        
        return new JSONResponse($this->effectMapper->createFromArray(object: $data));
    }

    /**
     * Updates an existing effect
     * 
     * This method updates an existing effect based on its ID and the provided data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the effect to update
     * @return JSONResponse A JSON response containing the updated effect details
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
        return new JSONResponse($this->effectMapper->updateFromArray(id: (int) $id, object: $data));
    }

    /**
     * Deletes an effect
     * 
     * This method deletes an effect based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the effect to delete
     * @return JSONResponse An empty JSON response
     */
    public function destroy(int $id): JSONResponse
    {
        $this->effectMapper->delete($this->effectMapper->find((int) $id));

        return new JSONResponse([]);
    }
}
