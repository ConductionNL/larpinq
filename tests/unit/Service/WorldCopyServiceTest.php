<?php

/**
 * Unit tests for WorldCopyService, on the real RegisterObjectFetcher and
 * IdListNormaliser over an in-memory OpenRegister.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/InMemoryObjectService.php';

use LengthException;
use OCA\Larpinq\Service\ConfigFileLoaderService;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\WorldCopyFailedException;
use OCA\Larpinq\Service\WorldCopyService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * REQ-WCR-001 and REQ-WCR-002 of worlds-copy-ruleset.
 */
class WorldCopyServiceTest extends TestCase {

	private const WORLD = '11111111-1111-4111-8111-111111111111';
	private const OTHER = '22222222-2222-4222-8222-222222222222';

	/**
	 * The in-memory OpenRegister.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $store;

	/**
	 * Set up Aldmoor: 2 abilities, 3 effects (one shared), a skill cycle,
	 * a unique item with a holder, a condition, a character, an event and
	 * a lore page tree.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->store = new InMemoryObjectService();
		$w = self::WORLD;
		$this->store->seed('setting', ['id' => $w, 'name' => 'Aldmoor', 'description' => 'The old kingdom', 'status' => 'active']);
		$this->store->seed('setting', ['id' => self::OTHER, 'name' => 'Elsewhere', 'status' => 'active']);
		$this->store->seed('ability', ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'name' => 'Strength', 'base' => 10, 'setting' => $w]);
		$this->store->seed(
			'ability',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'name' => 'Mana', 'base' => 0, 'setting' => $w, 'description' => null]
		);
		$this->store->seed('ability', ['id' => 'aaaaaaaa-0000-4000-8000-000000000003', 'name' => 'Luck', 'base' => 1]);
		$this->store->seed(
			'effect',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000004', 'name' => '+3 strength', 'modifier' => 3, 'modification' => 'positive', 'cumulative' => 'non-cumulative', 'abilities' => ['aaaaaaaa-0000-4000-8000-000000000001'], 'setting' => $w]
		);
		$this->store->seed(
			'effect',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000005', 'name' => '+5 mana', 'modifier' => 5, 'abilities' => [['id' => 'aaaaaaaa-0000-4000-8000-000000000002']], 'setting' => $w]
		);
		$this->store->seed(
			'effect',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000006', 'name' => '+1 luck', 'modifier' => 1, 'abilities' => ['aaaaaaaa-0000-4000-8000-000000000003']]
		);
		$this->store->seed(
			'skill',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000007', 'name' => 'Swordsmanship', 'effects' => ['aaaaaaaa-0000-4000-8000-000000000004'], 'requiredSkills' => ['aaaaaaaa-0000-4000-8000-000000000008'], 'setting' => $w]
		);
		$this->store->seed(
			'skill',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000008', 'name' => 'Master swordsman', 'effects' => ['aaaaaaaa-0000-4000-8000-000000000004', 'aaaaaaaa-0000-4000-8000-000000000006'], 'requiredSkills' => ['aaaaaaaa-0000-4000-8000-000000000007'], 'requiredStats' => ['aaaaaaaa-0000-4000-8000-000000000001'], 'requiredScore' => 12, 'setting' => $w]
		);
		$this->store->seed(
			'item',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000010', 'name' => 'Iron shield', 'unique' => true, 'effects' => ['aaaaaaaa-0000-4000-8000-000000000004'], 'characters' => ['aaaaaaaa-0000-4000-8000-000000000012'], 'setting' => $w]
		);
		$this->store->seed(
			'condition',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000011', 'name' => 'Cursed', 'unique' => false, 'effects' => ['aaaaaaaa-0000-4000-8000-000000000005'], 'characters' => ['aaaaaaaa-0000-4000-8000-000000000012'], 'setting' => $w]
		);
		$this->store->seed('skill', ['id' => 'aaaaaaaa-0000-4000-8000-000000000009', 'name' => 'Other world skill', 'setting' => self::OTHER]);
		$this->store->seed('character', ['id' => 'aaaaaaaa-0000-4000-8000-000000000012', 'name' => 'Anna', 'setting' => $w]);
		$this->store->seed('event', ['id' => 'aaaaaaaa-0000-4000-8000-000000000013', 'name' => 'Season opener', 'setting' => $w]);
		$this->store->seed(
			'lorepage',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000014', 'title' => 'The harbour', 'body' => 'Ships', 'visibility' => 'players', 'revealFrom' => '2026-10-01T00:00:00+00:00', 'category' => null, 'parent' => 'aaaaaaaa-0000-4000-8000-000000000015', 'order' => 1, 'setting' => $w]
		);
		$this->store->seed(
			'lorepage',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000015', 'title' => 'Aldmoor city', 'visibility' => 'gamemasters', 'revealFrom' => '2026-09-01T00:00:00+00:00', 'order' => 0, 'setting' => $w]
		);
		$this->store->seed(
			'characterfield',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000016', 'label' => 'Bloodline', 'key' => 'bloodline', 'fieldType' => 'choice', 'choices' => ['human', 'elven'], 'visibility' => 'owner', 'order' => 1, 'setting' => $w]
		);
		$this->store->seed(
			'characterfield',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000017', 'label' => 'True allegiance', 'key' => 'true-allegiance', 'fieldType' => 'text', 'visibility' => 'gamemasters', 'order' => 2, 'setting' => $w]
		);
		$this->store->seed(
			'characterfield',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000018', 'label' => 'Patron god', 'key' => 'patron-god', 'fieldType' => 'text', 'visibility' => 'owner']
		);

	}//end setUp()

	/**
	 * The service on the real fetcher, with the given types configured.
	 *
	 * @param list<string> $unconfigured Types with no schema configured.
	 *
	 * @return WorldCopyService The service.
	 */
	private function service(array $unconfigured = []): WorldCopyService {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($this->store);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use ($unconfigured): string {
				[$type, $kind] = explode('_', $key, 2) + [1 => ''];
				if (in_array($type, $unconfigured, true) === true) {
					return '';
				}

				return match ($kind) {
					'register' => 'larpinq',
					'schema' => $type,
					default => $default,
				};
			}
		);
		$fetcher = new RegisterObjectFetcher(
			container: $container,
			appManager: $apps,
			config: $config,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);

		return new WorldCopyService(objectFetcher: $fetcher, idList: new IdListNormaliser());
	}//end service()

	/**
	 * The copy of a named object in a type.
	 *
	 * @param string $type The type.
	 * @param string $world The world id.
	 * @param string $field The name field.
	 * @param string $name The name.
	 *
	 * @return array<string, mixed> The copy.
	 */
	private function copyOf(string $type, string $world, string $field, string $name): array {
		foreach ($this->store->objects[$type] ?? [] as $row) {
			if (($row['setting'] ?? '') === $world && ($row[$field] ?? '') === $name) {
				return $row;
			}
		}

		$this->fail(message: "no copy of {$type} {$name} in world {$world}");
	}//end copyOf()

	/**
	 * A new season on the same rules: every rule object is copied into the
	 * new world, characters and events are not, and the world ends active.
	 *
	 * @return void
	 */
	public function testANewSeasonOnTheSameRules(): void {
		$result = $this->service()->copy(worldId: self::WORLD, name: 'Aldmoor season 2');

		$new = (string)$result['world']['id'];
		$this->assertNotSame(expected: self::WORLD, actual: $new);
		$this->assertSame(expected: 'Aldmoor season 2', actual: $this->store->objects['setting'][$new]['name']);
		$this->assertSame(expected: 'The old kingdom', actual: $this->store->objects['setting'][$new]['description']);
		$this->assertSame(expected: 'active', actual: $this->store->objects['setting'][$new]['status']);
		$this->assertSame(
			expected: ['abilities' => 2, 'effects' => 2, 'skills' => 2, 'items' => 1, 'conditions' => 1, 'lorePages' => 2, 'characterFields' => 2],
			actual: $result['counts']
		);

		$inNew = static fn (array $rows): int => count(array_filter($rows, static fn (array $row): bool => ($row['setting'] ?? '') === $new));
		$this->assertSame(expected: 0, actual: $inNew($this->store->objects['character']));
		$this->assertSame(expected: 0, actual: $inNew($this->store->objects['event']));
		// The shared ability and effect, and the other world's skill, are not copied.
		$this->assertCount(expectedCount: 5, haystack: $this->store->objects['ability']);
		$this->assertCount(expectedCount: 5, haystack: $this->store->objects['effect']);
		$this->assertCount(expectedCount: 5, haystack: $this->store->objects['skill']);

		// The world is created archived first, so a half copy is never active.
		$this->assertSame(expected: 'archived', actual: $this->store->saves[0][1]['status']);

	}//end testANewSeasonOnTheSameRules()

	/**
	 * A prerequisite follows the copy, a shared effect stays shared, and
	 * holders start empty.
	 *
	 * @return void
	 */
	public function testCopiedRulesPointAtEachOther(): void {
		$result = $this->service()->copy(worldId: self::WORLD, name: 'Aldmoor season 2');
		$new = (string)$result['world']['id'];

		$str = $this->copyOf(type: 'ability', world: $new, field: 'name', name: 'Strength');
		$mana = $this->copyOf(type: 'ability', world: $new, field: 'name', name: 'Mana');
		$effStr = $this->copyOf(type: 'effect', world: $new, field: 'name', name: '+3 strength');
		$effMana = $this->copyOf(type: 'effect', world: $new, field: 'name', name: '+5 mana');
		$sword = $this->copyOf(type: 'skill', world: $new, field: 'name', name: 'Swordsmanship');
		$master = $this->copyOf(type: 'skill', world: $new, field: 'name', name: 'Master swordsman');
		$shield = $this->copyOf(type: 'item', world: $new, field: 'name', name: 'Iron shield');
		$cursed = $this->copyOf(type: 'condition', world: $new, field: 'name', name: 'Cursed');

		$this->assertSame(expected: [$str['id']], actual: $effStr['abilities']);
		$this->assertSame(expected: [$mana['id']], actual: $effMana['abilities']);
		$this->assertSame(expected: [$sword['id']], actual: $master['requiredSkills'], message: 'the prerequisite points at the copy');
		$this->assertSame(expected: [$master['id']], actual: $sword['requiredSkills'], message: 'a cycle in the old data copies too');
		$this->assertSame(
			expected: [$effStr['id'], 'aaaaaaaa-0000-4000-8000-000000000006'],
			actual: $master['effects'],
			message: 'a shared effect stays as it is'
		);
		$this->assertSame(expected: [$str['id']], actual: $master['requiredStats']);
		$this->assertSame(expected: 12, actual: $master['requiredScore']);
		$this->assertSame(expected: [$effStr['id']], actual: $shield['effects']);
		$this->assertSame(expected: [], actual: $shield['characters']);
		$this->assertTrue(condition: $shield['unique']);
		$this->assertSame(expected: [$effMana['id']], actual: $cursed['effects']);
		$this->assertSame(expected: [], actual: $cursed['characters']);

		$root = $this->copyOf(type: 'lorepage', world: $new, field: 'title', name: 'Aldmoor city');
		$child = $this->copyOf(type: 'lorepage', world: $new, field: 'title', name: 'The harbour');
		$this->assertSame(expected: $root['id'], actual: $child['parent'], message: 'a lore page keeps its place under the copied parent');

		$bloodline = $this->copyOf(type: 'characterfield', world: $new, field: 'label', name: 'Bloodline');
		$this->assertSame(expected: 'bloodline', actual: $bloodline['key'], message: 'a copied field keeps its key, so values keep their meaning');
		$this->assertSame(expected: ['human', 'elven'], actual: $bloodline['choices']);
		$this->assertSame(
			expected: 'gamemasters',
			actual: $this->copyOf(type: 'characterfield', world: $new, field: 'label', name: 'True allegiance')['visibility']
		);
		$this->assertCount(expectedCount: 5, haystack: $this->store->objects['characterfield'], message: 'a field for every world is shared, not copied');

		// The originals are untouched.
		$this->assertSame(
			expected: ['aaaaaaaa-0000-4000-8000-000000000007'],
			actual: $this->store->objects['skill']['aaaaaaaa-0000-4000-8000-000000000008']['requiredSkills']
		);
		$this->assertSame(
			expected: ['aaaaaaaa-0000-4000-8000-000000000012'],
			actual: $this->store->objects['item']['aaaaaaaa-0000-4000-8000-000000000010']['characters']
		);

	}//end testCopiedRulesPointAtEachOther()

	/**
	 * Every payload the copy writes validates against the real register
	 * schema, with Opis: no ids, no metadata, no nulls.
	 *
	 * @return void
	 */
	public function testEveryPayloadValidatesAgainstTheRealSchema(): void {
		$this->service()->copy(worldId: self::WORLD, name: 'Aldmoor season 2');

		$schemas = $this->mergedSchemas();
		$bySchema = ['setting' => 'setting', 'ability' => 'ability', 'effect' => 'effect', 'skill' => 'skill', 'item' => 'item', 'condition' => 'condition', 'lorepage' => 'lorePage', 'characterfield' => 'characterField'];
		$validator = new Validator();
		$this->assertNotEmpty(actual: $this->store->saves);
		foreach ($this->store->saves as [$schema, $payload]) {
			$this->assertArrayHasKey(key: $schema, array: $bySchema, message: "unexpected write to {$schema}");
			$this->assertArrayNotHasKey(key: 'id', array: $payload);
			$this->assertArrayNotHasKey(key: '@self', array: $payload);
			$json = json_decode((string)json_encode($this->forOpis(schema: $schemas[$bySchema[$schema]])));
			$result = $validator->validate(json_decode((string)json_encode($payload)), $json);
			$this->assertTrue(condition: $result->isValid(), message: "{$schema} payload must validate: " . json_encode($payload));
			foreach (array_keys($payload) as $key) {
				$this->assertArrayHasKey(key: $key, array: $schemas[$bySchema[$schema]]['properties'], message: "{$schema}.{$key} is not a schema property");
			}
		}

	}//end testEveryPayloadValidatesAgainstTheRealSchema()

	/**
	 * Lore pages are skipped, not fatal, before the register re-import
	 * configures their schema.
	 *
	 * @return void
	 */
	public function testAnUnconfiguredLoreSchemaCopiesTheRules(): void {
		$result = $this->service(unconfigured: ['lorepage'])->copy(worldId: self::WORLD, name: 'Aldmoor season 2');

		$this->assertSame(expected: 0, actual: $result['counts']['lorePages']);
		$this->assertSame(expected: 2, actual: $result['counts']['skills']);

	}//end testAnUnconfiguredLoreSchemaCopiesTheRules()

	/**
	 * Character fields are skipped, not fatal, before the register re-import
	 * configures their schema.
	 *
	 * @return void
	 */
	public function testAnUnconfiguredFieldSchemaCopiesTheRules(): void {
		$result = $this->service(unconfigured: ['characterfield'])->copy(worldId: self::WORLD, name: 'Aldmoor season 2');

		$this->assertSame(expected: 0, actual: $result['counts']['characterFields']);
		$this->assertSame(expected: 2, actual: $result['counts']['lorePages']);

	}//end testAnUnconfiguredFieldSchemaCopiesTheRules()

	/**
	 * The register import writes a lore page schema key, so the copy finds
	 * lore pages in production and the settings page keeps the key.
	 *
	 * @return void
	 */
	public function testTheImportConfiguresTheLoreSchema(): void {
		$slugs = (new ReflectionClass(\OCA\Larpinq\Service\SettingsLoadService::class))->getConstant('OBJECT_TYPE_SCHEMA_SLUGS');
		$this->assertSame(expected: 'larping_lore_page', actual: $slugs['lorepage'] ?? null);
		$keys = (new ReflectionClass(\OCA\Larpinq\Service\SettingsService::class))->getConstant('CONFIG_KEYS');
		$this->assertContains(needle: 'lorepage_schema', haystack: $keys);
		$this->assertContains(needle: 'lorepage_register', haystack: $keys);
		$this->assertSame(expected: 'larping_lore_page', actual: $this->mergedSchemas()['lorePage']['slug']);

	}//end testTheImportConfiguresTheLoreSchema()

	/**
	 * The preview counts what a copy would create.
	 *
	 * @return void
	 */
	public function testThePreviewCountsWithoutWriting(): void {
		$counts = $this->service()->preview(worldId: self::WORLD);

		$this->assertSame(expected: 'Aldmoor', actual: $counts['world']['name']);
		$this->assertSame(
			expected: ['abilities' => 2, 'effects' => 2, 'skills' => 2, 'items' => 1, 'conditions' => 1, 'lorePages' => 2, 'characterFields' => 2],
			actual: $counts['counts']
		);
		$this->assertSame(expected: [], actual: $this->store->saves);

	}//end testThePreviewCountsWithoutWriting()

	/**
	 * Above the cap nothing is written.
	 *
	 * @return void
	 */
	public function testAWorldAboveTheCapIsRefusedBeforeAnyWrite(): void {
		for ($i = 0; $i < WorldCopyService::MAX_OBJECTS; $i++) {
			$this->store->seed('effect', ['id' => "bulk-{$i}", 'name' => "Effect {$i}", 'setting' => self::WORLD]);
		}

		try {
			$this->service()->copy(worldId: self::WORLD, name: 'Too big');
			$this->fail(message: 'a copy above the cap must be refused');
		} catch (LengthException $e) {
			$this->assertStringContainsString(needle: (string)WorldCopyService::MAX_OBJECTS, haystack: $e->getMessage());
		}

		$this->assertSame(expected: [], actual: $this->store->saves);

	}//end testAWorldAboveTheCapIsRefusedBeforeAnyWrite()

	/**
	 * A copy that fails halfway removes what it made and names what it could
	 * not remove; no active half copy remains.
	 *
	 * @return void
	 */
	public function testAFailedCopyLeavesNothingHalfMade(): void {
		// Save 1 is the world, 2-3 abilities, 4-5 effects, 6 the first skill.
		$this->store->failOnSave = 6;
		$settingsBefore = count($this->store->objects['setting']);

		try {
			$this->service()->copy(worldId: self::WORLD, name: 'Broken');
			$this->fail(message: 'the copy must fail');
		} catch (WorldCopyFailedException $e) {
			$this->assertSame(expected: [], actual: $e->getLeftovers());
			$this->assertStringContainsString(needle: 'Swordsmanship', haystack: $e->getMessage());
		}

		$this->assertCount(expectedCount: $settingsBefore, haystack: $this->store->objects['setting']);
		$this->assertCount(expectedCount: 3, haystack: $this->store->objects['ability']);
		$this->assertCount(expectedCount: 3, haystack: $this->store->objects['effect']);
		$this->assertCount(expectedCount: 5, haystack: $this->store->deletes);

	}//end testAFailedCopyLeavesNothingHalfMade()

	/**
	 * A failure on the last write, the one that makes the new world active,
	 * names the world itself rather than the last rule object copied.
	 *
	 * @return void
	 */
	public function testAFailedActivationNamesTheWorld(): void {
		$this->service()->copy(worldId: self::WORLD, name: 'Counted');
		$saves = count($this->store->saves);
		$this->setUp();
		$this->store->failOnSave = $saves;

		try {
			$this->service()->copy(worldId: self::WORLD, name: 'Broken');
			$this->fail(message: 'the copy must fail');
		} catch (WorldCopyFailedException $e) {
			$this->assertStringStartsWith(prefix: 'Copying the world failed', string: $e->getMessage());
		}

	}//end testAFailedActivationNamesTheWorld()

	/**
	 * What a rollback cannot remove is reported, and the world stays archived.
	 *
	 * @return void
	 */
	public function testARollbackThatCannotDeleteReportsTheLeftovers(): void {
		$this->store->failOnSave = 6;
		$this->store->undeletable = ['00000000-0000-4000-8000-000000000001'];

		try {
			$this->service()->copy(worldId: self::WORLD, name: 'Broken');
			$this->fail(message: 'the copy must fail');
		} catch (WorldCopyFailedException $e) {
			$this->assertSame(expected: ['00000000-0000-4000-8000-000000000001'], actual: $e->getLeftovers());
		}

		$this->assertSame(expected: 'archived', actual: $this->store->objects['setting']['00000000-0000-4000-8000-000000000001']['status']);

	}//end testARollbackThatCannotDeleteReportsTheLeftovers()

	/**
	 * The schemas as OpenRegister imports them: the monolith plus every fragment.
	 *
	 * @return array<string, array<string, mixed>> The schemas by key.
	 */
	private function mergedSchemas(): array {
		$appPath = dirname(__DIR__, 3);
		$monolith = json_decode((string)file_get_contents($appPath . '/lib/Settings/larpinq_register.json'), true);
		$reflection = new ReflectionClass(ConfigFileLoaderService::class);
		$loader = $reflection->newInstanceWithoutConstructor();
		$merge = $reflection->getMethod('mergeRegisterFragments');
		$merge->setAccessible(true);
		return $merge->invoke($loader, $monolith, $appPath)['components']['schemas'];
	}//end mergedSchemas()

	/**
	 * The schema as OpenRegister hands it to Opis.
	 *
	 * @param array<string, mixed> $schema The register schema.
	 *
	 * @return array<string, mixed> The JSON Schema.
	 */
	private function forOpis(array $schema): array {
		foreach (array_keys($schema['properties']) as $name) {
			unset($schema['properties'][$name]['$ref'], $schema['properties'][$name]['authorization'], $schema['properties'][$name]['calculation']);
		}

		unset($schema['authorization'], $schema['configuration']);
		return $schema;
	}//end forOpis()
}//end class
