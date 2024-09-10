<?php

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Db\Item;
use OCA\LarpingApp\Db\ItemMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class ItemsController extends Controller
{
    const TEST_ARRAY = [
        [
            "id" => "5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f",
            "name" => "Hand of Vecna",
            "description" => "The Hand of Vecna is a powerful artifact in many campaign settings for the Dungeons & Dragons fantasy role-playing game. Originating in the World of Greyhawk campaign setting, the Hand appears as a severed left human hand, blackened and charred, with long, claw-like fingernails.",
            "effect" => "The new bearer of the Eye or Hand (or both) will gain access to powerful spell-like abilities, but the items will slowly corrupt them, turning them evil over time",
            "effects" => [],
            "unique" => true,
            "characters" => []
        ],
        [
            "id" => "4c3edd34-a90d-4d2a-8894-adb5836ecde8",
            "name" => "Vorpal Sword",
            "description" => "A legendary sword known for its ability to sever the heads of opponents. It appears as a finely crafted longsword with an impossibly sharp edge that seems to shimmer with an otherworldly energy.",
            "effect" => "On a critical hit, this sword has a chance to instantly decapitate the target. It also grants the wielder enhanced combat prowess and reflexes.",
            "effects" => [],
            "unique" => true,
            "characters" => []
        ],
        [
            "id" => "15551d6f-44e3-43f3-a9d2-59e583c91eb0",
            "name" => "Cloak of Invisibility",
            "description" => "A shimmering cloak that seems to be made of woven moonlight. When worn, it has the power to render the wearer completely invisible to the naked eye.",
            "effect" => "Grants the wearer invisibility at will. However, prolonged use may cause a growing detachment from the visible world and potential madness.",
            "effects" => [],
            "unique" => true,
            "characters" => []
        ],
        [
            "id" => "0a3a0ffb-dc03-4aae-b207-0ed1502e60da",
            "name" => "Potion of Healing",
            "description" => "A small vial containing a swirling red liquid that glows faintly. When consumed, it rapidly heals wounds and restores vitality.",
            "effect" => "Instantly heals 2d4+2 hit points when consumed. Can be used in combat as a bonus action.",
            "effects" => [],
            "unique" => false,
            "characters" => []
        ]
    ];

    public function __construct(
		$appName,
		IRequest $request,
		private readonly IAppConfig $config,
		private readonly ItemMapper $itemMapper
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
     * Retrieves a list of all items
     * 
     * This method returns a JSON response containing an array of all items in the system.
     * It uses filters and search parameters to refine the results.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of items
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch: $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->itemMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);
    }

    /**
     * Retrieves a single item by its ID
     * 
     * This method returns a JSON response containing the details of a specific item.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the item to retrieve
     * @return JSONResponse A JSON response containing the item details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->itemMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new item
     * 
     * This method creates a new item based on POST data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the created item
     */
    public function create(): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        
        return new JSONResponse($this->itemMapper->createFromArray(object: $data));
    }

    /**
     * Updates an existing item
     * 
     * This method updates an existing item based on its ID and the provided data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the item to update
     * @return JSONResponse A JSON response containing the updated item details
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
        return new JSONResponse($this->itemMapper->updateFromArray(id: (int) $id, object: $data));
    }

    /**
     * Deletes an item
     * 
     * This method deletes an item based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the item to delete
     * @return JSONResponse An empty JSON response
     */
    public function destroy(int $id): JSONResponse
    {
        $this->itemMapper->delete($this->itemMapper->find((int) $id));

        return new JSONResponse([]);
    }
}
