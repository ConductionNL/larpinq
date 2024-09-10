<?php

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Db\Event;
use OCA\LarpingApp\Db\EventMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class EventsController extends Controller
{
    const TEST_ARRAY = [
        [
            "id" => "5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f",
            "name" => "Summer Solstice Celebration",
            "description" => "A magical gathering to honor the longest day of the year",
            "characters" => [],
            "effects" => [],
            "startDate" => "2023-06-21T18:00:00Z",
            "endDate" => "2023-06-22T06:00:00Z",
            "location" => "Enchanted Forest Clearing"
        ],
        [
            "id" => "4c3edd34-a90d-4d2a-8894-adb5836ecde8",
            "name" => "Dragon's Lair Expedition",
            "description" => "A perilous journey to retrieve a legendary artifact",
            "characters" => [],
            "effects" => [],
            "startDate" => "2023-07-15T09:00:00Z",
            "endDate" => "2023-07-16T17:00:00Z",
            "location" => "Misty Mountains"
        ],
        [
            "id" => "15551d6f-44e3-43f3-a9d2-59e583c91eb0",
            "name" => "Royal Masquerade Ball",
            "description" => "An elegant evening of mystery and intrigue at the royal palace",
            "characters" => [],
            "effects" => [],
            "startDate" => "2023-08-01T20:00:00Z",
            "endDate" => "2023-08-02T02:00:00Z",
            "location" => "Royal Palace Grand Ballroom"
        ]
    ];

    public function __construct(
		$appName,
		IRequest $request,
		private readonly IAppConfig $config,
		private readonly EventMapper $eventMapper
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
     * Retrieves a list of all events
     * 
     * This method returns a JSON response containing an array of all events in the system.
     * It uses filters and search parameters to refine the results.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of events
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch: $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->eventMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);
    }

    /**
     * Retrieves a single event by its ID
     * 
     * This method returns a JSON response containing the details of a specific event.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the event to retrieve
     * @return JSONResponse A JSON response containing the event details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->eventMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new event
     * 
     * This method creates a new event based on POST data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the created event
     */
    public function create(): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        
        return new JSONResponse($this->eventMapper->createFromArray(object: $data));
    }

    /**
     * Updates an existing event
     * 
     * This method updates an existing event based on its ID and the provided data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the event to update
     * @return JSONResponse A JSON response containing the updated event details
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
        return new JSONResponse($this->eventMapper->updateFromArray(id: (int) $id, object: $data));
    }

    /**
     * Deletes an event
     * 
     * This method deletes an event based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the event to delete
     * @return JSONResponse An empty JSON response
     */
    public function destroy(int $id): JSONResponse
    {
        $this->eventMapper->delete($this->eventMapper->find((int) $id));

        return new JSONResponse([]);
    }
}