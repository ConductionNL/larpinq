<?php

/**
 * Unit tests for MigrateUserPreferences.
 *
 * The failure this guards is invisible by construction: every reader of these
 * preferences carries a DEFAULT, so a lost value does not error, it reverts.
 * For this app that means the six-step first-visit walkthrough and the setup
 * wizard re-opening for every existing player and game master.
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Repair
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Repair;

use Closure;
use OCA\Larpinq\AppInfo\Application;
use OCA\Larpinq\Repair\MigrateUserPreferences;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for the larpingapp -> larpinq per-user preference migration.
 */
class MigrateUserPreferencesTest extends TestCase {
	private const OLD_APP_ID = 'larpingapp';

	/** @var IConfig&MockObject The config. */
	private IConfig&MockObject $config;

	/** @var IUserManager&MockObject The user manager. */
	private IUserManager&MockObject $userManager;

	/** @var LoggerInterface&MockObject The logger. */
	private LoggerInterface&MockObject $logger;

	/** @var IOutput&MockObject The output. */
	private IOutput&MockObject $output;

	/** @var MigrateUserPreferences The step. */
	private MigrateUserPreferences $step;

	/**
	 * Build the doubles and the subject every test starts from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->userManager = $this->createMock(originalClassName: IUserManager::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
		$this->output = $this->createMock(originalClassName: IOutput::class);

		$this->step = new MigrateUserPreferences(
			config: $this->config,
			userManager: $this->userManager,
			logger: $this->logger
		);
	}//end setUp()

	/**
	 * Make callForSeenUsers() hand the given user ids to the step's closure.
	 *
	 * @param string[] $userIds The seen users to walk.
	 *
	 * @return void
	 */
	private function seedSeenUsers(array $userIds): void {
		$this->userManager->method('callForSeenUsers')->willReturnCallback(
			function (Closure $callback) use ($userIds): void {
				foreach ($userIds as $uid) {
					$user = $this->createMock(originalClassName: IUser::class);
					$user->method('getUID')->willReturn($uid);
					$callback($user);
				}
			}
		);
	}//end seedSeenUsers()

	/**
	 * Get name mentions the old app id.
	 *
	 * @return void
	 */
	public function testGetNameMentionsTheOldAppId(): void {
		self::assertStringContainsString(needle: self::OLD_APP_ID, haystack: $this->step->getName());
	}//end testGetNameMentionsTheOldAppId()

	/**
	 * The happy path, across more than one user.
	 */
	public function testCopiesEverySeenUsersStoredPreferences(): void {
		$this->seedSeenUsers(userIds: ['alice', 'bob']);

		$this->config->method('getUserKeys')->willReturnCallback(
			static fn (string $uid, string $app): array => $app === self::OLD_APP_ID
				? ['pref_walkthrough_completed_version', 'pref_support-dialog-seen']
				: []
		);

		$this->config->method('getUserValue')->willReturnCallback(
			static function (string $uid, string $app, string $key): string {
				if ($app !== self::OLD_APP_ID) {
					return '';
				}

				return $key === 'pref_walkthrough_completed_version' ? '0.1.27' : '1';
			}
		);

		$written = [];
		$this->config->method('setUserValue')->willReturnCallback(
			static function (string $uid, string $app, string $key, string $value) use (&$written): void {
				$written[$uid][$app][$key] = $value;
			}
		);

		$this->step->run($this->output);

		foreach (['alice', 'bob'] as $uid) {
			self::assertSame(
				expected: [
					'pref_walkthrough_completed_version' => '0.1.27',
					'pref_support-dialog-seen' => '1',
				],
				actual: $written[$uid][Application::APP_ID] ?? [],
				message: "preferences for {$uid} were not carried across"
			);
		}
	}//end testCopiesEverySeenUsersStoredPreferences()

	/**
	 * ⚠️ THE ENUMERATION STRATEGY IS PINNED HERE ON PURPOSE.
	 *
	 * The pilot walked getUsersForUserValue(app, key, value), which needs the
	 * value up front. That is exhaustive only for CLOSED value sets. This app's
	 * PreferencesController is a GENERIC key/value endpoint storing arbitrary
	 * strings (the walkthrough marker holds a version like `0.1.27`), so the
	 * value-enumerating call would migrate NOTHING WHILE REPORTING SUCCESS.
	 *
	 * A regression to that strategy would still pass every other test in this
	 * file, because they seed users through callForSeenUsers(). This assertion
	 * is the only thing that catches it.
	 */
	public function testNeverEnumeratesByValue(): void {
		$this->seedSeenUsers(userIds: ['alice']);
		$this->config->method('getUserKeys')->willReturn([]);

		$this->config->expects(self::never())->method('getUsersForUserValue');

		$this->step->run($this->output);
	}//end testNeverEnumeratesByValue()

	/**
	 * Does not overwrite a preference already set under the new app id.
	 *
	 * @return void
	 */
	public function testDoesNotOverwriteAPreferenceAlreadySetUnderTheNewAppId(): void {
		$this->seedSeenUsers(userIds: ['alice']);

		$this->config->method('getUserKeys')->willReturnCallback(
			static fn (string $uid, string $app): array => $app === self::OLD_APP_ID
				? ['pref_walkthrough_completed_version']
				: []
		);

		// Something is already stored under the new app id — a preference the
		// user changed after the rename. It must survive.
		$this->config->method('getUserValue')->willReturnCallback(
			static fn (string $uid, string $app): string => $app === self::OLD_APP_ID ? '0.1.27' : '999.0.0'
		);

		$this->config->expects(self::never())->method('setUserValue');

		$this->step->run($this->output);
	}//end testDoesNotOverwriteAPreferenceAlreadySetUnderTheNewAppId()

	/**
	 * A read that throws must not escape run() — this step runs under
	 * <install>, so a throw means the app never enables.
	 */
	public function testAReadThatThrowsIsLoggedAndTheWalkContinues(): void {
		$this->seedSeenUsers(userIds: ['alice']);

		$this->config->method('getUserKeys')->willReturnCallback(
			static fn (string $uid, string $app): array => $app === self::OLD_APP_ID
				? ['pref_poisoned', 'pref_walkthrough_completed_version']
				: []
		);

		$this->config->method('getUserValue')->willReturnCallback(
			static function (string $uid, string $app, string $key): string {
				if ($key === 'pref_poisoned') {
					throw new RuntimeException('unreadable preference');
				}

				return $app === self::OLD_APP_ID ? '0.1.27' : '';
			}
		);

		$written = [];
		$this->config->method('setUserValue')->willReturnCallback(
			static function (string $uid, string $app, string $key, string $value) use (&$written): void {
				$written[$key] = $value;
			}
		);

		$this->logger->expects(self::atLeastOnce())->method('warning');

		$this->step->run($this->output);

		self::assertSame(
			expected: ['pref_walkthrough_completed_version' => '0.1.27'],
			actual: $written,
			message: 'the walk must continue past an unreadable preference'
		);
	}//end testAReadThatThrowsIsLoggedAndTheWalkContinues()

	/**
	 * An unreadable key list for one user skips that user, not the install.
	 */
	public function testAnUnreadableKeyListForOneUserIsSurvivable(): void {
		$this->seedSeenUsers(userIds: ['alice']);

		$this->config->method('getUserKeys')
			->willThrowException(new RuntimeException('preferences unavailable'));

		$this->config->expects(self::never())->method('setUserValue');
		$this->logger->expects(self::atLeastOnce())->method('warning');

		$this->step->run($this->output);
	}//end testAnUnreadableKeyListForOneUserIsSurvivable()

	/**
	 * If the user backend itself is down, warn and return — never throw.
	 */
	public function testUserEnumerationFailureIsSurvivable(): void {
		$this->userManager->method('callForSeenUsers')
			->willThrowException(new RuntimeException('user backend unavailable'));

		$this->config->expects(self::never())->method('setUserValue');
		$this->logger->expects(self::atLeastOnce())->method('warning');
		$this->output->expects(self::atLeastOnce())->method('warning');

		$this->step->run($this->output);
	}//end testUserEnumerationFailureIsSurvivable()
}//end class
