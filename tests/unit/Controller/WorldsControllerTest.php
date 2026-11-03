<?php

/**
 * Unit tests for WorldsController: the game master guard, the statuses and
 * the cap, on the real WorldCopyService and RegisterObjectFetcher over an
 * in-memory OpenRegister.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

require_once dirname(__DIR__) . '/Service/InMemoryObjectService.php';

use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Controller\CallerAccess;
use OCA\Larpinq\Controller\WorldsController;
use OCA\Larpinq\Service\IdListNormaliser;
use OCA\Larpinq\Service\RegisterObjectFetcher;
use OCA\Larpinq\Service\WorldCopyService;
use OCA\Larpinq\Tests\Unit\Service\InMemoryObjectService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * REQ-WCR-003 of worlds-copy-ruleset.
 */
class WorldsControllerTest extends TestCase {

	private const WORLD = '11111111-1111-4111-8111-111111111111';

	/**
	 * The in-memory OpenRegister.
	 *
	 * @var InMemoryObjectService
	 */
	private InMemoryObjectService $store;

	/**
	 * Seed Aldmoor with one ability and one skill.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->store = new InMemoryObjectService();
		$this->store->seed('setting', ['id' => self::WORLD, 'name' => 'Aldmoor', 'status' => 'active']);
		$this->store->seed('ability', ['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'name' => 'Strength', 'setting' => self::WORLD]);
		$this->store->seed(
			'skill',
			['id' => 'aaaaaaaa-0000-4000-8000-000000000002', 'name' => 'Swordsmanship', 'requiredStats' => ['aaaaaaaa-0000-4000-8000-000000000001'], 'setting' => self::WORLD]
		);

	}//end setUp()

	/**
	 * The controller for a user.
	 *
	 * @param string|null $uid The user, or null for nobody.
	 * @param bool $gm Whether the user is in the game master group.
	 * @param bool $admin Whether the user is an admin.
	 *
	 * @return WorldsController The controller.
	 */
	private function controller(?string $uid, bool $gm = false, bool $admin = false): WorldsController {
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturn($this->store);
		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getInstalledApps')->willReturn(['openregister']);
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = ''): string {
				[$type, $kind] = explode('_', $key, 2) + [1 => ''];
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
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$session = $this->createMock(originalClassName: IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$groups = $this->createMock(originalClassName: IGroupManager::class);
		$groups->method('isInGroup')->willReturnCallback(static fn (string $u, string $group): bool => $gm === true && $group === Application::GM_GROUP);
		$groups->method('isAdmin')->willReturn($admin);

		return new WorldsController(
			request: $this->createMock(originalClassName: IRequest::class),
			worldCopy: new WorldCopyService(objectFetcher: $fetcher, idList: new IdListNormaliser()),
			callerAccess: new CallerAccess(userSession: $session, groupManager: $groups),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
		);
	}//end controller()

	/**
	 * The shared constant names the group the register rules use.
	 *
	 * @return void
	 */
	public function testTheGameMasterGroupIsOneConstant(): void {
		$this->assertSame(expected: 'gamemasters', actual: Application::GM_GROUP);

	}//end testTheGameMasterGroupIsOneConstant()

	/**
	 * A game master copies and gets 201 with the new world and the counts.
	 *
	 * @return void
	 */
	public function testAGameMasterCopies(): void {
		$response = $this->controller(uid: 'gm', gm: true)->copy(id: self::WORLD, name: 'Aldmoor season 2');

		$this->assertSame(expected: 201, actual: $response->getStatus());
		$data = $response->getData();
		$this->assertSame(expected: 'Aldmoor season 2', actual: $data['world']['name']);
		$this->assertSame(expected: 'active', actual: $data['world']['status']);
		$this->assertSame(expected: 1, actual: $data['counts']['abilities']);
		$this->assertSame(expected: 1, actual: $data['counts']['skills']);

	}//end testAGameMasterCopies()

	/**
	 * An admin who is not in the group may copy too.
	 *
	 * @return void
	 */
	public function testAnAdminCopies(): void {
		$response = $this->controller(uid: 'admin', admin: true)->copy(id: self::WORLD, name: 'Admin copy');

		$this->assertSame(expected: 201, actual: $response->getStatus());

	}//end testAnAdminCopies()

	/**
	 * A player tries to copy: 403 and no world is created.
	 *
	 * @return void
	 */
	public function testAPlayerTriesToCopy(): void {
		$response = $this->controller(uid: 'anna')->copy(id: self::WORLD, name: 'Anna world');

		$this->assertSame(expected: 403, actual: $response->getStatus());
		$this->assertCount(expectedCount: 1, haystack: $this->store->objects['setting']);
		$this->assertSame(expected: [], actual: $this->store->saves);

	}//end testAPlayerTriesToCopy()

	/**
	 * Nobody signed in: 401.
	 *
	 * @return void
	 */
	public function testNobodySignedInIsRefused(): void {
		$this->assertSame(expected: 401, actual: $this->controller(uid: null)->copy(id: self::WORLD, name: 'x')->getStatus());
		$this->assertSame(expected: 401, actual: $this->controller(uid: null)->access()->getStatus());

	}//end testNobodySignedInIsRefused()

	/**
	 * Above the cap: 422 and nothing written.
	 *
	 * @return void
	 */
	public function testAboveTheCapIs422(): void {
		for ($i = 0; $i <= WorldCopyService::MAX_OBJECTS; $i++) {
			$this->store->seed('effect', ['id' => "bulk-{$i}", 'name' => "Effect {$i}", 'setting' => self::WORLD]);
		}

		$response = $this->controller(uid: 'gm', gm: true)->copy(id: self::WORLD, name: 'Too big');

		$this->assertSame(expected: 422, actual: $response->getStatus());
		$this->assertSame(expected: [], actual: $this->store->saves);

	}//end testAboveTheCapIs422()

	/**
	 * An empty name is 400, an unknown world 404, a malformed id 400.
	 *
	 * @return void
	 */
	public function testBadInputIsRefused(): void {
		$gm = $this->controller(uid: 'gm', gm: true);

		$this->assertSame(expected: 400, actual: $gm->copy(id: self::WORLD, name: '  ')->getStatus());
		$this->assertSame(expected: 404, actual: $gm->copy(id: '99999999-9999-4999-8999-999999999999', name: 'x')->getStatus());
		$this->assertSame(expected: 400, actual: $gm->copy(id: 'not-a-uuid', name: 'x')->getStatus());
		$this->assertSame(expected: [], actual: $this->store->saves);

	}//end testBadInputIsRefused()

	/**
	 * A write that fails answers 500 with the leftovers, and no active copy remains.
	 *
	 * @return void
	 */
	public function testAFailedCopyAnswers500(): void {
		$this->store->failOnSave = 2;

		$response = $this->controller(uid: 'gm', gm: true)->copy(id: self::WORLD, name: 'Broken');

		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertSame(expected: [], actual: $response->getData()['leftovers']);
		$this->assertCount(expectedCount: 1, haystack: $this->store->objects['setting']);

	}//end testAFailedCopyAnswers500()

	/**
	 * The preview counts for a game master and refuses a player.
	 *
	 * @return void
	 */
	public function testThePreviewIsForGameMasters(): void {
		$preview = $this->controller(uid: 'gm', gm: true)->preview(id: self::WORLD);
		$this->assertSame(expected: 200, actual: $preview->getStatus());
		$this->assertSame(expected: 1, actual: $preview->getData()['counts']['skills']);

		$this->assertSame(expected: 403, actual: $this->controller(uid: 'anna')->preview(id: self::WORLD)->getStatus());

	}//end testThePreviewIsForGameMasters()

	/**
	 * The access answer drives whether the page shows the action.
	 *
	 * @return void
	 */
	public function testAccessSaysWhoMayCopy(): void {
		$this->assertSame(expected: ['allowed' => true], actual: $this->controller(uid: 'gm', gm: true)->access()->getData());
		$this->assertSame(expected: ['allowed' => false], actual: $this->controller(uid: 'anna')->access()->getData());

	}//end testAccessSaysWhoMayCopy()
}//end class
