<?php

namespace OCA\LarpingApp\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OCA\LarpingApp\Service\ObjectService;
use OCA\LarpingApp\Service\SearchService;
use OCA\LarpingApp\Service\CharacterService;
use OCA\LarpingApp\Db\Character;
use OCA\LarpingApp\Db\CharacterMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class CharactersController extends Controller
{
    const TEST_ARRAY = [
        [
            "id" => "5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f",
            "name" => "Evil Kenevil",
            "ocName" => "John Doe",
            "description" => "A daredevil stuntman known for his death-defying motorcycle jumps.",
            "background" => "Evil Kenevil grew up in a circus family, developing a passion for thrills and danger from a young age.",
            "itemsAndMoney" => "Customized motorcycle, leather jumpsuit, helmet, 50 gold pieces",
            "notice" => "",
            "faith" => "Believes in the god of risk and adventure",
            "slNotesPublic" => "Known for attempting increasingly dangerous stunts",
            "slNotesPrivate" => "Has a secret fear of heights, compensates with bravado",
            "card" => "| Evil Kenevil | Daredevil Stuntman |\n|---------------|--------------------|\n| Strength: 14  | Dexterity: 18      |\n| Charisma: 16  | Intelligence: 12   |",
            "stats" => [],
            "gold" => 50,
            "silver" => 0,
            "copper" => 0,
            "events" => [],
            "skills" => [],
            "conditions" => [],
            "type" => "player",
            "approved" => "approved"
        ],
        [
            "id" => "4c3edd34-a90d-4d2a-8894-adb5836ecde8",
            "name" => "Jack the Dipper",
            "ocName" => "Jane Smith",
            "description" => "A skilled pickpocket with a heart of gold, known for his quick hands and quicker wit.",
            "background" => "Orphaned at a young age, Jack learned to survive on the streets by mastering the art of theft.",
            "itemsAndMoney" => "Set of lockpicks, dark cloak, 30 silver pieces",
            "notice" => "",
            "faith" => "Follows the trickster god",
            "slNotesPublic" => "Often seen helping the poor despite his criminal activities",
            "slNotesPrivate" => "Secretly wants to retire and open an orphanage",
            "card" => "| Jack the Dipper | Master Thief |\n|-----------------|---------------|\n| Agility: 18     | Stealth: 16    |\n| Charisma: 15    | Luck: 14       |",
            "stats" => [],
            "gold" => 0,
            "silver" => 30,
            "copper" => 0,
            "events" => [],
            "skills" => [],
            "conditions" => [],
            "type" => "player",
            "approved" => "approved"
        ],
        [
            "id" => "15551d6f-44e3-43f3-a9d2-59e583c91eb0",
            "name" => "Piet Piraat",
            "ocName" => "Peter Johnson",
            "description" => "A jovial pirate captain with a penchant for singing sea shanties and hunting for buried treasure.",
            "background" => "Piet was born on a ship and has spent his entire life at sea, working his way up from cabin boy to captain.",
            "itemsAndMoney" => "Cutlass, spyglass, treasure map, 100 gold pieces",
            "notice" => "",
            "faith" => "Worships the sea goddess",
            "slNotesPublic" => "Known for his fairness in dividing loot among the crew",
            "slNotesPrivate" => "Has a crippling fear of mermaids due to a childhood incident",
            "card" => "| Piet Piraat | Pirate Captain |\n|-------------|------------------|\n| Strength: 16 | Navigation: 18   |\n| Charisma: 17 | Seamanship: 19   |",
            "stats" => [],
            "gold" => 100,
            "silver" => 0,
            "copper" => 0,
            "events" => [],
            "skills" => [],
            "conditions" => [],
            "type" => "player",
            "approved" => "approved"
        ],
        [
            "id" => "0a3a0ffb-dc03-4aae-b207-0ed1502e60da",
            "name" => "Sonja de rovers dochter",
            "ocName" => "Sophie Anderson",
            "description" => "A fierce warrior woman, raised by bandits but with a strong sense of justice.",
            "background" => "Sonja was kidnapped as a child and raised by a band of robbers, but always felt drawn to heroic deeds.",
            "itemsAndMoney" => "Longbow, short sword, leather armor, 75 silver pieces",
            "notice" => "",
            "faith" => "Follows the path of the righteous warrior",
            "slNotesPublic" => "Often conflicts with authority due to her unconventional upbringing",
            "slNotesPrivate" => "Secretly searching for her birth parents",
            "card" => "| Sonja de rovers dochter | Bandit Turned Hero |\n|---------------------------|---------------------|\n| Strength: 15              | Archery: 18         |\n| Agility: 16               | Survival: 17        |",
            "stats" => [],
            "gold" => 0,
            "silver" => 75,
            "copper" => 0,
            "events" => [],
            "skills" => [],
            "conditions" => [],
            "type" => "player",
            "approved" => "no"
        ]
    ];

    /**
     * Constructor for the CharactersController
     *
     * @param string $appName The name of the app
     * @param IRequest $request The request object
     * @param IAppConfig $config The app configuration object
     */
    public function __construct(
        $appName,
        IRequest $request,
        private readonly IAppConfig $config,
		private readonly CharacterMapper $characterMapper
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
     * Retrieves a list of all characters
     * 
     * This method returns a JSON response containing an array of all characters in the system.
     * Currently, it uses a test array instead of fetching from a database.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse A JSON response containing the list of characters
     */
    public function index(ObjectService $objectService, SearchService $searchService): JSONResponse
    {
        $filters = $this->request->getParams();
        $fieldsToSearch = ['name', 'description'];

        $searchParams = $searchService->createMySQLSearchParams(filters: $filters);
        $searchConditions = $searchService->createMySQLSearchConditions(filters: $filters, fieldsToSearch:  $fieldsToSearch);
        $filters = $searchService->unsetSpecialQueryParams(filters: $filters);

        return new JSONResponse(['results' => $this->characterMapper->findAll(limit: null, offset: null, filters: $filters, searchConditions: $searchConditions, searchParams: $searchParams)]);

    }

    /**
     * Retrieves a single character by its ID
     * 
     * This method returns a JSON response containing the details of a specific character.
     * Currently, it fetches the character from a test array instead of a database.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the character to retrieve
     * @return JSONResponse A JSON response containing the character details
     */
    public function show(string $id): JSONResponse
    {
        try {
            return new JSONResponse($this->characterMapper->find(id: (int) $id));
        } catch (DoesNotExistException $exception) {
            return new JSONResponse(data: ['error' => 'Not Found'], statusCode: 404);
        }
    }

    /**
     * Creates a new character
     * 
     * This method is intended to create a new character based on POST data.
     * Currently, it returns an empty JSON response as a placeholder.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return JSONResponse An empty JSON response (placeholder)
     */
    public function create(): JSONResponse
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
        
        return new JSONResponse($this->characterMapper->createFromArray(object: $data));
    }

    /**
     * Updates an existing character
     * 
     * This method is intended to update an existing character based on its ID.
     * Currently, it returns the character from the test array without actually updating it.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the character to update
     * @return JSONResponse A JSON response containing the (unchanged) character details
     */
    public function update(int $id): JSONResponse
    {$data = $this->request->getParams();

		foreach ($data as $key => $value) {
			if (str_starts_with($key, '_')) {
				unset($data[$key]);
			}
		}
		if (isset($data['id'])) {
			unset($data['id']);
		}
        return new JSONResponse($this->characterMapper->updateFromArray(id: (int) $id, object: $data));
    }

    /**
     * Deletes a character
     * 
     * This method is intended to delete a character based on its ID.
     * Currently, it returns an empty JSON response as a placeholder.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @param string $id The ID of the character to delete
     * @return JSONResponse An empty JSON response (placeholder)
     */
    public function destroy(int $id): JSONResponse
    {
        $this->characterMapper->delete($this->characterMapper->find((int) $id));

        return new JSONResponse([]);
    }
}