<?php

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Db\Player;
use OCA\LarpingApp\Db\PlayerMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class PlayersController extends Controller
{
    const TEST_ARRAY = [
        [
            "id" => "5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f",
            "name" => "Player 1",
            "description" => "summary for one"
        ],
        [
            "id" => "4c3edd34-a90d-4d2a-8894-adb5836ecde8",
            "name" => "Player 2",
            "description" => "summary for two"
        ],
        [
            "id" => "15551d6f-44e3-43f3-a9d2-59e583c91eb0",
            "name" => "Player 3",
            "description" => "summary for three"
        ],
        [
            "id" => "0a3a0ffb-dc03-4aae-b207-0ed1502e60da",
            "name" => "Player 4",
            "description" => "summary for four"
        ]
    ];

    /**
     * Constructor for the PlayersController
     *
     * @param string $appName The name of the app
     * @param IRequest $request The request object
     * @param IAppConfig $config The app configuration object
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly IAppConfig $config,
		private readonly PlayerMapper $playerMapper
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
            'larpingapp',
            'index',
            []
        );
    }
    
    /**
     * Retrieves a list of all players
     * 
     * This method returns a JSON response containing an array of all players in the system.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of players
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch: $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->playerMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);
    }

    /**
     * Retrieves a single player by their ID
     * 
     * This method returns a JSON response containing the details of a specific player.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the player to retrieve
     * @return JSONResponse A JSON response containing the player details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->playerMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new player
     * 
     * This method creates a new player based on POST data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the created player details
     */
    public function create(): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        
        return new JSONResponse($this->playerMapper->createFromArray(object: $data));
    }

    /**
     * Updates an existing player
     * 
     * This method updates an existing player based on their ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the player to update
     * @return JSONResponse A JSON response containing the updated player details
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
        return new JSONResponse($this->playerMapper->updateFromArray(id: (int) $id, object: $data));
    }

    /**
     * Deletes a player
     * 
     * This method deletes a player based on their ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the player to delete
     * @return JSONResponse An empty JSON response
     */
    public function destroy(int $id): JSONResponse
    {
        $this->playerMapper->delete($this->playerMapper->find((int) $id));

        return new JSONResponse([]);
    }
}
