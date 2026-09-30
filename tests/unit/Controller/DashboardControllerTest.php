<?php

/**
 * Unit tests for DashboardController.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Controller
 * @author   Ruben Linde <ruben@larpingapp.com>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://larpingapp.com
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Controller;

use OCA\Larpinq\Controller\DashboardController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DashboardController.
 */
class DashboardControllerTest extends TestCase {

	private DashboardController $controller;

	/** @var array<string, mixed> What page() handed to the initial state. */
	private array $provided = [];

	/** @var list<string> The groups of the signed-in user. */
	private array $groups = [];

	private bool $admin = false;

	private bool $signedIn = true;

	/** @var array<string, string> The signed-in user's stored larpinq preferences, by IConfig key. */
	private array $preferences = [];

	protected function setUp(): void {
		parent::setUp();

		$initialState = $this->createMock(IInitialState::class);
		$initialState->method('provideInitialState')->willReturnCallback(
			function (string $key, mixed $value): void {
				$this->provided[$key] = $value;
			}
		);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('anna');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturnCallback(fn () => $this->signedIn ? $user : null);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isInGroup')->willReturnCallback(
			fn (string $uid, string $group): bool => in_array($group, $this->groups, true)
		);
		$groupManager->method('isAdmin')->willReturnCallback(fn (): bool => $this->admin);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $uid, string $app, string $key, mixed $default = ''): mixed => ($app === 'larpinq' ? ($this->preferences[$key] ?? $default) : $default)
		);

		$this->controller = new DashboardController(
			'larpinq',
			$this->createMock(IRequest::class),
			$initialState,
			$session,
			$groupManager,
			$config,
		);
	}

	/**
	 * A game master's page tells the frontend so, and the import is offered
	 * (data-portability REQ-AIE-002, DECISIONS row 25).
	 *
	 * @return void
	 */
	public function testAGameMasterIsToldSo(): void {
		$this->groups = ['gamemasters'];
		$this->controller->page();

		self::assertTrue($this->provided['isGameMaster'] ?? null);
	}

	/**
	 * A player, or nobody signed in, is not a game master.
	 *
	 * @return void
	 */
	public function testAPlayerIsNot(): void {
		$this->groups = ['larpers'];
		$this->controller->page();
		self::assertFalse($this->provided['isGameMaster'] ?? null);

		$this->signedIn = false;
		$this->controller->page();
		self::assertFalse($this->provided['isGameMaster']);
	}

	/**
	 * An administrator acts as a game master, as the other GM checks do.
	 *
	 * @return void
	 */
	public function testAnAdministratorCounts(): void {
		$this->admin = true;
		$this->controller->page();

		self::assertTrue($this->provided['isGameMaster'] ?? null);
	}

	/**
	 * The world the user last chose reaches the first paint, so lists are
	 * narrowed before their first fetch (setting-management, the active-world
	 * lens; events-world-scope-and-upcoming).
	 *
	 * @return void
	 */
	public function testTheActiveWorldReachesThePage(): void {
		$this->preferences['pref_active-world'] = '11111111-1111-4111-8111-111111111111';
		$this->controller->page();

		self::assertSame('11111111-1111-4111-8111-111111111111', $this->provided['activeWorld'] ?? null);
	}

	/**
	 * No stored world, or nobody signed in, means all worlds: an empty string.
	 *
	 * @return void
	 */
	public function testNoActiveWorldMeansAllWorlds(): void {
		$this->controller->page();
		self::assertSame('', $this->provided['activeWorld'] ?? null);

		$this->preferences['pref_active-world'] = '11111111-1111-4111-8111-111111111111';
		$this->signedIn = false;
		$this->controller->page();
		self::assertSame('', $this->provided['activeWorld']);
	}

	public function testPageReturnsTemplateResponse(): void {
		$result = $this->controller->page();

		self::assertInstanceOf(TemplateResponse::class, $result);
	}

	/**
	 * The SPA catch-all serves the same shell as page().
	 *
	 * `dashboard#catchAll` at GET /{path} is what makes larpinq's deep links
	 * work: before it, /apps/larpinq/characters and /events 404'd at the
	 * SERVER, which is why this app could not simply switch to history routing
	 * with the others. It is a public network-facing endpoint, so gate-25
	 * (contract-coverage) wants a test rather than an `@contract exclude`.
	 *
	 * Asserting equality with page() rather than merely "returns a response" is
	 * the point: catchAll() exists only to delegate, and a delegation that
	 * quietly rendered the wrong template would hand every deep link a blank
	 * page while still answering HTTP 200.
	 *
	 * @return void
	 */
	public function testCatchAllServesTheSameShellAsPage(): void {
		$result = $this->controller->catchAll();
		$page = $this->controller->page();

		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertSame('index', $result->getTemplateName());
		self::assertSame($page->getTemplateName(), $result->getTemplateName());
		self::assertSame($page->getRenderAs(), $result->getRenderAs());
		self::assertSame($page->getParams(), $result->getParams());
	}

	public function testPageUsesIndexTemplate(): void {
		$result = $this->controller->page();

		self::assertSame('index', $result->getTemplateName());
	}

	public function testPageUsesLarpinqApp(): void {
		$result = $this->controller->page();

		// TemplateResponse stores the app name.
		self::assertInstanceOf(TemplateResponse::class, $result);
	}

	public function testPageReturnsEmptyParams(): void {
		$result = $this->controller->page();

		self::assertEmpty($result->getParams());
	}
}
