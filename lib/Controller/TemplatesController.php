<?php

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Db\Template;
use OCA\LarpingApp\Db\TemplateMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class TemplatesController extends Controller
{
    const TEST_ARRAY = [
        [
            "id" => "5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f",
            "name" => "Character Sheet",
            "description" => "A template for creating character sheets",
            "template" => "<h1>{{character.name}}</h1><p>Class: {{character.class}}</p><p>Level: {{character.level}}</p>"
        ],
        [
            "id" => "4c3edd34-a90d-4d2a-8894-adb5836ecde8",
            "name" => "Event Flyer",
            "description" => "A template for creating event flyers",
            "template" => "<h1>{{event.name}}</h1><p>Date: {{event.date}}</p><p>Location: {{event.location}}</p>"
        ],
        [
            "id" => "15551d6f-44e3-43f3-a9d2-59e583c91eb0",
            "name" => "Storybook",
            "description" => "This event's storybook",
            "template" => "<h1>{{story.title}}</h1><p>{{story.content}}</p>"
        ],
        [
            "id" => "0a3a0ffb-dc03-4aae-b207-0ed1502e60da",
            "name" => "Item Card",
            "description" => "A template for creating item cards",
            "template" => "<h2>{{item.name}}</h2><p>{{item.description}}</p><p>Effect: {{item.effect}}</p>"
        ]
    ];

    public function __construct(
		$appName,
		IRequest $request,
		private readonly IAppConfig $config,
		private readonly TemplateMapper $templateMapper
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
     * Retrieves a list of all templates
     * 
     * This method returns a JSON response containing an array of all templates in the system.
     * It uses filters and search parameters to refine the results.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of templates
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch: $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->templateMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);
    }

    /**
     * Retrieves a single template by its ID
     * 
     * This method returns a JSON response containing the details of a specific template.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the template to retrieve
     * @return JSONResponse A JSON response containing the template details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->templateMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new template
     * 
     * This method creates a new template based on POST data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the created template
     */
    public function create(): JSONResponse
    {
        $data = $this->request->getParams();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '_')) {
                unset($data[$key]);
            }
        }
        
        return new JSONResponse($this->templateMapper->createFromArray(object: $data));
    }

    /**
     * Updates an existing template
     * 
     * This method updates an existing template based on its ID and the provided data.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the template to update
     * @return JSONResponse A JSON response containing the updated template details
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
        return new JSONResponse($this->templateMapper->updateFromArray(id: (int) $id, object: $data));
    }

    /**
     * Deletes a template
     * 
     * This method deletes a template based on its ID.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param int $id The ID of the template to delete
     * @return JSONResponse An empty JSON response
     */
    public function destroy(int $id): JSONResponse
    {
        $this->templateMapper->delete($this->templateMapper->find((int) $id));

        return new JSONResponse([]);
    }
}
